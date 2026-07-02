<?php

namespace App\Filament\Widgets;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use Filament\Notifications\Notification;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Game Revenue (Last 30 Days)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '300s';

    public static function canView(): bool
    {
        return true;
    }

    protected function getData(): array
    {
        $apiError = false;

        try {
            $data = Cache::remember('revenue-chart-data', 60, fn () => app(GameApiService::class)->getDailyIncome());
        } catch (GameApiException $e) {
            $apiError = true;
            $data = ['daily_stats' => []];
        }

        if ($apiError) {
            Notification::make()
                ->title('Game API Unavailable')
                ->body('Could not connect to the wallet API. Stats shown may be incomplete.')
                ->warning()
                ->send();
        }

        $labels = collect(range(29, 0))
            ->map(fn ($i) => now()->subDays($i)->format('Y-m-d'))
            ->toArray();

        $dailyStats = $data['daily_stats'] ?? [];

        $singleGamesData = [];
        $tournamentsData = [];
        $jackpotsData = [];

        foreach ($labels as $date) {
            $stats = $dailyStats[$date] ?? [];
            $singleGamesData[] = $stats['single_games'] ?? 0;
            $tournamentsData[] = $stats['tournaments'] ?? 0;
            $jackpotsData[] = $stats['jackpots'] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Singles Income (KES)',
                    'data' => $singleGamesData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245,158,11,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Tournament Income (KES)',
                    'data' => $tournamentsData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Jackpot Income (KES)',
                    'data' => $jackpotsData,
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
