<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Exceptions\MpesaApiException;
use App\Models\Payout;
use App\Services\MpesaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('app:process-pending-payouts')]
#[Description('Process Pending Payouts per minute via M-Pesa B2C')]
class ProcessPendingPayouts extends Command
{
    public function __construct(private readonly MpesaService $mpesa)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::transaction(function () use (&$processedCount, &$successCount, &$failedCount, &$totalAmount, &$details) {
            $payout = Payout::query()
                ->where('status', PayoutStatus::Approved)
                ->lockForUpdate()
                ->first();

            if (! $payout) {
                $this->info('No pending payouts to process.');

                return;
            }

            $payee = $payout->payee;
            $this->info("Processing payout ID {$payout->id} — KES {$payout->amount} to {$payee->phone}.");

            $userParams = [
                'Amount' => (int) $payout->amount,
                'PartyB' => $payee->phone,
                'Remarks' => 'Company Payout',
                'Occasion' => '',
            ];
            Log::info('User Params: ', $userParams);

            try {
                $response = $this->mpesa->b2c($userParams);
            } catch (MpesaApiException $e) {
                Log::channel('mpesa')->error("B2C request failed for payout {$payout->id}: {$e->getMessage()}");
                $payout->updateQuietly([
                    'status' => PayoutStatus::Failed,
                    'response' => $e->getMessage(),
                ]);

                return;
            }

            $responseCode = $response['ResponseCode'] ?? null;
            $ConversationID = $response['ConversationID'] ?? null;
            $ResponseDescription = $response['ResponseDescription'] ?? null;

            if ($responseCode == '0') {
                Log::channel('mpesa')->info('B2C success for payout ', $response);
                $payout->update([
                    'conversation_id' => $ConversationID,
                    'receipt' => $ConversationID,
                    'response' => $ResponseDescription,
                    'processed_at' => now(),
                ]);
            } else {
                Log::channel('mpesa')->error("B2C failed for payout: {$payout->id}. Code: {$responseCode} — {$ResponseDescription}");
                $payout->update([
                    'status' => PayoutStatus::Failed,
                    'response' => json_encode($response),
                ]);
            }
        });

        return self::SUCCESS;
    }
}
