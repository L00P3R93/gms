<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-only player activity: how many distinct players came back today, this
 * week, month and year, against everyone who has ever played.
 */
class PlayerEngagementWidget extends BaseWidget
{
    use LoadsFinanceReport;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '120s';

    protected ?string $heading = 'Player Engagement';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $error = false;

        try {
            $retention = Cache::remember('gms_retention_stats', 120, fn (): array => app(GameApiService::class)->getRetentionStats());
        } catch (\Throwable) {
            $error = true;
            $retention = [];
        }

        $total = (int) ($retention['total_players'] ?? 0);
        $share = fn (int $active): string => $total > 0 ? number_format($active / $total * 100, 1).'% of all players' : 'No players yet';

        $stat = fn (string $label, string $key, string $color): Stat => Stat::make($label, Format::compact($retention[$key] ?? 0))
            ->description($share((int) ($retention[$key] ?? 0)))
            ->descriptionIcon('heroicon-m-user-group')
            ->color($error ? 'gray' : $color);

        return [
            $stat('Active Today', 'today', 'success'),
            $stat('Active This Week', 'week', 'info'),
            $stat('Active This Month', 'month', 'primary'),
            Stat::make('All Players', Format::compact($total))
                ->description('Active this year: '.Format::compact($retention['year'] ?? 0))
                ->descriptionIcon('heroicon-m-users')
                ->color($error ? 'gray' : 'warning'),
        ];
    }
}
