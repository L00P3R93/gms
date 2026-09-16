<?php

namespace App\Jobs;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(MpesaService $mpesa): void
    {
        $payouts = DB::transaction(function () {
            return Payout::where('status', PayoutStatus::Approved)
                ->lockForUpdate()
                ->get();
        });

        foreach ($payouts as $payout) {
            $this->processSingle($payout, $mpesa);
        }
    }

    private function processSingle(Payout $payout, MpesaService $mpesa): void
    {
        DB::transaction(function () use ($payout): void {
            $fresh = Payout::lockForUpdate()->find($payout->id);

            if ($fresh->status !== PayoutStatus::Approved) {
                return;
            }

            $fresh->update(['status' => PayoutStatus::Processing]);
        });

        try {
            $payee = $payout->payee;
            $userParams = [
                'Amount' => (int) $payout->amount,
                'PartyB' => $payee->phone,
                'Remarks' => 'Payout: '.$payout->reason,
                'Occasion' => '',
            ];
            $response = $mpesa->b2c($userParams);

            $conversationId = $response['ConversationID'] ?? null;

            if ($conversationId) {
                $payout->update([
                    'conversation_id' => $conversationId,
                    'receipt' => $conversationId,
                    'response' => $response['ResponseDescription'] ?? '',
                    'processed_at' => now(),
                ]);

                Log::info('Payout B2C initiated', [
                    'payout_id' => $payout->id,
                    'conversation_id' => $conversationId,
                ]);
            } else {
                $payout->update([
                    'status' => PayoutStatus::Failed,
                    'response' => json_encode($response),
                ]);

                Log::warning('Payout B2C missing ConversationID', ['payout_id' => $payout->id]);
            }
        } catch (\Throwable $e) {
            $payout->update([
                'status' => PayoutStatus::Approved,
                'response' => $e->getMessage(),
            ]);

            Log::error('Payout B2C failed, reverting to approved', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
