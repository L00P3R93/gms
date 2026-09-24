<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Filament\Pages\FinanceReportPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Referral programme money for finance: bonuses earned and payouts this month
 * (`/finance/referrals`), and whether the referral shortcode (type
 * `referral_b2c` on the balance sheet) still covers every unspent referral
 * balance. When it does not, withdrawals will start failing at M-Pesa.
 * Cached for a minute and never polled.
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
     * The referral shortcode's balance. When KadiApi lists several `referral_b2c` accounts,
     * payouts are drawn from the utility account, so that one is used.
     *
     * @param  list<array<string, mixed>>  $accounts
     */
    public static function referralShortcodeBalance(array $accounts): ?float
    {
        $referralAccounts = collect($accounts)->filter(fn ($account): bool => is_array($account) && ($account['type'] ?? null) === 'referral_b2c');

        if ($referralAccounts->isEmpty()) {
            return null;
        }

        $utility = $referralAccounts->first(fn (array $account): bool => str_contains(strtolower((string) ($account['account'] ?? '')), 'utility'));

        return (float) ($utility['amount'] ?? $referralAccounts->sum('amount'));
    }

    protected function getStats(): array
    {
        $report = $this->loadFinanceReport('referrals', [
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ], self::CACHE_SECONDS);
        $cashAccounts = $this->loadFinanceReport('balance-sheet', cacheSeconds: self::CACHE_SECONDS)['assets']['cash']['accounts'] ?? [];
        $error = $this->financeApiError;

        $bonuses = $report['bonuses_earned'] ?? [];
        $payouts = $report['payouts'] ?? [];
        $position = $report['position'] ?? [];
        $openWithdrawals = $position['open_withdrawals'] ?? [];
        $unspent = (float) ($position['unspent_balances'] ?? 0);
        $shortcodeBalance = static::referralShortcodeBalance($cashAccounts);

        return [
            Stat::make('Bonuses Earned (Month)', Format::money($bonuses['total'] ?? 0))
                ->description(number_format((int) ($bonuses['count'] ?? 0)).' bonuses · Signup '.Format::money($bonuses['signup'] ?? 0).' · First deposit '.Format::money($bonuses['first_deposit'] ?? 0))
                ->descriptionIcon('heroicon-m-gift')
                ->color($error ? 'gray' : 'info'),

            Stat::make('Referral Payouts (Month)', Format::money($payouts['paid'] ?? 0))
                ->description('Paid is the expense · Pending '.Format::money($payouts['pending'] ?? 0).' · Failed '.Format::money($payouts['failed'] ?? 0))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($error ? 'gray' : 'primary'),

            $this->shortcodeStat($shortcodeBalance, $unspent, $error),

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

    protected function shortcodeStat(?float $shortcodeBalance, float $unspent, bool $error): Stat
    {
        if ($shortcodeBalance === null) {
            return Stat::make('Referral Shortcode 4151665', 'Not fetched')
                ->description('No referral_b2c balance on the balance sheet yet · owes '.Format::money($unspent))
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('gray');
        }

        $shortfall = $unspent - $shortcodeBalance;

        return Stat::make('Referral Shortcode 4151665', Format::money($shortcodeBalance))
            ->description($shortfall > 0
                ? 'Short by '.Format::money($shortfall).' against unspent balances — payouts will start failing'
                : 'Covers unspent balances of '.Format::money($unspent))
            ->descriptionIcon($shortfall > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
            ->color($error ? 'gray' : ($shortfall > 0 ? 'danger' : 'success'));
    }
}
