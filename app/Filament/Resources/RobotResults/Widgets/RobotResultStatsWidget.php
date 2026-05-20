<?php

namespace App\Filament\Resources\RobotResults\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RobotResultStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Robot Games', '—')
                ->description('Robot game history not tracked in current system')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),
            Stat::make("Today's Games", '—')
                ->descriptionIcon('heroicon-m-sun')
                ->color('gray'),
            Stat::make("This Week's Games", '—')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
            Stat::make("This Month's Games", '—')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('gray'),
        ];
    }
}
