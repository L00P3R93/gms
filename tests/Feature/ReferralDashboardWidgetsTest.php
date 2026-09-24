<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Filament\Widgets\ReferralFinanceWidget;
use App\Filament\Widgets\ReferralProgrammeWidget;
use App\Filament\Widgets\ReferralReconciliationWidget;
use App\Filament\Widgets\TopReferrersWidget;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * @param  list<array<string, mixed>>  $cashAccounts
 */
function fakeReferralDashboardApi(array $cashAccounts = [], array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/stats/referrals' => Http::response(['success' => true, 'data' => [
            'referrals' => ['total' => 120, 'verified' => 90, 'deposited' => 60, 'today' => 4, 'this_week' => 20, 'this_month' => 45],
            'bonuses' => ['total' => 1500.0, 'signup' => 900.0, 'first_deposit' => 600.0, 'this_month' => 450.0],
            'unspent_balance' => 800.0,
            'referrers' => 35,
            'top_referrers' => [['customer_id' => 42, 'name' => 'Jane Doe', 'referrals' => 12, 'earned' => 150.0]],
        ]]),
        '*/finance/referrals*' => Http::response(['success' => true, 'data' => [
            'bonuses_earned' => ['signup' => 90.0, 'first_deposit' => 60.0, 'total' => 150.0, 'count' => 15],
            'payouts' => ['paid' => 100.0, 'pending' => 50.0, 'failed' => 0.0],
            'position' => ['unspent_balances' => 800.0, 'open_withdrawals' => ['count' => 1, 'amount' => 50.0], 'lifetime_bonuses' => 1500.0, 'lifetime_paid_out' => 650.0],
        ]]),
        '*/finance/balance-sheet*' => Http::response(['success' => true, 'data' => [
            'assets' => ['cash' => ['accounts' => $cashAccounts]],
        ]]),
        '*/finance/reconciliation*' => Http::response(['success' => true, 'data' => [
            'status' => 'warn',
            'checks' => [
                ['key' => 'unmatched_deposits', 'title' => 'Deposits from unknown customers', 'status' => 'warn', 'count' => 2, 'amount' => 275.0],
                ['key' => 'referral_bonuses_unverified', 'title' => 'Referral bonuses for unverified referrals', 'status' => 'pass', 'count' => 0, 'amount' => 0.0],
                ['key' => 'stuck_referral_withdrawals', 'title' => 'Stuck referral withdrawals', 'status' => 'warn', 'count' => 3, 'amount' => 350.0],
            ],
        ]]),
    ]);
}

beforeEach(function (): void {
    Cache::flush();

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('admin');

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');
});

it('shows the referral programme stats and fetches them once a minute at most', function (): void {
    fakeReferralDashboardApi();
    $this->actingAs($this->manager);

    Livewire::test(ReferralProgrammeWidget::class)
        ->assertSee('120')
        ->assertSee('50% of referrals made a first deposit')
        ->assertSee('KES 1,500.00')
        ->assertSee('KES 800.00')
        ->assertSee('35 players have referred someone');

    Livewire::test(ReferralProgrammeWidget::class);

    Http::assertSentCount(1);
});

it('lists the top referrers with links to their customer pages', function (): void {
    fakeReferralDashboardApi();
    $this->actingAs($this->manager);

    Livewire::test(TopReferrersWidget::class)
        ->assertSee('Jane Doe')
        ->assertSee('KES 150.00')
        ->assertSee(ReferralWithdrawalsPage::customerUrl(42));
});

it('shows referral bonuses and payouts for the current month', function (): void {
    $this->travelTo('2026-09-24 12:00:00');
    fakeReferralDashboardApi([['type' => 'referral_b2c', 'account' => 'Utility Account', 'amount' => 5000.0]]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('KES 150.00')
        ->assertSee('Pending KES 50.00')
        ->assertSee('Covers unspent balances of KES 800.00');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/finance/referrals?') && $query === ['from' => '2026-09-01', 'to' => '2026-09-24'];
    });
});

it('warns when the referral shortcode holds less than the unspent balances', function (): void {
    fakeReferralDashboardApi([['type' => 'referral_b2c', 'account' => 'Utility Account', 'amount' => 500.0]]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('KES 500.00')
        ->assertSee('Short by KES 300.00 against unspent balances — payouts will start failing');
});

it('says when the referral shortcode balance has not been fetched', function (): void {
    fakeReferralDashboardApi([['type' => 'b2c', 'account' => 'Utility Account', 'amount' => 9000.0]]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('Not fetched');
});

it('reads the referral shortcode balance from its utility account', function (array $accounts, ?float $balance): void {
    expect(ReferralFinanceWidget::referralShortcodeBalance($accounts))->toBe($balance);
})->with([
    'utility and working accounts' => [[
        ['type' => 'referral_b2c', 'account' => 'Working Account', 'amount' => 100.0],
        ['type' => 'referral_b2c', 'account' => 'Utility Account', 'amount' => 700.0],
        ['type' => 'b2c', 'account' => 'Utility Account', 'amount' => 9000.0],
    ], 700.0],
    'one unnamed account' => [[['type' => 'referral_b2c', 'account' => 'Organization', 'amount' => 250.0]], 250.0],
    'none' => [[['type' => 'b2c', 'account' => 'Utility Account', 'amount' => 9000.0]], null],
]);

it('shows only the referral reconciliation checks and links stuck withdrawals to the list', function (): void {
    fakeReferralDashboardApi();
    $this->actingAs($this->admin);

    Livewire::test(ReferralReconciliationWidget::class)
        ->assertSee('Stuck referral withdrawals')
        ->assertSee('3 items · KES 350.00')
        ->assertSee('Referral bonuses for unverified referrals')
        ->assertDontSee('Deposits from unknown customers')
        ->assertSee(ReferralWithdrawalsPage::getUrlForStatus('processing'), escape: false);
});

it('opens the withdrawals list filtered by the status in the link', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*/referral-withdrawals*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 0]])]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['filters' => ['status' => ['value' => 'processing']]])
        ->test(ReferralWithdrawalsPage::class);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=processing'));
});

it('shows the programme widgets to referral viewers and the finance widgets to finance only', function (): void {
    $this->actingAs($this->manager);
    expect(ReferralProgrammeWidget::canView())->toBeTrue()
        ->and(TopReferrersWidget::canView())->toBeTrue()
        ->and(ReferralFinanceWidget::canView())->toBeFalse()
        ->and(ReferralReconciliationWidget::canView())->toBeFalse();

    $this->actingAs($this->admin);
    expect(ReferralFinanceWidget::canView())->toBeTrue()
        ->and(ReferralReconciliationWidget::canView())->toBeTrue();

    $this->actingAs(User::factory()->create(['status' => UserStatus::Active->value]));
    expect(ReferralProgrammeWidget::canView())->toBeFalse();
});
