<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ExciseChargesReport;
use App\Filament\Pages\IncomeStatementReport;
use App\Filament\Pages\PromoCodesPage;
use App\Filament\Pages\ReconciliationReport;
use App\Filament\Pages\SignupBonusesReport;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * The `/finance/promotions` payload.
 *
 * @param  array<string, mixed>  $summary  Overrides for the summary block.
 * @return array<string, mixed>
 */
function promotionsReportPayload(array $summary = []): array
{
    return [
        'summary' => [
            'credits' => 96,
            'gross_amount' => 2020.8,
            'excise_amount' => 100.8,
            'net_amount' => 1920.0,
            'by_promotion' => ['signup_bonus' => ['credits' => 96, 'gross_amount' => 2020.8, 'excise_amount' => 100.8, 'net_amount' => 1920.0]],
            'budget_cap' => 10525.0,
            'signup_bonus_spent' => 2020.8,
            ...$summary,
        ],
        'items' => [[
            'id' => 7, 'granted_at' => '2026-10-02T10:15:00+03:00', 'customer_id' => 42, 'customer_name' => 'Jane Wanjiru',
            'promotion' => 'signup_bonus', 'promo_code' => 'LAUNCH-OCT', 'gross_amount' => 21.05, 'rate' => 0.05,
            'excise_amount' => 1.05, 'net_amount' => 20.0, 'status' => 'granted', 'ledger_entry_id' => 'uuid-7',
        ]],
        'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
    ];
}

/**
 * Fake the finance and promo code endpoints the promotion reports read. More specific
 * patterns go first because the first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakePromotionFinanceApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/finance/export/*' => Http::response("\xEF\xBB\xBFid,promo_code\n7,LAUNCH-OCT\n", 200, ['Content-Disposition' => 'attachment; filename="finance-promotions-2026-10-01-2026-10-31.csv"']),
        '*/finance/promotions*' => Http::response(['success' => true, 'data' => promotionsReportPayload()]),
        '*/finance/income-statement*' => Http::response(['success' => true, 'data' => [
            'revenue' => ['total' => 135.0],
            'expenses' => ['tracked' => true, 'total' => 40.0, 'by_category' => []],
            'referral_payouts' => 0.0,
            'promotions' => 21.05,
            'net_income' => 73.95,
            'series' => [['period' => '2026-09-20', 'total' => 135.0, 'expenses' => 40.0, 'referral_payouts' => 0.0, 'promotions' => 21.05, 'net_income' => 73.95]],
        ]]),
        '*/finance/excise-duty/charges*' => Http::response(['success' => true, 'data' => [
            'summary' => ['charges' => 2, 'gross_deposits' => 121.05, 'excise' => 6.05, 'by_status' => [], 'unremitted' => ['charges' => 2, 'excise' => 6.05]],
            'items' => [
                ['id' => 12, 'charged_at' => '2026-09-20T10:00:00+03:00', 'source' => 'deposit', 'deposit_id' => 71, 'promotion_credit_id' => null, 'trans_id' => 'SIA12345', 'customer_id' => 900, 'customer_name' => 'Alice Wanjiru', 'msisdn' => '2547****5678', 'gross_amount' => 100.0, 'rate' => 0.05, 'excise_amount' => 5.0, 'net_amount' => 95.0, 'status' => 'charged', 'remittance_id' => null, 'kra_reference' => null],
                ['id' => 13, 'charged_at' => '2026-09-20T11:00:00+03:00', 'source' => 'promotion', 'deposit_id' => null, 'promotion_credit_id' => 7, 'trans_id' => null, 'customer_id' => 42, 'customer_name' => 'Jane Wanjiru', 'msisdn' => null, 'gross_amount' => 21.05, 'rate' => 0.05, 'excise_amount' => 1.05, 'net_amount' => 20.0, 'status' => 'charged', 'remittance_id' => null, 'kra_reference' => null],
            ],
            'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 2, 'last_page' => 1],
        ]]),
        '*/finance/reconciliation*' => Http::response(['success' => true, 'data' => [
            'status' => 'pass',
            'counts' => ['pass' => 1, 'warn' => 0, 'fail' => 0],
            'checks' => [['key' => 'promotion_credits_ledger', 'title' => 'Promotion credits match the ledger', 'status' => 'pass', 'count' => 0, 'amount' => 0, 'detail' => '']],
        ]]),
        '*/finance/balance-sheet*' => Http::response(['success' => true, 'data' => ['house_wallet' => 450.0]]),
        '*/promo-codes*' => Http::response(['success' => true, 'data' => [
            'items' => [],
            'pagination' => ['page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2],
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

it('shows signup bonus costs, links customers and passes the code and customer filters to the API', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(SignupBonusesReport::class)
        ->assertSee('KES 2,020.80')
        ->assertSee('KES 100.80')
        ->assertSee('KES 1,920.00')
        ->assertSee('Signup bonus')
        ->assertSee('LAUNCH-OCT')
        ->assertSee(AccountResource::getUrl('view', ['record' => 42]))
        ->filterTable('promo_code', ['code' => 'launch-oct'])
        ->filterTable('customer', ['customer_id' => 42]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/finance/promotions?')
        && str_contains($request->url(), 'kind=LAUNCH-OCT')
        && str_contains($request->url(), 'customer_id=42'));
});

it('shows the budget bar when a cap is set', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(SignupBonusesReport::class)
        ->assertSee('Signup bonus budget')
        ->assertSee('19.2%')
        ->assertSee('KES 2,020.80 of KES 10,525.00 spent');
});

it('leaves out the budget bar when there is no cap', function (): void {
    fakePromotionFinanceApi(['*/finance/promotions*' => Http::response(['success' => true, 'data' => promotionsReportPayload(['budget_cap' => null])])]);
    $this->actingAs($this->admin);

    Livewire::test(SignupBonusesReport::class)
        ->assertSee('KES 2,020.80')
        ->assertDontSee('Signup bonus budget');
});

it('streams the promotions CSV through the GMS with its filters', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    $response = get(route('finance.export', ['report' => 'promotions', 'from' => '2026-10-01', 'to' => '2026-10-31', 'kind' => 'LAUNCH-OCT', 'customer_id' => 42]));

    $response->assertOk()->assertDownload('finance-promotions-2026-10-01-2026-10-31.csv');
    expect($response->headers->all())->not->toHaveKey('x-api-key');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/finance/export/promotions?')
        && str_contains($request->url(), 'kind=LAUNCH-OCT')
        && str_contains($request->url(), 'customer_id=42')
        && $request->header('X-API-KEY') === ['test-api-key']);
});

it('keeps non-finance users out of the signup bonus report and its CSV', function (): void {
    $this->actingAs($this->manager);

    get(SignupBonusesReport::getUrl())->assertForbidden();
    get(route('finance.export', ['report' => 'promotions']))->assertForbidden();
});

it('shows promotions as an expense on the income statement', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(IncomeStatementReport::class)
        ->assertSee('Promotions')
        ->assertSee('Signup bonuses (expense)')
        ->assertSee('KES 21.05')
        ->assertSee('Revenue less expenses, referral payouts and promotions');
});

it('labels excise charges by source', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(ExciseChargesReport::class)
        ->assertSee('Deposit')
        ->assertSee('Signup bonus')
        ->assertSee('SIA12345');
});

it('shows the promotion credits check with the other reconciliation controls', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(ReconciliationReport::class)
        ->assertSee('Promotion credits match the ledger');
});

it('warns finance users when the house wallet is low while codes are active', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->assertSee('House wallet running low')
        ->assertSee('The house wallet has KES 450.00 and active promo codes: 2.')
        ->assertActionVisible('bonusCosts');
});

it('does not warn when the house wallet covers the bonuses or no codes are active', function (string $scenario): void {
    fakePromotionFinanceApi(match ($scenario) {
        'covered' => ['*/finance/balance-sheet*' => Http::response(['success' => true, 'data' => ['house_wallet' => 1000.0]])],
        'no active codes' => ['*/promo-codes*' => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 1, 'total' => 0, 'last_page' => 1]]])],
    });
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->assertDontSee('House wallet running low');
})->with(['covered', 'no active codes']);

it('hides the house wallet warning and bonus costs from non-finance users', function (): void {
    fakePromotionFinanceApi();
    $this->actingAs($this->manager);

    Livewire::test(PromoCodesPage::class)
        ->assertDontSee('House wallet running low')
        ->assertActionHidden('bonusCosts');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/finance/balance-sheet'));
});
