<?php

namespace App\Filament\Resources\Holders\Widgets;

use App\Enums\HolderStatus;
use App\Models\CompanyWallet;
use App\Models\Holder;
use App\Services\MpesaService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class HolderStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $b2cBalance = Cache::remember('mpesa_b2c_balance', 300, function () {
            try {
                return app(MpesaService::class)->getB2CBalance();
            } catch (\Throwable) {
                return 0;
            }
        });

        $companyBalance = CompanyWallet::find(CompanyWallet::MAIN_WALLET)?->balance ?? 0;

        $holderCounts = collect(range(6, 0))
            ->map(fn ($days) => Holder::whereDate('created_at', now()->subDays($days))->count())
            ->toArray();

        $totalHolders = Holder::count();
        $activeHolders = Holder::where('status', HolderStatus::Active->value)->count();

        return [
            Stat::make('Total Holders', $totalHolders)
                ->description('All registered shareholders')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart($holderCounts),
            Stat::make('Active Holders', $activeHolders)
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('M-Pesa B2C Balance', 'KES '.number_format($b2cBalance, 2))
                ->color('warning'),
            Stat::make('Company Wallet', 'KES '.number_format($companyBalance, 2))
                ->color('info'),
        ];
    }
}
