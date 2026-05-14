<?php

namespace App\Filament\Widgets;

use App\Models\PlayedGame;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Game Revenue (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function getData(): array
    {
        $singlesData = Trend::query(
            PlayedGame::whereIn('match_type', ['multiplayer2', 'multiplayer3', 'multiplayer4'])
        )
            ->dateColumn('time')
            ->between(start: now()->subDays(29), end: now())
            ->perDay()
            ->sum('amount');

        $tournamentData = Trend::query(
            PlayedGame::where('match_type', 'TN')
        )
            ->dateColumn('time')
            ->between(start: now()->subDays(29), end: now())
            ->perDay()
            ->sum('amount');

        $jackpotData = Trend::query(
            PlayedGame::where('match_type', 'JP')
        )
            ->dateColumn('time')
            ->between(start: now()->subDays(29), end: now())
            ->perDay()
            ->sum('amount');

        $labels = $singlesData->map(fn (TrendValue $value) => $value->date)->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Singles Income (KES)',
                    'data' => $singlesData->map(fn (TrendValue $v) => round($v->aggregate * 0.10, 2))->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245,158,11,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Tournament Income (KES)',
                    'data' => $tournamentData->map(fn (TrendValue $v) => round($v->aggregate * 0.10, 2))->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Jackpot Income (KES)',
                    'data' => $jackpotData->map(fn (TrendValue $v) => round($v->aggregate * 0.10, 2))->toArray(),
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
