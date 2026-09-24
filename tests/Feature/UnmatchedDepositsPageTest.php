<?php

use App\Enums\UserStatus;
use App\Filament\Pages\UnmatchedDepositsPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Widgets\UnmatchedDepositsWidget;
use App\Models\User;
use App\Support\DepositSuggestion;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * An unmatched deposit as KadiApi's queue returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function unmatchedDeposit(array $overrides = []): array
{
    return [
        'id' => 31,
        'trans_id' => 'UIKEQ7VTP4',
        'trans_time' => '2026-09-24T10:00:00+03:00',
        'amount' => 500.0,
        'bill_ref_no' => '0712345678',
        'msisdn' => '2547****5678',
        'name' => 'JOHN DOE',
        'status' => 'unmatched',
        'created_at' => '2026-09-24T10:00:05+03:00',
        'suggestions' => [],
        'resolution' => null,
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function unmatchedQueue(array $items, int $count = 3, float $amount = 1750.0): array
{
    return ['success' => true, 'data' => [
        'summary' => ['unmatched_count' => $count, 'unmatched_amount' => $amount],
        'items' => $items,
        'pagination' => ['page' => 1, 'per_page' => 50, 'total' => count($items), 'last_page' => 1],
    ]];
}

function suggestion(int $customerId, string $name, string $accountNo, array $matches, bool $ambiguous = false): array
{
    return ['customer_id' => $customerId, 'name' => $name, 'account_no' => $accountNo, 'phone_no' => '0712***678', 'match' => $matches[0], 'matches' => $matches, 'ambiguous' => $ambiguous];
}

/**
 * Answers the queue by the `status` it is asked for.
 *
 * @param  array<string, array<string, mixed>>  $byStatus
 */
function fakeUnmatchedApi(array $byStatus): void
{
    Http::preventStrayRequests();

    Http::fake(['*/deposits/unmatched*' => function (Request $request) use ($byStatus) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response($byStatus[$query['status'] ?? 'unmatched'] ?? unmatchedQueue([]));
    }]);
}

beforeEach(function (): void {
    Cache::flush();
    $this->travelTo('2026-09-25 12:00:00');

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');
});

it('lets support and admins open the queue but not agents', function (): void {
    fakeUnmatchedApi([]);

    $this->actingAs($this->manager);
    get(UnmatchedDepositsPage::getUrl())->assertOk();

    $agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $agent->assignRole('agent');
    $this->actingAs($agent);
    get(UnmatchedDepositsPage::getUrl())->assertForbidden();
    expect(UnmatchedDepositsWidget::canView())->toBeFalse();
});

it('lists unmatched deposits with the top suggestion and highlights those waiting over 24 hours', function (): void {
    fakeUnmatchedApi(['unmatched' => unmatchedQueue([
        unmatchedDeposit(['id' => 31, 'trans_time' => '20260923090000', 'bill_ref_no' => 'kk6aa8b1daaf392', 'suggestions' => [
            suggestion(42, 'Wanjiru Kamau', 'KK-6AA8B1DAAF392', ['account_no']),
            suggestion(43, 'Otieno Ouma', 'KK-OTHER', ['bill_ref_phone']),
        ]]),
        unmatchedDeposit(['id' => 32, 'trans_id' => 'UIKEQ8ABCD', 'trans_time' => '2026-09-25T10:00:00+03:00']),
    ])]);
    $this->actingAs($this->manager);

    Livewire::test(UnmatchedDepositsPage::class)
        ->assertSee(['UIKEQ7VTP4', 'kk6aa8b1daaf392', 'JOHN DOE', 'KES 500.00'])
        ->assertSee('Wanjiru Kamau · KK-6AA8B1DAAF392')
        ->assertSee('+1 more')
        ->assertSee('Account number')
        ->assertSee(AccountResource::getUrl('view', ['record' => 42]))
        ->assertSee('23 Sep 2026, 09:00')
        ->assertSee('Waiting over 24 hours')
        ->assertSee('No suggestion')
        ->assertDontSee('Credited to');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/deposits/unmatched?') && str_contains($request->url(), 'status=unmatched'));
});

it('labels every match kind, the paid-from-and-typed case, shared numbers and unknown kinds', function (): void {
    fakeUnmatchedApi(['unmatched' => unmatchedQueue([
        unmatchedDeposit(['suggestions' => [
            suggestion(42, 'Wanjiru Kamau', 'KK-1', ['payer_phone', 'bill_ref_phone'], ambiguous: true),
            suggestion(43, 'Otieno Ouma', 'KK-2', ['bill_ref_phone']),
            suggestion(44, 'Akinyi Achieng', 'KK-3', ['payer_phone']),
            suggestion(45, 'Kiprono Kip', 'KK-4', ['email_hint']),
        ]]),
    ])]);
    $this->actingAs($this->manager);

    Livewire::test(UnmatchedDepositsPage::class)
        ->assertSee("Paid from and typed this customer's phone")
        ->assertSee('Shared number')
        ->mountAction(TestAction::make('suggestions')->table('31'))
        ->assertMountedActionModalSee(['Otieno Ouma · KK-2', 'Typed this phone', 'Akinyi Achieng · KK-3', 'Paid from this phone', 'Kiprono Kip · KK-4', 'Email hint', 'belongs to more than one customer'])
        ->assertMountedActionModalSeeHtml([
            'fi-color-danger',
            'fi-color-warning',
            AccountResource::getUrl('view', ['record' => 45]),
        ]);

    expect(DepositSuggestion::badges(suggestion(45, 'Kiprono Kip', 'KK-4', ['email_hint'])))
        ->toBe([['label' => 'Email hint', 'color' => 'gray']]);
});

it('shows how assigned and refunded deposits were resolved', function (): void {
    fakeUnmatchedApi([
        'assigned' => unmatchedQueue([unmatchedDeposit(['status' => 'assigned', 'resolution' => [
            'action' => 'assigned', 'customer_id' => 42, 'customer_name' => 'Wanjiru Kamau', 'mpesa_reference' => null, 'note' => 'Typed her phone number as the account',
            'resolved_by' => 'api_key:3', 'resolved_at' => '2026-09-24T08:00:00Z',
        ]]), unmatchedDeposit(['id' => 33, 'status' => 'assigned', 'resolution' => [
            'action' => 'assigned', 'customer_id' => 44, 'mpesa_reference' => null, 'note' => 'Bill ref matches KK-6AA8B1DAAF392',
            'resolved_by' => 'command:deposits:match-unmatched', 'resolved_at' => '2026-09-24T08:05:00Z',
        ]])]),
        'refunded' => unmatchedQueue([unmatchedDeposit(['status' => 'refunded', 'resolution' => [
            'action' => 'refunded', 'customer_id' => null, 'mpesa_reference' => 'RKA1B2C3D4', 'note' => 'Payer asked for it back',
            'resolved_by' => null, 'resolved_at' => '2026-09-24T09:30:00+03:00',
        ]])]),
    ]);
    $this->actingAs($this->manager);

    Livewire::test(UnmatchedDepositsPage::class)
        ->call('setTab', 'assigned')
        ->assertSee(['Credited to', 'Wanjiru Kamau', 'Typed her phone number as the account', 'GMS (API key #3)', '24 Sep 2026, 11:00'])
        ->assertDontSee('Customer #42')
        ->assertSee(['Customer #44', 'Auto-match'])
        ->assertSee(AccountResource::getUrl('view', ['record' => 42]))
        ->assertDontSee('api_key:3')
        ->assertDontSee('Suggested customer')
        ->call('setTab', 'refunded')
        ->assertSee(['Reversal ref', 'RKA1B2C3D4', 'Payer asked for it back', '24 Sep 2026, 09:30'])
        ->assertDontSee('Customer #');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=assigned'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=refunded'));
});

it('ignores an unknown tab from the URL', function (): void {
    fakeUnmatchedApi([]);
    $this->actingAs($this->manager);

    Livewire::withQueryParams(['tab' => 'deleted'])
        ->test(UnmatchedDepositsPage::class)
        ->assertSet('tab', 'unmatched');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'status=deleted'));
});

it('shows the unmatched count and amount on the dashboard, linked to the queue, fetched once a minute at most', function (): void {
    fakeUnmatchedApi(['unmatched' => unmatchedQueue([], count: 3, amount: 1750.0)]);
    $this->actingAs($this->manager);

    Livewire::test(UnmatchedDepositsWidget::class)
        ->assertSee('3')
        ->assertSee('KES 1,750.00')
        ->assertSee(UnmatchedDepositsPage::getUrl());

    Livewire::test(UnmatchedDepositsWidget::class);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status=unmatched') && str_contains($request->url(), 'per_page=1'));
});

it('says so when KadiApi cannot be reached', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*/deposits/unmatched*' => Http::response(['message' => 'Server error'], 500)]);
    $this->actingAs($this->manager);

    Livewire::test(UnmatchedDepositsPage::class)->assertSee('Unmatched deposits unavailable');
    Livewire::test(UnmatchedDepositsWidget::class)->assertSee('KadiApi could not be reached');
});
