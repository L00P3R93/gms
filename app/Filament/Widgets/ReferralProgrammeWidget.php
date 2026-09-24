<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ReferralsPage;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;

/**
 * Player referral programme at a glance, from `GET /stats/referrals`: how many
 * referrals, how far they got, and the bonuses paid. Shown to whoever can view
 * referrals. Cached for a minute and never polled, because the endpoint shares
 * KadiApi's 20/min limiter with the finance reports.
 */
class ReferralProgrammeWidget extends BaseWidget
{
    protected static ?int $sort = 24;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Player Referrals';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ReferralsPage::canAccess();
    }

    protected function getStats(): array
    {
        try {
            $stats = app(GameApiService::class)->getPlayerReferralProgrammeStats();
            $error = false;
        } catch (\Throwable $e) {
            Log::warning('Referral programme stats failed', ['error' => $e->getMessage()]);
            $stats = [];
            $error = true;
        }

        $referrals = $stats['referrals'] ?? [];
        $bonuses = $stats['bonuses'] ?? [];
        $total = (int) ($referrals['total'] ?? 0);

        return [
            Stat::make('Referrals', number_format($total))
                ->description('Today '.number_format((int) ($referrals['today'] ?? 0))
                    .' · Week '.number_format((int) ($referrals['this_week'] ?? 0))
                    .' · Month '.number_format((int) ($referrals['this_month'] ?? 0)))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($error ? 'gray' : 'primary')
                ->url(ReferralsPage::getUrl()),

            Stat::make('Verified · Deposited', number_format((int) ($referrals['verified'] ?? 0)).' · '.number_format((int) ($referrals['deposited'] ?? 0)))
                ->description($total > 0
                    ? round(100 * (int) ($referrals['deposited'] ?? 0) / $total).'% of referrals made a first deposit'
                    : 'No referrals yet')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($error ? 'gray' : 'success'),

            Stat::make('Bonuses Paid', Format::money($bonuses['total'] ?? 0))
                ->description('Signup '.Format::money($bonuses['signup'] ?? 0)
                    .' · First deposit '.Format::money($bonuses['first_deposit'] ?? 0)
                    .' · Month '.Format::money($bonuses['this_month'] ?? 0))
                ->descriptionIcon('heroicon-m-gift')
                ->color($error ? 'gray' : 'info'),

            Stat::make('Unspent Referral Balances', Format::money($stats['unspent_balance'] ?? 0))
                ->description(number_format((int) ($stats['referrers'] ?? 0)).' players have referred someone')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($error ? 'gray' : 'warning'),
        ];
    }
}
