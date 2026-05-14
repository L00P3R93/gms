<?php

namespace App\Filament\Resources\Withdraws\Widgets;

use App\Enums\WithdrawStatus;
use App\Models\Withdraw;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WithdrawStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pending = Withdraw::where('status', WithdrawStatus::Pending->value)->count();
        $completed = Withdraw::where('status', WithdrawStatus::Completed->value)->count();
        $totalPaid = Withdraw::where('status', WithdrawStatus::Completed->value)->sum('amount');

        $last7 = collect(range(6, 0))
            ->map(fn ($d) => Withdraw::whereDate('created_at', now()->subDays($d))->count())
            ->toArray();

        return [
            Stat::make('Pending', $pending)
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($last7),
            Stat::make('Completed', $completed)
                ->description('Successfully paid out')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Total Paid Out', 'KES '.number_format($totalPaid, 2))
                ->color('info'),
        ];
    }
}
