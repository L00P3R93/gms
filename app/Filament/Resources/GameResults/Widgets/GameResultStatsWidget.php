<?php

namespace App\Filament\Resources\GameResults\Widgets;

use App\Services\GameApiService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class GameResultStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $apiError = false;

        try {
            $stats = Cache::remember('gms_dashboard_stats', 60, fn () => app(GameApiService::class)->getDashboardStats());
            $played = $stats['played'] ?? [];
            $income = $stats['income'] ?? [];
        } catch (\Throwable) {
            $apiError = true;
            $played = [];
            $income = [];
        }

        $fmt = fn ($v) => $v !== null && $v !== '' ? 'KES '.number_format((float) $v, 2) : 'KES —';
        $fmtInt = fn ($v) => $v !== null && $v !== '' ? number_format((int) $v) : '—';

        return [
            Stat::make('Single Games Today', $fmtInt($played['games'] ?? null))
                ->description('Multiplayer single games played today')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color($apiError ? 'gray' : 'info'),
            Stat::make('Single Game Income', $fmt($income['single_games_income'] ?? null))
                ->description('Total house income from single games')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($apiError ? 'gray' : 'warning'),
            Stat::make('All Games Today', $fmtInt($played['total'] ?? null))
                ->description("Jackpots: {$fmtInt($played['jackpots'] ?? null)} · Tournaments: {$fmtInt($played['tournament'] ?? null)}")
                ->descriptionIcon('heroicon-m-building-library')
                ->color($apiError ? 'gray' : 'success'),
        ];
    }
}
