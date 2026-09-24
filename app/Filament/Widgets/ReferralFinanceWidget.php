<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Enums\CompanyWithdrawStatus;
use App\Enums\PayoutStatus;
use App\Enums\WithdrawStatus;
use App\Filament\Pages\FinanceReportPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Models\CompanyWithdraw;
use App\Models\MpesaAccountBalance;
use App\Models\Payout;
use App\Models\Withdraw;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Referral programme money for finance: bonuses earned and payouts this month
 * (`/finance/referrals`), and whether the shortcode KadiApi pays referrals from
 * still covers what is owed out of it. That shortcode is the GMS's own B2C
 * shortcode, so its Utility balance comes from the local `b2c` balance row and
 * is compared against unspent referral balances plus the GMS payouts already
 * approved or sent from it. When it falls short, payouts start failing at
 * M-Pesa. Cached for a minute and never polled.
 */
class ReferralFinanceWidget extends BaseWidget
{
    use LoadsFinanceReport;

    public const CACHE_SECONDS = 60;

    protected static ?int $sort = 26;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Referral Programme Finance';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return FinanceReportPage::canAccess();
    }

    /**
     * Balances older than this are flagged, matching the M-Pesa balance widget.
     */
    public const STALE_AFTER_HOURS = 2;

    /**
     * GMS money approved or already sent from the B2C shortcode and not yet settled:
     * payouts approved or processing, and company and shareholder withdrawals processing.
     */
    public static function gmsCommittedPayouts(): float
    {
        return (float) Payout::query()->whereIn('status', [PayoutStatus::Approved, PayoutStatus::Processing])->sum('amount')
            + (float) CompanyWithdraw::query()->where('status', CompanyWithdrawStatus::Processing)->sum('amount')
            + (float) Withdraw::query()->where('status', WithdrawStatus::Processing)->sum('amount');
    }

    protected function getStats(): array
    {
        $report = $this->loadFinanceReport('referrals', [
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ], self::CACHE_SECONDS);
        $error = $this->financeApiError;

        $bonuses = $report['bonuses_earned'] ?? [];
        $payouts = $report['payouts'] ?? [];
        $position = $report['position'] ?? [];
        $openWithdrawals = $position['open_withdrawals'] ?? [];
        $unspent = (float) ($position['unspent_balances'] ?? 0);

        return [
            Stat::make('Bonuses Earned (Month)', Format::money($bonuses['total'] ?? 0))
                ->description(number_format((int) ($bonuses['count'] ?? 0)).' bonuses · Signup '.Format::money($bonuses['signup'] ?? 0).' · First deposit '.Format::money($bonuses['first_deposit'] ?? 0))
                ->descriptionIcon('heroicon-m-gift')
                ->color($error ? 'gray' : 'info'),

            Stat::make('Referral Payouts (Month)', Format::money($payouts['paid'] ?? 0))
                ->description('Paid is the expense · Pending '.Format::money($payouts['pending'] ?? 0).' · Failed '.Format::money($payouts['failed'] ?? 0))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($error ? 'gray' : 'primary'),

            $this->shortcodeStat(MpesaAccountBalance::latestOfType('b2c')->first(), $unspent, static::gmsCommittedPayouts(), $error),

            Stat::make('Open Withdrawals', number_format((int) ($openWithdrawals['count'] ?? 0)))
                ->description(Format::money($openWithdrawals['amount'] ?? 0).' pending or processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color($error ? 'gray' : ((int) ($openWithdrawals['count'] ?? 0) > 0 ? 'warning' : 'success'))
                ->url(ReferralWithdrawalsPage::canAccess() ? ReferralWithdrawalsPage::getUrlForStatus('processing') : null),

            Stat::make('Lifetime', Format::money($position['lifetime_bonuses'] ?? 0))
                ->description('Bonuses earned · '.Format::money($position['lifetime_paid_out'] ?? 0).' paid out')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('gray'),
        ];
    }

    protected function shortcodeStat(?MpesaAccountBalance $balance, float $unspent, float $gmsCommitted, bool $error): Stat
    {
        $label = 'B2C Shortcode '.config('mpesa.b2c.short_code');
        $owed = $unspent + $gmsCommitted;
        $owedBreakdown = 'referral balances '.Format::money($unspent).' + GMS payouts '.Format::money($gmsCommitted);

        if ($balance === null) {
            return Stat::make($label, 'Not fetched')
                ->description('No B2C balance yet (mpesa:fetch-balances) · owes '.$owedBreakdown)
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('gray');
        }

        $available = (float) $balance->utility_account_balance;
        $shortfall = $owed - $available;
        $isStale = $balance->fetched_at->lt(now()->subHours(self::STALE_AFTER_HOURS));
        $updated = ' · updated '.$balance->fetched_at->diffForHumans();

        return Stat::make($label, Format::money($available))
            ->description(($shortfall > 0
                ? 'Short by '.Format::money($shortfall).' against '.$owedBreakdown.' — payouts will start failing'
                : 'Covers '.$owedBreakdown).$updated)
            ->descriptionIcon($shortfall > 0 ? 'heroicon-m-exclamation-triangle' : ($isStale ? 'heroicon-m-clock' : 'heroicon-m-check-circle'))
            ->color(match (true) {
                $shortfall > 0 => 'danger',
                $isStale || $error => 'warning',
                default => 'success',
            });
    }
}
