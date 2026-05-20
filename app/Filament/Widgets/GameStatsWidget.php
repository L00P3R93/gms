<?php

namespace App\Filament\Widgets;

use App\Services\GameApiService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class GameStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Income Today (KES)';

    public static function canView(): bool
    {
        // return auth()->user()?->hasRole('super-admin') ?? false;
        return false;
    }

    protected function getStats(): array
    {
        try {
            $stats = Cache::remember('gms_dashboard_stats', 120,
                fn () => app(GameApiService::class)->getDashboardStats()
            );
        } catch (\Throwable) {
            $stats = [];
        }

        $income = $stats['income'] ?? [];

        return [
            Stat::make('Singles Income', 'KES '.number_format((float) ($income['games'] ?? 0), 2))
                ->description('All-time (10% house cut)')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Tournament Income', 'KES '.number_format((float) ($income['tournaments'] ?? 0), 2))
                ->description('All-time (10% house cut)')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('info'),

            Stat::make('Jackpot Income', 'KES '.number_format((float) ($income['jackpots'] ?? 0), 2))
                ->description('All-time (10% house cut)')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
        ];
    }
}
