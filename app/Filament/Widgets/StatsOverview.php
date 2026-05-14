<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\CompanyWallet;
use App\Services\GameApiService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function getStats(): array
    {
        $gameApi = app(GameApiService::class);

        $mpesaBalance = Cache::remember('mpesa_balance', 300, fn () => $gameApi->getB2CBalance());

        $totalCustomers = Account::count();
        $activeToday = Account::whereDate('reset_time', today())->count();
        $companyBalance = CompanyWallet::find(CompanyWallet::MAIN_WALLET)?->balance ?? 0;

        return [
            Stat::make('Total Players', number_format($totalCustomers))
                ->description('All registered accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Active Today', $activeToday)
                ->description('Players active today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('M-Pesa Balance', 'KES '.number_format($mpesaBalance, 2))
                ->description('B2C account balance')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('warning'),

            Stat::make('Company Wallet', 'KES '.number_format($companyBalance, 2))
                ->description('Available company funds')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('gray'),
        ];
    }
}
