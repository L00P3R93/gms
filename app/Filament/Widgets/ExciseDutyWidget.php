<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Filament\Pages\ExciseDutyReport;
use App\Filament\Pages\ExciseRemittancesReport;
use App\Filament\Pages\ExciseReturnsReport;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Admin-only excise duty position: what is owed to KRA, what was charged on
 * deposits this month and whether any monthly return is overdue (returns and
 * payment are due by the 20th of the following month).
 */
class ExciseDutyWidget extends BaseWidget
{
    use LoadsFinanceReport;

    protected static ?int $sort = 23;

    protected ?string $pollingInterval = '120s';

    protected ?string $heading = 'Excise Duty';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $excise = $this->loadFinanceReport('excise-duty', [
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'group_by' => 'month',
        ]);
        $returns = $this->loadFinanceReport('excise-duty/returns', [
            'from' => today()->subMonths(11)->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]);
        $error = $this->financeApiError;

        $totals = $excise['totals'] ?? [];
        $outstanding = (float) ($excise['payable']['outstanding'] ?? 0);
        $oldestUnremitted = $excise['payable']['oldest_unremitted_at'] ?? null;
        $rate = (float) ($excise['rate'] ?? 0);

        $returnItems = collect($returns['items'] ?? [])->filter(fn ($item): bool => is_array($item));
        $overdue = $returnItems->filter(fn (array $item): bool => (bool) ($item['overdue'] ?? false));
        $nextDue = $returnItems
            ->filter(fn (array $item): bool => (float) ($item['outstanding'] ?? 0) > 0 && filled($item['due_date'] ?? null))
            ->sortBy('due_date')
            ->first();

        return [
            Stat::make('Excise Payable', Format::money($outstanding))
                ->description($outstanding > 0 && $oldestUnremitted ? 'Unremitted since '.Format::date($oldestUnremitted) : 'Nothing owed to KRA')
                ->descriptionIcon('heroicon-m-building-library')
                ->color($error ? 'gray' : ($outstanding > 0 ? 'warning' : 'success'))
                ->url(ExciseRemittancesReport::canAccess() ? ExciseRemittancesReport::getUrl() : null),

            Stat::make('Excise Charged (Month)', Format::money($totals['excise_net'] ?? 0))
                ->description('On '.Format::compactMoney($totals['gross_deposits'] ?? 0).' deposits'.($rate > 0 ? ' at '.ExciseDutyReport::percent($rate) : '')
                    .(($excise['enabled'] ?? true) ? '' : ' · disabled'))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color($error ? 'gray' : 'info')
                ->url(ExciseDutyReport::canAccess() ? ExciseDutyReport::getUrl() : null),

            ($overdue->isNotEmpty()
                ? Stat::make('Overdue Returns', number_format($overdue->count()))
                    ->description(Format::money($overdue->sum(fn (array $item): float => (float) ($item['outstanding'] ?? 0))).' past the due date')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color($error ? 'gray' : 'danger')
                : Stat::make('Next Return Due', $nextDue ? Format::date($nextDue['due_date']) : '—')
                    ->description($nextDue
                        ? Format::money($nextDue['outstanding']).' for '.Carbon::parse($nextDue['period_start'] ?? $nextDue['due_date'])->format('M Y')
                        : 'No returns outstanding')
                    ->descriptionIcon('heroicon-m-calendar-days')
                    ->color($error ? 'gray' : 'success'))
                ->url(ExciseReturnsReport::canAccess() ? ExciseReturnsReport::getUrl() : null),
        ];
    }
}
