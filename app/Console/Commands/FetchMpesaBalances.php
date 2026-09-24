<?php

namespace App\Console\Commands;

use App\Exceptions\MpesaApiException;
use App\Services\MpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Asks Safaricom for the B2C shortcode's account balance. The request is only
 * acknowledged here; Safaricom posts the balance to B2CBalanceResultController,
 * which updates the single `b2c` row in mpesa_account_balances.
 *
 * C2B is not fetched: MPESA_C2B_SHORTCODE is the same shortcode as B2C, so it
 * returned the same accounts twice.
 */
class FetchMpesaBalances extends Command
{
    protected $signature = 'mpesa:fetch-balances';

    protected $description = 'Request the B2C shortcode balance from Safaricom (the result arrives by callback)';

    public function __construct(
        private readonly MpesaService $mpesa,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Requesting the M-Pesa B2C account balance...');

        try {
            $response = $this->mpesa->b2cAccountBalance();
        } catch (MpesaApiException $e) {
            Log::channel('mpesa')->error('b2c balance request API error', [
                'message' => $e->getMessage(),
                'status' => $e->statusCode,
            ]);
            $this->error("b2c API error: {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error('b2c balance request error', ['message' => $e->getMessage()]);
            $this->error("b2c error: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ((string) ($response['ResponseCode'] ?? '') !== '0') {
            Log::channel('mpesa')->error('b2c balance request rejected', ['response' => $response]);
            $this->error('b2c balance request rejected: '.($response['ResponseDescription'] ?? $response['errorMessage'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        Log::channel('mpesa')->info('b2c balance requested', ['conversation_id' => $response['ConversationID'] ?? null]);
        $this->line('  b2c: requested, the balance arrives by callback.');

        return self::SUCCESS;
    }
}
