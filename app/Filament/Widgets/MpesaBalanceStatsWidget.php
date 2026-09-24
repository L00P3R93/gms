<?php

namespace App\Filament\Widgets;

use App\Models\MpesaAccountBalance;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MpesaBalanceStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '3600s';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 19;

    protected ?string $heading = 'M-Pesa Account Balances';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        // C2B is no longer fetched: it is the same shortcode as B2C.
        $b2c = MpesaAccountBalance::latestOfType('b2c')->first();

        $stale = now()->subHours(2);

        return [
            Stat::make('B2C Utility Account', 'KES '.number_format($b2c?->utility_account_balance ?? 0, 2))
                ->description($this->formatLastUpdated($b2c?->fetched_at))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($this->getBalanceColor($b2c, $stale)),
        ];
    }

    private function formatLastUpdated(?CarbonInterface $date): string
    {
        if (! $date) {
            return 'No data yet';
        }

        return 'Updated '.$date->diffForHumans();
    }

    private function getBalanceColor(?MpesaAccountBalance $balance, CarbonInterface $stale): string
    {
        if (! $balance || $balance->fetched_at->isBefore($stale)) {
            return 'warning';
        }

        return 'success';
    }
}
