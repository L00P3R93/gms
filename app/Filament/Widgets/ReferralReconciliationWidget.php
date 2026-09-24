<?php

namespace App\Filament\Widgets;

use App\Concerns\LoadsFinanceReport;
use App\Filament\Pages\FinanceReportPage;
use App\Filament\Pages\ReconciliationReport;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Support\Format;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The referral controls from `GET /finance/reconciliation`, one card each with
 * its pass / warn / fail status. Stuck referral withdrawals link to the
 * withdrawals list so finance can check and settle them. Shares the cached
 * reconciliation call with the platform position widget; never polled.
 */
class ReferralReconciliationWidget extends BaseWidget
{
    use LoadsFinanceReport;

    /**
     * Checks shown when KadiApi reports them, in this order. Any other check whose key
     * mentions referrals is shown after them.
     *
     * @var list<string>
     */
    public const CHECKS = [
        'stuck_referral_withdrawals',
        'failed_referral_withdrawals_not_reversed',
        'referral_bonuses_unverified',
        'referral_wallet_drift',
    ];

    protected static ?int $sort = 27;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Referral Reconciliation';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return FinanceReportPage::canAccess();
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return list<array<string, mixed>>
     */
    public static function referralChecks(array $checks): array
    {
        return collect($checks)
            ->filter(fn ($check): bool => is_array($check) && str_contains((string) ($check['key'] ?? ''), 'referral'))
            ->sortBy(fn (array $check): int => array_search($check['key'], self::CHECKS, true) === false ? PHP_INT_MAX : array_search($check['key'], self::CHECKS, true))
            ->values()
            ->all();
    }

    protected function getStats(): array
    {
        $checks = static::referralChecks($this->loadFinanceReport('reconciliation', cacheSeconds: ReferralFinanceWidget::CACHE_SECONDS)['checks'] ?? []);

        if ($checks === []) {
            return [
                Stat::make('Referral checks', $this->financeApiError ? 'Unavailable' : 'Not reported')
                    ->description($this->financeApiError ? 'KadiApi could not be reached' : 'KadiApi returned no referral checks')
                    ->color('gray'),
            ];
        }

        return array_map(fn (array $check): Stat => $this->checkStat($check), $checks);
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function checkStat(array $check): Stat
    {
        $status = (string) ($check['status'] ?? 'unknown');
        $count = (int) ($check['count'] ?? 0);

        $url = match (true) {
            $check['key'] === 'stuck_referral_withdrawals' && ReferralWithdrawalsPage::canAccess() => ReferralWithdrawalsPage::getUrlForStatus('processing'),
            ReconciliationReport::canAccess() => ReconciliationReport::getUrl(),
            default => null,
        };

        return Stat::make($check['title'] ?? ucfirst(str_replace('_', ' ', (string) $check['key'])), strtoupper($status))
            ->description($status === 'pass'
                ? 'Nothing to act on'
                : number_format($count).' item'.($count === 1 ? '' : 's').' · '.Format::money($check['amount'] ?? 0))
            ->descriptionIcon(match ($status) {
                'pass' => 'heroicon-m-check-circle',
                'warn' => 'heroicon-m-exclamation-triangle',
                'fail' => 'heroicon-m-x-circle',
                default => 'heroicon-m-question-mark-circle',
            })
            ->color(match ($status) {
                'pass' => 'success',
                'warn' => 'warning',
                'fail' => 'danger',
                default => 'gray',
            })
            ->extraAttributes(['title' => (string) ($check['detail'] ?? '')])
            ->url($url);
    }
}
