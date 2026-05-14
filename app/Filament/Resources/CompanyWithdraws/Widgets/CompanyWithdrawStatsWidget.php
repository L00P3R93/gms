<?php

namespace App\Filament\Resources\CompanyWithdraws\Widgets;

use App\Enums\CompanyWithdrawStatus;
use App\Models\CompanyWallet;
use App\Models\CompanyWithdraw;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CompanyWithdrawStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $balance = CompanyWallet::find(CompanyWallet::MAIN_WALLET)?->balance ?? 0;

        $pendingCount = CompanyWithdraw::where('status', CompanyWithdrawStatus::Pending->value)->count();
        $totalWithdrawn = CompanyWithdraw::where('status', CompanyWithdrawStatus::Completed->value)->sum('amount');

        $withdrawLast7 = collect(range(6, 0))
            ->map(fn ($d) => CompanyWithdraw::whereDate('created_at', now()->subDays($d))->count())
            ->toArray();

        return [
            Stat::make('Wallet Balance', 'KES '.number_format($balance, 2))
                ->color('primary'),
            Stat::make('Pending Withdrawals', $pendingCount)
                ->color('warning')
                ->chart($withdrawLast7)
                ->descriptionIcon('heroicon-m-clock'),
            Stat::make('Total Withdrawn', 'KES '.number_format($totalWithdrawn, 2))
                ->color('info'),
        ];
    }
}
