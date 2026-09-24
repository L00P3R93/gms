<?php

use App\Enums\UserStatus;
use App\Filament\Pages\UnmatchedDepositsPage;
use App\Filament\Widgets\UnmatchedDepositsWidget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GameApiService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The queue with one unmatched KES 500 deposit (id 31) and the given suggestions.
 *
 * @param  list<array<string, mixed>>  $suggestions
 * @return array<string, mixed>
 */
function resolveQueue(array $suggestions): array
{
    return ['success' => true, 'data' => [
        'summary' => ['unmatched_count' => 1, 'unmatched_amount' => 500.0],
        'items' => [[
            'id' => 31, 'trans_id' => 'UIKEQ7VTP4', 'trans_time' => '2026-09-24T10:00:00+03:00', 'amount' => 500.0,
            'bill_ref_no' => '0712345678', 'msisdn' => '2547****5678', 'name' => 'JOHN DOE', 'status' => 'unmatched',
            'created_at' => '2026-09-24T10:00:05+03:00', 'suggestions' => $suggestions, 'resolution' => null,
        ]],
        'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 1, 'last_page' => 1],
    ]];
}

/**
 * @param  list<string>  $matches
 * @return array<string, mixed>
 */
function resolveSuggestion(int $customerId, string $name, array $matches, bool $ambiguous = false): array
{
    return ['customer_id' => $customerId, 'name' => $name, 'account_no' => 'KK-'.$customerId, 'phone_no' => '0712***678', 'match' => $matches[0], 'matches' => $matches, 'ambiguous' => $ambiguous];
}

/**
 * Fake KadiApi: the queue, customer lookups, and the given assign/refund responses.
 *
 * @param  list<array<string, mixed>>  $suggestions
 */
function fakeResolveApi(array $suggestions, mixed $assign = null, mixed $refund = null): void
{
    Http::preventStrayRequests();

    Http::fake([
        '*/deposits/unmatched*' => Http::response(resolveQueue($suggestions)),
        '*/deposits/*/assign' => $assign ?? Http::response(['data' => ['id' => 31, 'status' => 2, 'resolution' => ['action' => 'assigned', 'customer_id' => 42]]]),
        '*/deposits/*/refund' => $refund ?? Http::response(['data' => ['id' => 31, 'status' => 4, 'resolution' => ['action' => 'refunded', 'mpesa_reference' => 'RKA1B2C3D4']]]),
        '*/customers/search*' => Http::response([['id' => 77, 'account_no' => 'KK-77', 'name' => 'Chebet Rono', 'email' => 'c@example.com', 'balance' => 0]]),
        // Ids are encrypted with a random IV, so every customer lookup answers with the same customer.
        '*/customers/*' => Http::response(['data' => ['id' => 42, 'name' => 'Wanjiru Kamau', 'account_no' => 'KK-42', 'phone_no' => '254712345678', 'created_at' => '2026-05-04T22:30:00.000000Z']]),
    ]);
}

function isAssignRequest(Request $request): bool
{
    return $request->method() === 'POST' && str_ends_with($request->url(), '/assign');
}

function isRefundRequest(Request $request): bool
{
    return $request->method() === 'POST' && str_ends_with($request->url(), '/refund');
}

function queueAsAdmin(): Testable
{
    return Livewire::test(UnmatchedDepositsPage::class);
}

beforeEach(function (): void {
    Cache::flush();
    $this->travelTo('2026-09-25 12:00:00');

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
        'services.game_api.openssl_key' => 'a9378f354771d2bdf46c1fd1b5bcaf38',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['name' => 'Amina Finance', 'status' => UserStatus::Active->value]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('assigns a deposit to a suggested customer with a signed note and a fresh idempotency key', function (): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])]);
    Cache::put(GameApiService::UNMATCHED_SUMMARY_CACHE_KEY, ['unmatched_count' => 1], 60);

    queueAsAdmin()
        ->mountAction(TestAction::make('assign')->table('31'))
        ->fillForm(['suggested_customer_id' => 42])
        ->assertMountedActionModalSee([
            'Wanjiru Kamau', 'Account KK-42', 'Phone ****5678', 'Joined 05 May 2026',
            "KES 500.00 will be credited to Wanjiru Kamau's wallet. 5% excise duty applies. This cannot be undone from the GMS.",
        ])
        ->fillForm(['note' => 'Bill ref is her account number'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Deposit assigned')
        ->assertDispatched(UnmatchedDepositsWidget::REFRESH_EVENT);

    Http::assertSent(fn (Request $request): bool => isAssignRequest($request)
        && $request->data() === ['customer_id' => 42, 'note' => 'Bill ref is her account number — by Amina Finance']
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));

    $log = AuditLog::where('auditable_type', 'KadiApi\\Deposit')->sole();
    expect($log->event)->toBe('assigned')
        ->and($log->auditable_id)->toBe(31)
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->new_values['payload']['customer_id'])->toBe(42)
        ->and($log->new_values['status'])->toBe(200)
        ->and(Cache::has(GameApiService::UNMATCHED_SUMMARY_CACHE_KEY))->toBeFalse();
});

it('assigns a deposit to a customer found by search', function (): void {
    fakeResolveApi([]);

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: [
            'suggested_customer_id' => 'other',
            'searched_customer_id' => 77,
            'note' => 'Payer called support with his account',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Deposit assigned');

    Http::assertSent(fn (Request $request): bool => isAssignRequest($request) && $request->data()['customer_id'] === 77);
});

it('makes the admin confirm they opened every customer sharing the number', function (): void {
    fakeResolveApi([
        resolveSuggestion(42, 'Wanjiru Kamau', ['payer_phone', 'bill_ref_phone'], ambiguous: true),
        resolveSuggestion(43, 'Otieno Ouma', ['payer_phone'], ambiguous: true),
    ]);

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'Her phone, confirmed on a call'])
        ->assertHasFormErrors(['confirm_shared_number' => 'accepted']);

    Http::assertNotSent(fn (Request $request): bool => isAssignRequest($request));

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'Her phone, confirmed on a call', 'confirm_shared_number' => true])
        ->assertHasNoFormErrors()
        ->assertNotified('Deposit assigned');
});

it('requires a note of 3 to 255 characters', function (): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])]);

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'ok'])
        ->assertHasFormErrors(['note' => 'min']);

    Http::assertNotSent(fn (Request $request): bool => isAssignRequest($request));
});

it('closes and refreshes when KadiApi says the deposit is already resolved or missing', function (int $status, string $title): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])], assign: Http::response(['message' => 'KadiApi says no.'], $status));

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'Bill ref is her account number'])
        ->assertHasNoFormErrors()
        ->assertNotified($title)
        ->assertActionNotMounted(TestAction::make('assign')->table('31'))
        ->assertDispatched(UnmatchedDepositsWidget::REFRESH_EVENT);

    $log = AuditLog::where('auditable_type', 'KadiApi\\Deposit')->sole();
    expect($log->event)->toBe('assign_failed')
        ->and($log->new_values['status'])->toBe($status)
        ->and($log->new_values['response']['message'])->toBe('KadiApi says no.');
})->with([
    'already resolved' => [409, 'Deposit already resolved'],
    'not found' => [404, 'Deposit or customer not found'],
]);

it('puts KadiApi validation errors on the fields and uses a new key for the corrected request', function (): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])], assign: Http::sequence()
        ->push(['message' => 'The given data was invalid.', 'errors' => ['note' => ['The note may not contain links.']]], 422)
        ->push(['data' => ['id' => 31, 'status' => 2]]));

    $page = queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'See http://example.com'])
        ->assertHasFormErrors(['note' => 'The note may not contain links.'])
        ->assertActionMounted(TestAction::make('assign')->table('31'));

    $page->fillForm(['note' => 'Bill ref is her account number'])->callMountedAction()->assertNotified('Deposit assigned');

    $keys = collect(Http::recorded())->filter(fn (array $pair): bool => isAssignRequest($pair[0]))->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])->values();
    expect($keys)->toHaveCount(2)->and($keys[0])->not->toBe($keys[1]);
});

it('keeps the modal open and replays the same key after a rate limit', function (): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])], assign: Http::sequence()
        ->push(['message' => 'Too Many Attempts.'], 429)
        ->push(['data' => ['id' => 31, 'status' => 2]]));

    queueAsAdmin()
        ->callAction(TestAction::make('assign')->table('31'), data: ['suggested_customer_id' => 42, 'note' => 'Bill ref is her account number'])
        ->assertNotified('Could not assign the deposit')
        ->assertActionMounted(TestAction::make('assign')->table('31'))
        ->callMountedAction()
        ->assertNotified('Deposit assigned');

    $keys = collect(Http::recorded())->filter(fn (array $pair): bool => isAssignRequest($pair[0]))->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])->values();
    expect($keys)->toHaveCount(2)->and($keys[0])->toBe($keys[1]);
});

it('records a refund with the reversal reference in capitals', function (): void {
    fakeResolveApi([]);

    queueAsAdmin()
        ->mountAction(TestAction::make('refund')->table('31'))
        ->assertMountedActionModalSee('Reverse the payment on the Kizuka (4007279) M-Pesa portal first. This only records it; no money is sent.')
        ->fillForm(['mpesa_reference' => ' rka1b2c3d4 ', 'note' => 'Payer asked for it back'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Refund recorded');

    Http::assertSent(fn (Request $request): bool => isRefundRequest($request)
        && $request->data() === ['mpesa_reference' => 'RKA1B2C3D4', 'note' => 'Payer asked for it back — by Amina Finance']);

    expect(AuditLog::where('auditable_type', 'KadiApi\\Deposit')->sole()->event)->toBe('refunded');
});

it('rejects a malformed reversal reference before calling KadiApi', function (): void {
    fakeResolveApi([]);

    queueAsAdmin()
        ->callAction(TestAction::make('refund')->table('31'), data: ['mpesa_reference' => 'RK-1', 'note' => 'Payer asked for it back'])
        ->assertHasFormErrors(['mpesa_reference' => 'regex']);

    Http::assertNotSent(fn (Request $request): bool => isRefundRequest($request));
});

it('keeps a refund open on the reference field when the reference is already used', function (array $body, string $error): void {
    fakeResolveApi([], refund: Http::response(['data' => ['id' => 31], ...$body], 409));

    queueAsAdmin()
        ->callAction(TestAction::make('refund')->table('31'), data: ['mpesa_reference' => 'RKA1B2C3D4', 'note' => 'Payer asked for it back'])
        ->assertHasFormErrors(['mpesa_reference' => $error])
        ->assertActionMounted(TestAction::make('refund')->table('31'));
})->with([
    'as KadiApi sends it' => [['success' => false, 'code' => 'reference_used', 'message' => 'That M-Pesa reference is already on another refund', 'errors' => ['mpesa_reference' => ['That M-Pesa reference is already on another refund']]], 'That M-Pesa reference is already on another refund'],
    'code without errors' => [['success' => false, 'code' => 'reference_used', 'message' => 'Conflict'], 'Conflict'],
]);

it('closes a refund when the deposit was already resolved', function (array $body): void {
    fakeResolveApi([], refund: Http::response(['data' => ['id' => 31], ...$body], 409));

    queueAsAdmin()
        ->callAction(TestAction::make('refund')->table('31'), data: ['mpesa_reference' => 'RKA1B2C3D4', 'note' => 'Payer asked for it back'])
        ->assertHasNoFormErrors()
        ->assertNotified('Deposit already resolved')
        ->assertActionNotMounted(TestAction::make('refund')->table('31'));
})->with([
    'as KadiApi sends it' => [['success' => false, 'code' => 'already_resolved', 'message' => 'Only unmatched deposits can be refunded']],
    // The code decides, never the wording: a message mentioning the reference changes nothing.
    'reworded message' => [['success' => false, 'code' => 'already_resolved', 'message' => 'This deposit and its reference are already resolved']],
]);

it('hides assign and refund from support, who can still view the queue', function (): void {
    fakeResolveApi([resolveSuggestion(42, 'Wanjiru Kamau', ['account_no'])]);
    $manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $manager->assignRole('manager');
    $this->actingAs($manager);

    Livewire::test(UnmatchedDepositsPage::class)
        ->assertActionVisible(TestAction::make('suggestions')->table('31'))
        ->assertActionHidden(TestAction::make('assign')->table('31'))
        ->assertActionHidden(TestAction::make('refund')->table('31'));
});

it('shows the GMS user who resolved a deposit instead of the API key', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*/deposits/unmatched*' => Http::response(['data' => [
        'summary' => ['unmatched_count' => 0, 'unmatched_amount' => 0],
        'items' => [
            ['id' => 31, 'trans_id' => 'UIKEQ7VTP4', 'amount' => 500, 'status' => 'assigned', 'resolution' => ['action' => 'assigned', 'customer_id' => 42, 'note' => 'n', 'resolved_by' => 'api_key:3', 'resolved_at' => '2026-09-24T08:00:00Z']],
            ['id' => 32, 'trans_id' => 'UIKEQ8ABCD', 'amount' => 700, 'status' => 'assigned', 'resolution' => ['action' => 'assigned', 'customer_id' => 43, 'note' => 'n', 'resolved_by' => 'api_key:3', 'resolved_at' => '2026-09-24T08:05:00Z']],
        ],
        'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 2, 'last_page' => 1],
    ]])]);
    AuditLog::create(['user_id' => $this->admin->id, 'auditable_type' => 'KadiApi\\Deposit', 'auditable_id' => 31, 'event' => 'assigned', 'new_values' => []]);

    Livewire::withQueryParams(['tab' => 'assigned'])
        ->test(UnmatchedDepositsPage::class)
        ->assertSee('Amina Finance (GMS)')
        ->assertSee('GMS (API key #3)');
});
