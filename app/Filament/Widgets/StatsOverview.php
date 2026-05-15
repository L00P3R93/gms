<?php

namespace App\Filament\Widgets;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use Filament\Notifications\Notification;
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
        $apiError = false;

        try {
            $stats = Cache::remember('gms_dashboard_stats', 60, fn () => app(GameApiService::class)->getDashboardStats());
        } catch (GameApiException $e) {
            $apiError = true;
            $stats = ['customer' => [], 'income' => [], 'played' => [], 'purchases' => []];
        } catch (\Throwable) {
            $apiError = true;
            $stats = ['customer' => [], 'income' => [], 'played' => [], 'purchases' => []];
        }

        try {
            $b2cAmount = Cache::remember('gms_b2c_balance', 300, fn () => app(GameApiService::class)->getB2CBalanceAmount());
        } catch (\Throwable) {
            $apiError = true;
            $b2cAmount = 0.0;
        }

        try {
            $todayIncome = Cache::remember('gms_wallet_today', 60, fn () => app(GameApiService::class)->getWalletToday());
        } catch (\Throwable) {
            $apiError = true;
            $todayIncome = 0.0;
        }

        if ($apiError) {
            Notification::make()
                ->title('Game API Unavailable')
                ->body('Could not connect to the wallet API. Stats shown may be incomplete.')
                ->warning()
                ->send();
        }

        $customer = $stats['customer'];
        $income = $stats['income'];
        $played = $stats['played'];
        $purchases = $stats['purchases'];

        $fmt = fn ($v) => $v !== null && $v !== '' ? number_format((float) $v, 2) : '—';
        $fmtInt = fn ($v) => $v !== null && $v !== '' ? number_format((int) $v) : '—';

        return [
            Stat::make('Total Customers', $fmtInt($customer['this_year'] ?? null))
                ->description("Today: {$fmtInt($customer['today'] ?? null)} · This week: {$fmtInt($customer['this_week'] ?? null)}")
                ->descriptionIcon('heroicon-m-users')
                ->color($apiError ? 'gray' : 'primary'),

            Stat::make('Total Income', 'KES '.$fmt($income['total_income'] ?? null))
                ->description("Singles: KES {$fmt($income['single_games_income'] ?? null)} · Tournaments: KES {$fmt($income['tournaments_income'] ?? null)}")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($apiError ? 'gray' : 'success'),

            Stat::make("Today's Deposits", 'KES '.$fmt($todayIncome))
                ->description('Total deposited to house wallet today')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color($apiError ? 'gray' : 'info'),

            Stat::make('Games Played Today', $fmtInt($played['total'] ?? null))
                ->description("Singles: {$fmtInt($played['games'] ?? null)} · Tournaments: {$fmtInt($played['tournament'] ?? null)} · Jackpots: {$fmtInt($played['jackpots'] ?? null)}")
                ->descriptionIcon('heroicon-m-play-circle')
                ->color($apiError ? 'gray' : 'warning'),

            Stat::make('Purchases Today', 'KES '.$fmt($purchases['today'] ?? null))
                ->description("This week: KES {$fmt($purchases['week'] ?? null)}")
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'gray'),

            Stat::make('M-Pesa B2C Float', 'KES '.$fmt($b2cAmount))
                ->description('Withdrawal account balance')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color($apiError ? 'gray' : 'danger'),
        ];
    }
}
