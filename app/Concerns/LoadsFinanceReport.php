<?php

namespace App\Concerns;

use App\Services\GameApiService;
use Illuminate\Support\Facades\Log;

/**
 * Lets dashboard widgets read a wallet API finance report without ever
 * throwing: a failed call yields an empty payload and flips $financeApiError.
 */
trait LoadsFinanceReport
{
    protected bool $financeApiError = false;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function loadFinanceReport(string $report, array $filters = []): array
    {
        try {
            return app(GameApiService::class)->financeReport($report, $filters);
        } catch (\Throwable $e) {
            Log::warning('Dashboard finance report failed', ['report' => $report, 'error' => $e->getMessage()]);
            $this->financeApiError = true;

            return [];
        }
    }

    /**
     * Widgets on the dashboard are for company-level financials: admins only.
     */
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
