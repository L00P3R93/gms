<?php

namespace App\Filament\Widgets;

use App\Services\GameApiService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AgentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('agent') ?? false;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $codes = $user->referral_codes_array;
        $gameApi = app(GameApiService::class);

        $customers = Cache::remember('agent_stats_'.$user->id, 300, fn () => $gameApi->getCustomersByReferral($codes));
        $purchases = Cache::remember('agent_stats_'.$user->id.'_purchases', 300, fn () => $gameApi->getPurchasesByReferral($codes));

        $customerCount = is_array($customers) ? count($customers) : 0;
        $purchasesTotal = is_array($purchases) ? collect($purchases)->sum('amount') : 0;

        return [
            Stat::make('My Customers', number_format($customerCount))
                ->description('Players via your referral code(s)')
                ->color('primary'),

            Stat::make('Total Purchases', 'KES '.number_format($purchasesTotal, 2))
                ->description('Revenue from your referrals')
                ->color('success'),

            Stat::make('Referral Codes', empty($codes) ? '—' : implode(', ', $codes))
                ->description('Your active referral codes')
                ->color('info'),
        ];
    }
}
