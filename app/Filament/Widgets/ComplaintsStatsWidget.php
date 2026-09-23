<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ComplaintsPage;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;

/**
 * Open player complaints, money held in dispute escrow and aged disputes. Shown
 * on the dashboard and in the header of {@see ComplaintsPage}, to anyone who
 * can view complaints.
 */
class ComplaintsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 22;

    protected ?string $pollingInterval = '120s';

    protected ?string $heading = 'Player Complaints';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ComplaintsPage::canAccess();
    }

    protected function getStats(): array
    {
        $pending = ComplaintsPage::pendingCount();
        $aged = ComplaintsPage::agedPendingCount();
        $held = $this->currentlyHeld();
        $url = ComplaintsPage::getUrl();

        return [
            Stat::make('Pending Complaints', $pending === null ? '—' : number_format($pending))
                ->description('Awaiting a decision')
                ->descriptionIcon('heroicon-m-scale')
                ->color($pending === null ? 'gray' : 'warning')
                ->url($url),
            Stat::make('Held in Dispute Escrow', $held === null ? '—' : Format::money($held))
                ->description('Winnings on hold across open complaints')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($held === null ? 'gray' : 'primary')
                ->url($url),
            Stat::make('Aged Disputes', $aged === null ? '—' : number_format($aged))
                ->description('Pending for '.ComplaintsPage::AGED_AFTER_DAYS.'+ days')
                ->descriptionIcon('heroicon-m-clock')
                ->color(($aged ?? 0) > 0 ? 'danger' : 'gray')
                ->url($url),
        ];
    }

    protected function currentlyHeld(): ?float
    {
        try {
            return (float) (app(GameApiService::class)->getDisputesSummary()['currently_held'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Disputes summary failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
