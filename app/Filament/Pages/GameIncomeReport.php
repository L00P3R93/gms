<?php

namespace App\Filament\Pages;

use App\Services\GameApiService;
use App\Traits\SuperAdminAccess;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class GameIncomeReport extends Page
{
    use SuperAdminAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Income Report';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.game-income-report';

    public string $period = 'today';

    public ?string $customStart = null;

    public ?string $customEnd = null;

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public function getPeriodOptions(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year' => 'This Year',
            'all_time' => 'All Time',
            'custom' => 'Custom Range',
        ];
    }

    protected function getDateRange(): array
    {
        return match ($this->period) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'yesterday' => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
            'this_week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'last_week' => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_month' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'this_year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'all_time' => [null, null],
            'custom' => [$this->customStart ?? now()->startOfMonth()->toDateString(), $this->customEnd ?? now()->toDateString()],
            default => [today()->toDateString(), today()->toDateString()],
        };
    }

    public function getReportData(): array
    {
        [$start, $end] = $this->getDateRange();
        $gameApi = app(GameApiService::class);

        $startDate = $start ?? '2000-01-01';
        $endDate = $end ?? now()->toDateString();
        $cacheKey = "income_report_{$this->period}_{$startDate}_{$endDate}";

        try {
            $data = Cache::remember($cacheKey, 120, function () use ($gameApi, $startDate, $endDate): array {
                return [
                    'singles' => $gameApi->getGameIncomeBreakdown($startDate, $endDate),
                    'tournaments' => $gameApi->getCompetitionIncomeBreakdown(1, $startDate, $endDate),
                    'jackpots' => $gameApi->getCompetitionIncomeBreakdown(2, $startDate, $endDate),
                ];
            });

            $this->apiError = false;
        } catch (\Throwable) {
            $this->apiError = true;
            $data = ['singles' => [], 'tournaments' => [], 'jackpots' => []];
        }

        $singlesIncome = (float) collect($data['singles'])->sum('total_income');
        $tournamentIncome = (float) collect($data['tournaments'])->sum('total_income');
        $jackpotIncome = (float) collect($data['jackpots'])->sum('total_income');

        return [
            'singles' => $data['singles'],
            'tournaments' => $data['tournaments'],
            'jackpots' => $data['jackpots'],
            'totals' => [
                'singles_income' => $singlesIncome,
                'tournament_income' => $tournamentIncome,
                'jackpot_income' => $jackpotIncome,
                'grand_total' => $singlesIncome + $tournamentIncome + $jackpotIncome,
            ],
        ];
    }
}
