<?php

namespace App\Filament\Widgets;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Notifications\Notification;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 4;

    protected ?string $heading = 'Overall Summary';

    public static function canView(): bool
    {
        return true;
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

        /*
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

        */

        if ($apiError) {
            Notification::make()
                ->title('Game API Unavailable')
                ->body('Could not connect to the wallet API. Stats shown may be incomplete.')
                ->warning()
                ->send();
        }

        $customer = $stats['customer'];
        $income = $stats['income'];
        $totalIncome = $income['games']['total'] + $income['tournaments']['total'] + $income['jackpots']['total'];
        $played = $stats['played'];
        $purchases = $stats['purchases'];

        $fmt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber((int) $v) : '—';
        $fmtInt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber((int) $v) : '—';

        return [
            Stat::make('Total Customers', $fmtInt($customer['this_year'].'Customers' ?? null))
                ->description("Today: {$fmtInt($customer['today'] ?? null)} · week: {$fmtInt($customer['this_week'] ?? null)} · month: {$fmtInt($customer['this_month'] ?? null)}")
                ->descriptionIcon('heroicon-m-users')
                ->color($apiError ? 'gray' : 'primary'),

            Stat::make('Games Played Today', $fmtInt($played['total'].'Games' ?? null))
                ->description("S: {$fmtInt($played['games']['total'] ?? null)} · T: {$fmtInt($played['tournament']['total'] ?? null)} · J: {$fmtInt($played['jackpots']['total'] ?? null)}")
                ->descriptionIcon('heroicon-m-play-circle')
                ->color($apiError ? 'gray' : 'warning'),

            Stat::make('Total Income', 'KES '.$fmt($totalIncome ?? null))
                ->description("S: {$fmt($income['games']['total'] ?? null)} · T: {$fmt($income['tournaments']['total'] ?? null)}· J: {$fmt($income['jackpots']['total'] ?? null)}")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($apiError ? 'gray' : 'success'),

            Stat::make('Total Purchases', 'KES '.$fmt($purchases['total'] ?? null))
                ->description('Total Purchases all time.')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'info'),
        ];
    }
}
