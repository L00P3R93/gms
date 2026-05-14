<?php

namespace App\Filament\Widgets;

use App\Models\PlayedGame;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GameStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function getStats(): array
    {
        $singlesTotal = PlayedGame::whereIn('match_type', ['multiplayer2', 'multiplayer3', 'multiplayer4'])
            ->sum('amount');
        $singlesIncome = $singlesTotal * 0.10;

        $tournamentTotal = PlayedGame::where('match_type', 'TN')->sum('amount');
        $tournamentIncome = $tournamentTotal * 0.10;

        $jackpotTotal = PlayedGame::where('match_type', 'JP')->sum('amount');
        $jackpotIncome = $jackpotTotal * 0.10;

        $totalRobotGames = PlayedGame::where('match_type', 'robot_game')->count();
        $todayRobotGames = PlayedGame::where('match_type', 'robot_game')
            ->whereDate('time', today())->count();
        $weekRobotGames = PlayedGame::where('match_type', 'robot_game')
            ->whereBetween('time', [now()->startOfWeek(), now()->endOfWeek()])->count();

        return [
            Stat::make('Singles Income', 'KES '.number_format($singlesIncome, 2))
                ->description('All-time (10% house cut)')
                ->color('success'),

            Stat::make('Tournament Income', 'KES '.number_format($tournamentIncome, 2))
                ->description('All-time (10% house cut)')
                ->color('info'),

            Stat::make('Jackpot Income', 'KES '.number_format($jackpotIncome, 2))
                ->description('All-time (10% house cut)')
                ->color('warning'),

            Stat::make('Robot Games Total', number_format($totalRobotGames))
                ->description("Today: {$todayRobotGames} · This week: {$weekRobotGames}")
                ->color('gray'),
        ];
    }
}
