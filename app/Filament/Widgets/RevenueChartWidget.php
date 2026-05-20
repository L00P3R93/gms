<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Game Revenue (Last 30 Days)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // return auth()->user()?->hasRole('super-admin') ?? false;
        return false;
    }

    protected function getData(): array
    {
        $labels = collect(range(29, 0))
            ->map(fn ($i) => now()->subDays($i)->format('Y-m-d'))
            ->toArray();

        $empty = array_fill(0, 30, 0);

        return [
            'datasets' => [
                [
                    'label' => 'Singles Income (KES)',
                    'data' => $empty,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245,158,11,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Tournament Income (KES)',
                    'data' => $empty,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Jackpot Income (KES)',
                    'data' => $empty,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16,185,129,0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
