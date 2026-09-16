<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Exceptions\MpesaApiException;
use App\Models\Payout;
use App\Services\MpesaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('payouts:process-pending')]
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
        $payout = DB::transaction(function (): ?Payout {
            $payout = Payout::query()
                ->where('status', PayoutStatus::Approved)
                ->lockForUpdate()
                ->first();

            $payout?->update(['status' => PayoutStatus::Processing]);

            return $payout;
        });

        if (! $payout) {
            $this->info('No pending payouts to process.');

            return self::SUCCESS;
        }

        $payee = $payout->payee;
        $this->info("Processing payout ID {$payout->id} — KES {$payout->amount} to {$payee->phone}.");

        $userParams = [
            'Amount' => (int) $payout->amount,
            'PartyB' => $payee->phone,
            'Remarks' => 'Company Payout',
            'Occasion' => '',
        ];
        // Log::channel('mpesa')->info('B2C request params', $userParams);

        try {
            $response = $this->mpesa->b2c($userParams);
        } catch (MpesaApiException|ConnectionException $e) {
            Log::channel('mpesa')->error("B2C request failed for payout {$payout->id}: {$e->getMessage()}");
            $payout->update([
                'status' => PayoutStatus::Failed,
                'response' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $responseCode = (string) ($response['ResponseCode'] ?? '');
        $conversationId = $response['ConversationID'] ?? null;
        $responseDescription = $response['ResponseDescription'] ?? null;

        if ($responseCode === '0') {
            // Accepted for processing only — MpesaB2CResultController finalizes
            // Completed/Failed once Safaricom's async result callback arrives.
            Log::channel('mpesa')->info("B2C request accepted for payout {$payout->id}", $response);
            $payout->update([
                'conversation_id' => $conversationId,
                'response' => $responseDescription,
            ]);
        } else {
            Log::channel('mpesa')->error("B2C rejected for payout {$payout->id}. Code: {$responseCode} — {$responseDescription}");
            $payout->update([
                'status' => PayoutStatus::Failed,
                'response' => json_encode($response),
            ]);
        }

        return self::SUCCESS;
    }
}
