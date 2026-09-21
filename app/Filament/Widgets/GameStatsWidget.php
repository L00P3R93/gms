<?php

namespace App\Filament\Widgets;

use App\Services\GameApiService;
use App\Support\Format;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class GameStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '300s';

    protected ?string $heading = 'Games Played Today Summary';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // return auth()->user()?->hasRole('super-admin') ?? false;
        return true;
    }

    protected function getStats(): array
    {
        $apiError = false;

        try {
            $stats = Cache::remember('gms_dashboard_stats', 120,
                fn () => app(GameApiService::class)->getDashboardStats()
            );
        } catch (\Throwable) {
            $stats = [];
        }

        if ($apiError) {
            Notification::make()
                ->title('Game API Unavailable')
                ->body('Could not connect to the wallet API. Stats shown may be incomplete.')
                ->warning()
                ->send();
        }

        $played = $stats['played'] ?? [];

        $singleGamesPlayed = $played['games'] ?? [];
        $_2PlayerSingleGamesPlayed = $singleGamesPlayed['2_players'] ?? null;
        $_3PlayerSingleGamesPlayed = $singleGamesPlayed['3_players'] ?? null;
        $_4PlayerSingleGamesPlayed = $singleGamesPlayed['4_players'] ?? null;
        $totalSingleGamesPlayed = $singleGamesPlayed['total'] ?? 0;

        $tournamentsPlayed = $played['tournament'] ?? [];
        $_3RoundsTournamentsPlayed = $tournamentsPlayed['3_rounds'] ?? null;
        $_4RoundsTournamentsPlayed = $tournamentsPlayed['4_rounds'] ?? null;
        $_5RoundsTournamentsPlayed = $tournamentsPlayed['5_rounds'] ?? null;
        $totalTournamentsPlayed = $tournamentsPlayed['total'] ?? 0;

        $jackpotsPlayed = $played['jackpots'] ?? [];
        $_13RoundsJackpotsPlayed = $jackpotsPlayed['13_rounds'] ?? null;
        $_17RoundsJackpotsPlayed = $jackpotsPlayed['17_rounds'] ?? null;
        $_21RoundsJackpotsPlayed = $jackpotsPlayed['21_rounds'] ?? null;
        $totalJackpotsPlayed = $jackpotsPlayed['total'] ?? 0;

        $fmtInt = fn ($v) => $v !== null && $v !== '' && $v !== 0 ? Format::formatNumber((int) $v) : '—';

        return [
            Stat::make('Single Games Played', $fmtInt($totalSingleGamesPlayed.' Games' ?? null))
                ->description("2P: {$fmtInt($_2PlayerSingleGamesPlayed ?? null)} · 3P: {$fmtInt($_3PlayerSingleGamesPlayed ?? null)} · 4P: {$fmtInt($_4PlayerSingleGamesPlayed ?? null)}")
                ->descriptionColor('primary')
                ->descriptionIcon(Heroicon::OutlinedPlayCircle)
                ->color($apiError ? 'gray' : 'primary'),

            Stat::make('Tournaments Played', $fmtInt($totalTournamentsPlayed.' Games' ?? null))
                ->description("3R: {$fmtInt($_3RoundsTournamentsPlayed ?? null)} · 4R: {$fmtInt($_4RoundsTournamentsPlayed ?? null)} · 5R: {$fmtInt($_5RoundsTournamentsPlayed ?? null)}")
                ->descriptionColor('success')
                ->descriptionIcon(Heroicon::OutlinedPlay)
                ->color($apiError ? 'gray' : 'success'),

            Stat::make('Jackpots Played', $fmtInt($totalJackpotsPlayed.' Games' ?? null))
                ->description("13R: {$fmtInt($_13RoundsJackpotsPlayed ?? null)} · 17R: {$fmtInt($_17RoundsJackpotsPlayed ?? null)} · 21R: {$fmtInt($_21RoundsJackpotsPlayed ?? null)}")
                ->descriptionColor('info')
                ->descriptionIcon(Heroicon::OutlinedPlayCircle)
                ->color($apiError ? 'gray' : 'info'),
        ];
    }
}
