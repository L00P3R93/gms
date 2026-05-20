<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class PlayerRegistrationTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Daily Active Players (Last 30 Days)';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '300s';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function getData(): array
    {
        $labels = collect(range(29, 0))
            ->map(fn ($i) => now()->subDays($i)->format('Y-m-d'))
            ->toArray();

        return [
            'datasets' => [[
                'label' => 'Active Players',
                'data' => array_fill(0, 30, 0),
                'borderColor' => '#8b5cf6',
                'backgroundColor' => 'rgba(139,92,246,0.15)',
                'fill' => true,
                'tension' => 0.4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
