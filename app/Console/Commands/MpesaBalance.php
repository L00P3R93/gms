<?php

namespace App\Console\Commands;

use App\Services\MpesaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MpesaBalance extends Command
{
    protected $signature = 'mpesa:balance';

    protected $description = 'Query the current M-Pesa B2C account balance';

    public function handle(MpesaService $mpesa): int
    {
        try {
            $result = $mpesa->queryB2CBalance();
            $this->info('M-Pesa B2C Balance:');
            $this->table(['Key', 'Value'], collect($result)->map(fn ($v, $k) => [$k, $v])->values()->toArray());
            Log::info('mpesa:balance query result', $result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to query M-Pesa balance: '.$e->getMessage());
            Log::error('mpesa:balance failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
