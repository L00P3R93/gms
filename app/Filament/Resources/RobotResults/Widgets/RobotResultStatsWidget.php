<?php

namespace App\Filament\Resources\RobotResults\Widgets;

use App\Models\PlayedGame;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RobotResultStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $base = fn () => PlayedGame::where('match_type', PlayedGame::TYPE_ROBOT);

        return [
            Stat::make('Total Robot Games', number_format($base()->count()))
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),
            Stat::make("Today's Games", number_format($base()->whereDate('time', today())->count()))
                ->descriptionIcon('heroicon-m-sun')
                ->color('info'),
            Stat::make("This Week's Games", number_format($base()->whereBetween('time', [now()->startOfWeek(), now()->endOfWeek()])->count()))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
            Stat::make("This Month's Games", number_format($base()->whereMonth('time', now()->month)->whereYear('time', now()->year)->count()))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
        ];
    }
}
