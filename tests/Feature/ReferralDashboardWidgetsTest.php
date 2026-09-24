<?php

use App\Enums\CompanyWithdrawStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserStatus;
use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Filament\Widgets\ReferralFinanceWidget;
use App\Filament\Widgets\ReferralProgrammeWidget;
use App\Filament\Widgets\ReferralReconciliationWidget;
use App\Filament\Widgets\TopReferrersWidget;
use App\Models\CompanyWithdraw;
use App\Models\MpesaAccountBalance;
use App\Models\Payee;
use App\Models\Payout;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $stubs
 */
function fakeReferralDashboardApi(array $stubs = []): void
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

/**
 * Store the B2C shortcode's balance the way the Safaricom balance callback does.
 */
function storeB2cBalance(float $utility): MpesaAccountBalance
{
    return MpesaAccountBalance::storeFromCallback('b2c', [
        'ResultParameters' => ['ResultParameter' => [
            ['Key' => 'AccountBalance', 'Value' => "Working Account|KES|0.00|0.00|0.00|0.00&Utility Account|KES|{$utility}|{$utility}|0.00|0.00"],
        ]],
    ]);
}

function makeCommittedPayout(float $amount, PayoutStatus $status): Payout
{
    $payee = Payee::create(['name' => 'Ops Vendor', 'phone' => '254700000000', 'designation' => 'Vendor', 'team' => 'Ops']);

    return Payout::create(['payee_id' => $payee->id, 'amount' => $amount, 'reason' => 'Hosting', 'status' => $status]);
}

it('shows referral bonuses and payouts for the current month', function (): void {
    $this->travelTo('2026-09-24 12:00:00');
    fakeReferralDashboardApi();
    storeB2cBalance(5000);
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('KES 150.00')
        ->assertSee('Pending KES 50.00')
        ->assertSee('Covers referral balances KES 800.00 + GMS payouts KES 0.00');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/finance/referrals?') && $query === ['from' => '2026-09-01', 'to' => '2026-09-24'];
    });
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/finance/balance-sheet'));
});

it('warns when the B2C balance cannot cover referral balances plus committed GMS payouts', function (): void {
    fakeReferralDashboardApi();
    storeB2cBalance(1000);
    makeCommittedPayout(300, PayoutStatus::Approved);
    makeCommittedPayout(200, PayoutStatus::Processing);
    makeCommittedPayout(5000, PayoutStatus::Pending);
    makeCommittedPayout(7000, PayoutStatus::Completed);
    CompanyWithdraw::create(['phone' => '254700000001', 'amount' => 100, 'user_id' => $this->admin->id, 'reason' => 'Ops', 'status' => CompanyWithdrawStatus::Processing->value]);
    Withdraw::create(['receiver_id' => 1, 'type' => WithdrawType::Holder->value, 'phone' => '254700000003', 'amount' => 50, 'status' => WithdrawStatus::Processing->value]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('KES 1,000.00')
        ->assertSee('Short by KES 450.00 against referral balances KES 800.00 + GMS payouts KES 650.00 — payouts will start failing');
});

it('flags a B2C balance older than two hours as stale', function (): void {
    fakeReferralDashboardApi();
    $this->travelTo('2026-09-24 09:00:00');
    storeB2cBalance(5000);
    $this->travelTo('2026-09-24 12:00:00');
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('updated 3 hours ago');
});

it('says when no B2C balance has been fetched yet', function (): void {
    fakeReferralDashboardApi();
    $this->actingAs($this->admin);

    Livewire::test(ReferralFinanceWidget::class)
        ->assertSee('Not fetched');
});

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
