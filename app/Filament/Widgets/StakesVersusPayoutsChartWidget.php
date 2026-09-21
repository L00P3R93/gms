<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Admin-only comparison of what players staked, what was paid back and what the
 * house kept, across the finance summary windows.
 */
class StakesVersusPayoutsChartWidget extends ChartWidget
{
    use LoadsFinanceReport;

    protected ?string $heading = 'Stakes vs Payouts vs Revenue';

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '300s';

    protected function getData(): array
    {
        $windows = $this->loadFinanceReport('summary')['windows'] ?? [];
        $keys = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month'];

        $series = fn (callable $value): array => collect($keys)
            ->map(fn (string $label, string $key): float => (float) $value($windows[$key] ?? []))
            ->values()
            ->all();

        return [
            'datasets' => [
                ['label' => 'Stakes (KES)', 'data' => $series(fn (array $w) => $w['stakes'] ?? 0), 'backgroundColor' => 'rgba(59,130,246,0.7)'],
                ['label' => 'Paid to players (KES)', 'data' => $series(fn (array $w) => $w['payouts'] ?? 0), 'backgroundColor' => 'rgba(245,158,11,0.7)'],
                ['label' => 'House revenue (KES)', 'data' => $series(fn (array $w) => $w['revenue']['total'] ?? 0), 'backgroundColor' => 'rgba(16,185,129,0.7)'],
            ],
            'labels' => array_values($keys),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Abbreviate the y axis (12k, 1.5M) to match the compact figures on the dashboard.
     */
    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => Math.abs(value) >= 1000000
                                ? (value / 1000000).toFixed(1) + 'M'
                                : Math.abs(value) >= 1000 ? (value / 1000).toFixed(0) + 'k' : value,
                        },
                    },
                },
            }
            JS);
    }
}
