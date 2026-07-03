<?php

namespace App\Jobs;

use App\Services\IncomeDistributionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomeDistributionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(IncomeDistributionService $service): void
    {
        Log::info('Starting income distribution job');

        $result = $service->processDistribution();

        Log::info('Income distribution job completed', [
            'success' => $result['success'],
            'message' => $result['message'],
            'distribution_id' => $result['distribution_id'] ?? null,
            'delta' => $result['delta'] ?? null,
        ]);
    }
}
