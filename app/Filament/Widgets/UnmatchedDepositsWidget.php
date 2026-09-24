<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\UnmatchedDepositsPage;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;

/**
 * M-Pesa payments credited to nobody, from `GET /deposits/unmatched`. Shown on
 * the dashboard and above the queue. KadiApi's summary always counts deposits
 * still unmatched, ignoring the status and date filters, so it is labelled as
 * a total rather than a count for the open tab. Cached for a minute and never polled,
 * because the endpoint shares KadiApi's 20/min limiter with the finance reports.
 */
class UnmatchedDepositsWidget extends BaseWidget
{
    /**
     * Dispatched by the queue after a deposit is assigned or refunded.
     */
    public const REFRESH_EVENT = 'unmatched-deposits-changed';

    protected static ?int $sort = 21;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return UnmatchedDepositsPage::canAccess();
    }

    #[On(self::REFRESH_EVENT)]
    public function refreshSummary(): void
    {
        // The summary cache was cleared by the write; re-rendering fetches it again.
    }

    protected function getStats(): array
    {
        try {
            $summary = app(GameApiService::class)->getUnmatchedDepositsSummary();
            $error = false;
        } catch (\Throwable $e) {
            Log::warning('Unmatched deposits summary failed', ['error' => $e->getMessage()]);
            $summary = [];
            $error = true;
        }

        $count = (int) ($summary['unmatched_count'] ?? 0);
        $url = UnmatchedDepositsPage::getUrl();

        return [
            Stat::make('Unmatched Deposits', $error ? '—' : number_format($count))
                ->description(match (true) {
                    $error => 'KadiApi could not be reached',
                    $count === 0 => 'Every payment is credited to a customer',
                    default => 'Credited to nobody, across all dates · open the queue',
                })
                ->descriptionIcon($count > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color(match (true) {
                    $error => 'gray',
                    $count > 0 => 'warning',
                    default => 'success',
                })
                ->url($url),

            Stat::make('Unmatched Amount', $error ? '—' : Format::money($summary['unmatched_amount'] ?? 0))
                ->description('Every deposit still unmatched, any date · owed until assigned or refunded')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($error ? 'gray' : ($count > 0 ? 'danger' : 'success'))
                ->url($url),
        ];
    }
}
