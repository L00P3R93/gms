<?php

namespace App\Filament\Widgets;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PurchasesStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected ?string $heading = 'Customer Purchase Summary';

    protected int|string|array $columnSpan = 4;

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

        $purchases = $stats['purchases'] ?? [];

        $fmt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber($v) : '—';
        $fmtInt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber($v) : '—';

        return [
            Stat::make('Purchases', 'KES '.$fmt($purchases['year'] ?? null))
                ->description('Total Purchases this year.')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'yellow'),

            Stat::make('Purchases', 'KES '.$fmt($purchases['month'] ?? null))
                ->description('Total Purchases this month.')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'indigo'),

            Stat::make('Purchases', 'KES '.$fmt($purchases['week'] ?? null))
                ->description('Total Purchases this week.')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'green'),

            Stat::make('Purchases', 'KES '.$fmt($purchases['today'] ?? null))
                ->description('Total Purchases today.')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($apiError ? 'gray' : 'blue'),
        ];
    }
}
