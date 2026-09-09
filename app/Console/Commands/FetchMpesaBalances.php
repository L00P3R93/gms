<?php

namespace App\Console\Commands;

use App\Exceptions\MpesaApiException;
use App\Models\MpesaAccountBalance;
use App\Services\MpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchMpesaBalances extends Command
{
    protected $signature = 'mpesa:fetch-balances';

    protected $description = 'Fetch B2C and C2B account balances from Safaricom and store them';

    public function __construct(
        private readonly MpesaService $mpesa,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching M-Pesa account balances...');

        $this->fetchBalance('b2c');
        $this->fetchBalance('c2b');

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function fetchBalance(string $type): void
    {
        try {
            $response = match ($type) {
                'b2c' => $this->mpesa->b2cAccountBalance(),
                'c2b' => $this->mpesa->c2bAccountBalance(),
            };

            $result = $response['Result'] ?? $response;

            if (($result['ResultCode'] ?? null) != 0) {
                Log::channel('mpesa')->error("Balance fetch failed for {$type}", [
                    'response' => $response,
                ]);
                $this->error("{$type} balance fetch failed: ".($result['ResultDesc'] ?? 'Unknown error'));

                return;
            }

            $balance = MpesaAccountBalance::storeFromCallback($type, $result);

            if ($balance) {
                Log::channel('mpesa')->info("{$type} balance updated", [
                    'working' => $balance->working_account_balance,
                    'utility' => $balance->utility_account_balance,
                ]);
                $this->line("  {$type}: Working KES ".number_format($balance->working_account_balance, 2).' | Utility KES '.number_format($balance->utility_account_balance, 2));
            }
        } catch (MpesaApiException $e) {
            Log::channel('mpesa')->error("{$type} balance fetch API error", [
                'message' => $e->getMessage(),
                'status' => $e->statusCode,
            ]);
            $this->error("{$type} API error: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::channel('mpesa')->error("{$type} balance fetch error", [
                'message' => $e->getMessage(),
            ]);
            $this->error("{$type} error: {$e->getMessage()}");
        }
    }
}
