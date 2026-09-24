<?php

use App\Enums\UserStatus;
use App\Filament\Pages\DepositsPage;
use App\Filament\Pages\LedgerReport;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

const HASHED_MSISDN = '2a9bf3a1c0e46a58d7c3b5e2f1d8a4b6c9e0f7a3d2b1c4e5f6a7b8c9d0e1f2a3';

/**
 * A `/finance/deposits` page as KadiApi returns it.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function depositsReport(array $items): array
{
    return [
        'meta' => ['period' => ['from' => '2026-09-24', 'to' => '2026-09-24'], 'warnings' => []],
        'summary' => ['payments' => count($items), 'amount' => 1500, 'by_kind' => [], 'by_status' => [], 'top_depositors' => []],
        'items' => $items,
        'pagination' => ['page' => 1, 'per_page' => 25, 'total' => count($items), 'last_page' => 1],
    ];
}

/**
 * @param  array<string, mixed>  $stubs
 */
function fakeDepositsApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/finance/export/*' => Http::response("id,amount\n7,500.00\n", 200, ['Content-Disposition' => 'attachment; filename="finance-deposits.csv"']),
        '*/finance/deposits*' => Http::response(['success' => true, 'data' => depositsReport([
            ['id' => 7, 'trans_id' => 'UIKEQ7VTP4', 'amount' => 500, 'kind' => 'wallet_deposit', 'status' => 'processed', 'customer_id' => 42, 'customer_name' => 'Wanjiru Kamau', 'msisdn' => HASHED_MSISDN, 'bill_ref_no' => 'KK-7W**98', 'created_at' => '2026-09-24T06:04:22Z'],
            ['id' => 8, 'trans_id' => 'UIKEQ8ABCD', 'amount' => 1000, 'kind' => 'unmatched', 'status' => 'unmatched', 'customer_id' => null, 'customer_name' => null, 'msisdn' => '2547****5678', 'bill_ref_no' => '0790**7280', 'created_at' => '2026-09-24T10:30:00+03:00'],
        ])]),
    ]);
}

beforeEach(function (): void {
    Cache::flush();
    $this->travelTo('2026-09-24 12:00:00');

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
        'services.game_api.openssl_key' => 'a9378f354771d2bdf46c1fd1b5bcaf38',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

it('lets support and admins view deposits but not agents', function (): void {
    fakeDepositsApi();

    $this->actingAs($this->manager);
    get(DepositsPage::getUrl())->assertOk();

    $admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
    get(DepositsPage::getUrl())->assertOk();

    $this->actingAs($this->agent);
    get(DepositsPage::getUrl())->assertForbidden();
});

it('shows today by default and forwards the status and customer filters to the API', function (): void {
    fakeDepositsApi(['*/customers/*' => Http::response(['data' => ['id' => 42, 'name' => 'Wanjiru Kamau', 'account_no' => 'KK-7WMK98TW']])]);
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->set('tableFilters.status.value', '4')
        ->set('tableFilters.customer_id.value', '42')
        ->assertSee('Customer: Wanjiru Kamau · KK-7WMK98TW');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/finance/deposits?')
            && $query['from'] === '2026-09-24' && $query['to'] === '2026-09-24'
            && ($query['status'] ?? null) === '4'
            && ($query['customer_id'] ?? null) === '42';
    });
});

it('shows a hashed payer number as hidden by M-Pesa and a masked one as sent', function (): void {
    fakeDepositsApi();
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->assertSee('Hidden by M-Pesa')
        ->assertSee(HASHED_MSISDN)
        ->assertSee('2547****5678');
});

it('links a matched deposit to the customer page and shows times in Nairobi time', function (): void {
    fakeDepositsApi();
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->assertSee(AccountResource::getUrl('view', ['record' => 42]))
        ->assertSee('24 Sep 2026, 09:04')
        ->assertSee('24 Sep 2026, 10:30');
});

it('opens a deposit with its excise split and resolution', function (): void {
    fakeDepositsApi(['*/deposits/*' => Http::response(['data' => [
        'id' => 8, 'trans_id' => 'UIKEQ8ABCD', 'trans_time' => '2026-09-24T07:30:00Z', 'amount' => 1000, 'name' => 'JOHN DOE',
        'short_code' => '4007279', 'status' => 2, 'excise_amount' => '50.00', 'net_amount' => '950.00',
        'customer' => ['id' => 42, 'name' => 'Wanjiru Kamau', 'account_no' => 'KK-7WMK98TW'],
        'resolution' => ['action' => 'assigned', 'note' => 'Typed her phone number', 'resolved_by' => 'api_key:3', 'resolved_at' => '2026-09-24T08:00:00Z'],
    ]])]);
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->mountAction(TestAction::make('view')->table('8'))
        ->assertMountedActionModalSee(['KES 950.00', 'KES 50.00', 'Wanjiru Kamau · KK-7WMK98TW', 'Processed', 'Typed her phone number', 'GMS (API key #3)', '24 Sep 2026, 11:00'])
        ->assertMountedActionModalSeeHtml(AccountResource::getUrl('view', ['record' => 42]));
});

it('opens a deposit in the documented shape with no excise charged', function (): void {
    fakeDepositsApi(['*/deposits/*' => Http::response(['data' => [
        'id' => 7, 'trans_id' => 'UIKEQ7VTP4', 'trans_amount' => 500, 'name' => 'JANE DOE', 'status' => 2,
        'excise_amount' => null, 'net_amount' => null, 'customer' => 'Wanjiru Kamau',
    ]])]);
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->mountAction(TestAction::make('view')->table('7'))
        ->assertMountedActionModalSee(['None charged', 'KES 500.00'])
        ->assertMountedActionModalDontSee('Resolution');
});

it('names the customer an unmatched deposit was assigned to', function (): void {
    fakeDepositsApi(['*/deposits/*' => Http::response(['data' => [
        'id' => 8, 'trans_id' => 'UIKEQ8ABCD', 'trans_amount' => 1000, 'name' => 'JOHN DOE', 'status' => 2, 'customer' => null,
        'resolution' => ['action' => 'assigned', 'customer_id' => 42, 'customer_name' => 'Wanjiru Kamau', 'note' => 'Typed her phone number', 'resolved_by' => 'api_key:3', 'resolved_at' => '2026-09-24T08:00:00Z'],
    ]])]);
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->mountAction(TestAction::make('view')->table('8'))
        ->assertMountedActionModalSee(['Wanjiru Kamau', 'Assigned'])
        ->assertMountedActionModalSeeHtml(AccountResource::getUrl('view', ['record' => 42]));
});

it('shows the API message when a deposit cannot be loaded', function (): void {
    fakeDepositsApi(['*/deposits/*' => Http::response(['message' => 'Deposit not found.'], 404)]);
    $this->actingAs($this->manager);

    Livewire::test(DepositsPage::class)
        ->mountAction(TestAction::make('view')->table('7'))
        ->assertNotified('Could not load the deposit')
        ->assertActionNotMounted(TestAction::make('view')->table('7'));
});

it('finds customers for the customer filter by name, account or phone', function (): void {
    fakeDepositsApi(['*/customers/search*' => Http::response([
        ['id' => 42, 'account_no' => 'KK-7WMK98TW', 'name' => 'Wanjiru Kamau', 'email' => 'w@example.com', 'balance' => 10],
    ])]);
    $this->actingAs($this->manager);

    expect(DepositsPage::searchCustomers('wanj'))->toBe([42 => 'Wanjiru Kamau · KK-7WMK98TW'])
        ->and(DepositsPage::searchCustomers('w'))->toBe([]);

    Http::assertSentCount(1);
});

it('streams the deposits CSV to support with the filters but no other finance export', function (): void {
    fakeDepositsApi();
    $this->actingAs($this->manager);

    get(route('finance.export', ['report' => 'deposits', 'from' => '2026-09-24', 'to' => '2026-09-24', 'status' => '0', 'customer_id' => 42]))
        ->assertOk()
        ->assertDownload('finance-deposits.csv');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/finance/export/deposits?')
        && str_contains($request->url(), 'status=0')
        && str_contains($request->url(), 'customer_id=42'));

    get(route('finance.export', ['report' => 'ledger']))->assertForbidden();
    get(LedgerReport::getUrl())->assertForbidden();

    $this->actingAs($this->agent);
    get(route('finance.export', ['report' => 'deposits']))->assertForbidden();
});
