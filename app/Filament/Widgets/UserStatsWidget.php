<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', UserStatus::Active->value)->count();

        $userLast7 = collect(range(6, 0))
            ->map(fn ($d) => User::whereDate('created_at', now()->subDays($d))->count())
            ->toArray();

        return [
            Stat::make('Total Users', $totalUsers)
                ->chart($userLast7)
                ->color('primary')
                ->descriptionIcon('heroicon-m-users'),
            Stat::make('Active Users', $activeUsers)
                ->color('success')
                ->descriptionIcon('heroicon-m-check-circle'),
        ];
    }
}
