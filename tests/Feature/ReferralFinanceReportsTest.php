<?php

use App\Enums\UserStatus;
use App\Filament\Pages\BalanceSheetReport;
use App\Filament\Pages\CashFlowReport;
use App\Filament\Pages\IncomeStatementReport;
use App\Filament\Pages\ReferralBonusesReport;
use App\Filament\Pages\ReferralPayoutsReport;
use App\Filament\Pages\ReferralWithdrawalDetailPage;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * Fake the finance endpoints the referral reports and statements read. More specific
 * patterns go first because the first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeReferralFinanceApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    $pagination = ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1];

    Http::fake($stubs + [
        '*/finance/export/*' => Http::response("\xEF\xBB\xBFid,amount\n31,10.00\n", 200, ['Content-Disposition' => 'attachment; filename="finance-referral-bonuses-2026-09-01-2026-09-24.csv"']),
        '*/finance/referrals/bonuses*' => Http::response(['success' => true, 'data' => [
            'summary' => ['by_milestone' => ['signup' => ['bonuses' => 9, 'amount' => 90.0], 'first_deposit' => ['bonuses' => 6, 'amount' => 60.0]], 'total' => 150.0],
            'items' => [['id' => 31, 'paid_at' => '2026-09-20T10:00:00+03:00', 'referral_id' => 7, 'customer_id' => 42, 'referrer_name' => 'Jane Doe', 'referred_id' => 97, 'referred_name' => 'Achieng Wafula', 'milestone' => 'signup', 'amount' => 10.0, 'ledger_entry_id' => 'uuid']],
            'pagination' => $pagination,
        ]]),
        '*/finance/referrals/withdrawals*' => Http::response(['success' => true, 'data' => [
            'summary' => ['by_status' => ['completed' => ['withdrawals' => 4, 'amount' => 400.0], 'processing' => ['withdrawals' => 1, 'amount' => 50.0], 'failed' => ['withdrawals' => 1, 'amount' => 100.0]]],
            'items' => [['id' => 5, 'requested_at' => '2026-09-21T08:00:00+03:00', 'customer_id' => 42, 'customer_name' => 'Jane Doe', 'phone_no' => '2547****5678', 'amount' => 100.0, 'status' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'result_code' => '0', 'result_desc' => 'Accepted', 'completed_at' => '2026-09-21T08:01:00+03:00', 'failed_at' => null]],
            'pagination' => $pagination,
        ]]),
        '*/finance/income-statement*' => Http::response(['success' => true, 'data' => [
            'revenue' => ['total' => 1000.0],
            'expenses' => ['tracked' => true, 'total' => 200.0, 'by_category' => []],
            'referral_payouts' => 350.0,
            'net_income' => 450.0,
            'memo' => ['referral_bonuses_earned' => 520.0],
            'series' => [['period' => '2026-09-01', 'total' => 1000.0, 'expenses' => 200.0, 'referral_payouts' => 350.0, 'net_income' => 450.0]],
        ]]),
        '*/finance/cash-flow*' => Http::response(['success' => true, 'data' => [
            'totals' => [
                'cash_in' => ['total' => 5000.0],
                'cash_out' => ['paid' => 1000.0, 'pending' => 0.0, 'failed' => 0.0],
                'referral_payouts' => ['paid' => 275.0, 'pending' => 60.0, 'failed' => 15.0],
                'net_cash' => 3725.0,
            ],
            'series' => [],
        ]]),
        '*/finance/balance-sheet*' => Http::response(['success' => true, 'data' => [
            'assets' => ['cash' => ['accounts' => [['type' => 'referral_b2c', 'account' => 'Utility Account', 'amount' => 4200.0, 'as_of' => '2026-09-24T11:00:00+03:00']], 'total' => 4200.0], 'total' => 4200.0],
            'liabilities' => ['customer_wallets' => 2000.0, 'referral_wallets' => 812.5, 'total' => 2812.5],
            'house_wallet' => 300.0,
            'difference' => 1087.5,
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

it('shows referral bonuses by milestone and passes the filters to the API', function (): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(ReferralBonusesReport::class)
        ->assertSee('KES 150.00')
        ->assertSee('9 referrals verified')
        ->assertSee('Jane Doe')
        ->assertSee('Achieng Wafula')
        ->set('tableFilters.type.value', 'first_deposit')
        ->set('tableFilters.customer.customer_id', '42');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/finance/referrals/bonuses?')
            && ($query['type'] ?? null) === 'first_deposit'
            && ($query['customer_id'] ?? null) === '42'
            && isset($query['from'], $query['to'], $query['page'], $query['per_page']);
    });
});

it('shows referral payouts by status and opens each withdrawal', function (): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(ReferralPayoutsReport::class)
        ->assertSee('KES 400.00')
        ->assertSee('1 pending or processing')
        ->assertSee('RKA1B2C3D4')
        ->assertSee(ReferralWithdrawalDetailPage::getUrl(['withdrawal' => 5]))
        ->set('tableFilters.status.value', 'failed');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/finance/referrals/withdrawals?') && str_contains($request->url(), 'status=failed'));
});

it('keeps non-finance users out of the referral finance reports', function (string $page): void {
    $this->actingAs($this->manager);

    get($page::getUrl())->assertForbidden();
})->with([
    'bonuses' => [ReferralBonusesReport::class],
    'payouts' => [ReferralPayoutsReport::class],
]);

it('streams the referral CSV exports through the GMS with their filters', function (string $report): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    $response = get(route('finance.export', ['report' => $report, 'from' => '2026-09-01', 'to' => '2026-09-24', 'status' => 'completed', 'customer_id' => 42]));

    $response->assertOk()->assertDownload();
    expect($response->headers->all())->not->toHaveKey('x-api-key');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/finance/export/{$report}?")
        && str_contains($request->url(), 'customer_id=42')
        && str_contains($request->url(), 'from=2026-09-01')
        && $request->header('X-API-KEY') === ['test-api-key']);
})->with(['referral-bonuses', 'referral-withdrawals']);

it('refuses the referral CSV exports to non-finance users', function (): void {
    $this->actingAs($this->manager);

    get(route('finance.export', ['report' => 'referral-withdrawals']))->assertForbidden();
});

it('shows referral payouts as an expense and bonuses as a memo on the income statement', function (): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(IncomeStatementReport::class)
        ->assertSee('Referral payouts (expense)')
        ->assertSee('KES 350.00')
        ->assertSee('Bonuses earned (memo, not an expense)')
        ->assertSee('KES 520.00')
        ->assertSee('Revenue less expenses and referral payouts');
});

it('shows referral payouts from the referral shortcode on the cash flow', function (): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(CashFlowReport::class)
        ->assertSee('Referral payouts (4151665)')
        ->assertSee('KES 275.00')
        ->assertSee('KES 60.00')
        ->assertSee('KES 15.00');
});

it('shows the referral wallets liability and the referral shortcode on the balance sheet', function (): void {
    fakeReferralFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(BalanceSheetReport::class)
        ->assertSee('Referral Wallets')
        ->assertSee('KES 812.50')
        ->assertSee('REFERRAL_B2C')
        ->assertSee('KES 4,200.00');
});
