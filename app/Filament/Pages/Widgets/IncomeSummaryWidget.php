<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\GameIncomeReport;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * Header widget for {@see GameIncomeReport}. Lives outside
 * the auto-discovered widgets directory so it never lands on the dashboard —
 * it is referenced explicitly by the report page's getHeaderWidgets().
 */
class IncomeSummaryWidget extends BaseWidget
{
    /**
     * Totals supplied by the report page via getWidgetData(). Reactive so the
     * cards refresh when the page's period filter changes.
     *
     * @var array<string, float>
     */
    #[Reactive]
    public array $summary = [];

    protected ?string $heading = 'Income Summary';

    protected function getStats(): array
    {
        return [
            Stat::make('Singles Income', Format::money($this->summary['singles_income'] ?? 0))
                ->description('Multiplayer single games')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Tournament Income', Format::money($this->summary['tournament_income'] ?? 0))
                ->description('Competition rounds')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('info'),

            Stat::make('Jackpot Income', Format::money($this->summary['jackpot_income'] ?? 0))
                ->description('Jackpot tier payouts')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),

            Stat::make('Grand Total', Format::money($this->summary['grand_total'] ?? 0))
                ->description('Total house income, 10% cut')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }
}
