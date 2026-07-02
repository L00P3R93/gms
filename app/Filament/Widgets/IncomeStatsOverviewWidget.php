<?php

namespace App\Filament\Widgets;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class IncomeStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected ?string $heading = 'Todays Income Breakdown Summary';

    protected int|string|array $columnSpan = 3;

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

        if ($apiError) {
            Notification::make()
                ->title('Game API Unavailable')
                ->body('Could not connect to the wallet API. Stats shown may be incomplete.')
                ->warning()
                ->send();
        }

        $income = $stats['income'];
        $singleGamesIncome = $income['games'];
        $totalSingleGamesIncome = $singleGamesIncome['total'];
        $_2PlayerGamesIncome = $singleGamesIncome['2_players'];
        $_3PlayerGamesIncome = $singleGamesIncome['3_players'];
        $_4PlayerGamesIncome = $singleGamesIncome['4_players'];

        $tournamentsIncome = $income['tournaments'];
        $totalTournamentsIncome = $tournamentsIncome['total'];
        $_3RoundsTournamentsIncome = $tournamentsIncome['3_rounds'];
        $_4RoundsTournamentsIncome = $tournamentsIncome['4_rounds'];
        $_5RoundsTournamentsIncome = $tournamentsIncome['5_rounds'];

        $jackpotsIncome = $income['jackpots'];
        $totalJackpotsIncome = $jackpotsIncome['total'];
        $_13RoundsJackpotsIncome = $jackpotsIncome['13_rounds'];
        $_17RoundsJackpotsIncome = $jackpotsIncome['17_rounds'];
        $_21RoundsJackpotsIncome = $jackpotsIncome['21_rounds'];

        $fmt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber((int) $v) : '—';
        $fmtInt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber((int) $v) : '—';

        return [
            Stat::make('Single Games Today', 'KES '.$fmt($totalSingleGamesIncome ?? null))
                ->description("2P: KES {$fmt($_2PlayerGamesIncome ?? null)} · 3P: KES {$fmt($_3PlayerGamesIncome ?? null)} · 4P: KES {$fmt($_4PlayerGamesIncome ?? null)}")
                ->descriptionColor('primary')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($apiError ? 'gray' : 'primary'),

            Stat::make('Tournaments Today', 'KES '.$fmt($totalTournamentsIncome ?? null))
                ->description("3R: KES {$fmt($_3RoundsTournamentsIncome ?? null)} · 4R: KES {$fmt($_4RoundsTournamentsIncome ?? null)} · 5R: KES {$fmt($_5RoundsTournamentsIncome ?? null)}")
                ->descriptionColor('success')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($apiError ? 'gray' : 'success'),

            Stat::make('Jackpots Today', 'KES '.$fmt($totalJackpotsIncome ?? null))
                ->description("13R: KES {$fmt($_13RoundsJackpotsIncome ?? null)} · 17R: KES {$fmt($_17RoundsJackpotsIncome ?? null)} · 21R: KES {$fmt($_21RoundsJackpotsIncome ?? null)}")
                ->descriptionColor('info')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($apiError ? 'gray' : 'info'),
        ];
    }
}
