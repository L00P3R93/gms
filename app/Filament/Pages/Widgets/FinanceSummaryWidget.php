<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\FinanceReportPage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * Header widget for {@see FinanceReportPage}. Lives outside the auto-discovered
 * widgets directory so it never lands on the dashboard — it is referenced
 * explicitly by the report page's getHeaderWidgets().
 */
class FinanceSummaryWidget extends BaseWidget
{
    /**
     * Cards supplied by the report page via getWidgetData(). Reactive so the
     * cards refresh when the page's period or filters change.
     *
     * @var list<array{label: string, value: string, description?: string, color?: string, icon?: string}>
     */
    #[Reactive]
    public array $stats = [];

    protected function getStats(): array
    {
        return collect($this->stats)
            ->map(function (array $stat): Stat {
                $card = Stat::make($stat['label'], $stat['value'])
                    ->color($stat['color'] ?? 'primary');

                if (isset($stat['description'])) {
                    $card->description($stat['description']);
                }

                if (isset($stat['icon'])) {
                    $card->descriptionIcon($stat['icon']);
                }

                return $card;
            })
            ->all();
    }
}
