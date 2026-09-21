<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Admin-only money-in / money-out picture from the API's finance summary.
 */
class FinanceOverviewWidget extends BaseWidget
{
    use LoadsFinanceReport;

    protected static ?int $sort = 11;

    protected ?string $pollingInterval = '120s';

    protected ?string $heading = 'Finance Overview';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $windows = $this->loadFinanceReport('summary')['windows'] ?? [];
        $today = $windows['today'] ?? [];
        $month = $windows['month'] ?? [];
        $error = $this->financeApiError;

        $money = fn ($value): string => Format::compactMoney($value);

        return [
            Stat::make('Revenue Today', $money($today['revenue']['total'] ?? 0))
                ->description('Week '.$money($windows['week']['revenue']['total'] ?? 0).' · Month '.$money($month['revenue']['total'] ?? 0))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($error ? 'gray' : 'success'),

            Stat::make('Net Income (Month)', $money($month['net_income'] ?? 0))
                ->description('Expenses '.$money($month['expenses'] ?? 0).' · since '.Format::date($month['from'] ?? null))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($error ? 'gray' : (($month['net_income'] ?? 0) >= 0 ? 'primary' : 'danger')),

            Stat::make('Cash In (Month)', $money($month['cash_in'] ?? 0))
                ->description('Cash out '.$money($month['cash_out'] ?? 0).' · Net '.$money($month['net_cash'] ?? 0))
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color($error ? 'gray' : 'info'),

            Stat::make('Player Stakes Today', $money($today['stakes'] ?? 0))
                ->description('Payouts '.$money($today['payouts'] ?? 0).' · Refunds '.$money($today['refunds'] ?? 0))
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color($error ? 'gray' : 'warning'),
        ];
    }
}
