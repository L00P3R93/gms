<?php

use App\Enums\UserStatus;
use App\Filament\Pages\PromoCodesPage;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A promo code as KadiApi returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function promoCodePayload(array $overrides = []): array
{
    return [
        'id' => 3,
        'code' => 'LAUNCH-OCT',
        'promotion' => 'signup_bonus',
        'status' => 'active',
        'expires_at' => '2026-10-31T23:59:00+03:00',
        'max_redemptions' => 500,
        'signups' => 120,
        'redemptions' => 96,
        'remaining' => 404,
        'note' => 'October launch',
        'created_by' => 'api_key:2',
        'created_at' => '2026-09-26T09:00:00+03:00',
        'deactivated_at' => null,
        'deactivated_by' => null,
        ...$overrides,
    ];
}

function isPromoCodeListRequest(Request $request): bool
{
    return $request->method() === 'GET' && str_contains($request->url(), '/promo-codes');
}

function isPromoCodeCreateRequest(Request $request): bool
{
    return $request->method() === 'POST' && str_ends_with($request->url(), '/promo-codes');
}

function isPromoCodeDeactivateRequest(Request $request): bool
{
    return $request->method() === 'POST' && str_ends_with($request->url(), '/deactivate');
}

/**
 * Fake every KadiApi call the promo codes page makes. Write stubs go first because the
 * first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakePromoCodesApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/promo-codes*' => Http::response(['success' => true, 'data' => [
            'items' => [promoCodePayload()],
            'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 1, 'last_page' => 1],
        ]]),
    ]);
}

beforeEach(function (): void {
    Cache::flush();
    $this->travelTo('2026-09-26 10:00:00');

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
        'services.game_api.openssl_key' => 'a9378f354771d2bdf46c1fd1b5bcaf38',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['name' => 'Grace Admin', 'status' => UserStatus::Active->value]);
    $this->admin->assignRole('admin');

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');
});

it('lists active promo codes by default and passes the status filter to the API', function (): void {
    fakePromoCodesApi();
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->assertSee('LAUNCH-OCT')
        ->assertSee('96 / 500')
        ->assertSee('GMS (API key #2)')
        ->filterTable('status', 'expired');

    $statuses = Http::recorded(fn (Request $request): bool => isPromoCodeListRequest($request))
        ->map(function (array $pair): ?string {
            parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

            return $query['status'] ?? null;
        })
        ->unique()
        ->values()
        ->all();

    expect($statuses)->toBe(['active', 'expired']);
});

it('shows unlimited codes without a maximum', function (): void {
    fakePromoCodesApi(['*/promo-codes*' => Http::response(['success' => true, 'data' => [
        'items' => [promoCodePayload(['max_redemptions' => null, 'remaining' => null])],
        'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 1, 'last_page' => 1],
    ]])]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->assertSee('96 / ∞')
        ->assertSee('Unlimited');
});

it('shows an empty state instead of failing when KadiApi is down', function (): void {
    fakePromoCodesApi(['*/promo-codes*' => Http::response(['message' => 'Server Error'], 500)]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->assertSee('Promo codes unavailable');
});

it('creates a promo code with an idempotency key and logs the write', function (): void {
    fakePromoCodesApi(['*/promo-codes' => function (Request $request) {
        return $request->method() === 'POST'
            ? Http::response(['success' => true, 'data' => promoCodePayload(['id' => 9, 'code' => 'LAUNCH-NOV', 'signups' => 0, 'redemptions' => 0])], 201)
            : Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'last_page' => 1]]]);
    }]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->callAction('createPromoCode', data: [
            'code' => 'launch-nov',
            'expires_at' => '2026-11-30 23:59:00',
            'max_redemptions' => 500,
            'note' => 'November launch',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Promo code LAUNCH-NOV created');

    Http::assertSent(fn (Request $request): bool => isPromoCodeCreateRequest($request)
        && $request->data() === ['code' => 'LAUNCH-NOV', 'expires_at' => '2026-11-30 23:59', 'max_redemptions' => 500, 'note' => 'November launch']
        && $request->header('X-API-KEY')[0] === 'test-api-key'
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\\PromoCode')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->auditable_id)->toBe(9)
        ->and($log->event)->toBe('created')
        ->and($log->new_values['payload']['code'])->toBe('LAUNCH-NOV')
        ->and($log->new_values['status'])->toBe(201)
        ->and($log->new_values['response']['code'])->toBe('LAUNCH-NOV');
});

it('leaves out an empty maximum so the code is unlimited', function (): void {
    fakePromoCodesApi(['*/promo-codes' => fn (Request $request) => $request->method() === 'POST'
        ? Http::response(['success' => true, 'data' => promoCodePayload(['max_redemptions' => null])], 201)
        : Http::response(['success' => true, 'data' => ['items' => []]])]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->callAction('createPromoCode', data: ['code' => 'OPEN', 'expires_at' => '2026-11-30 23:59:00'])
        ->assertHasNoActionErrors();

    Http::assertSent(fn (Request $request): bool => isPromoCodeCreateRequest($request)
        && $request->data() === ['code' => 'OPEN', 'expires_at' => '2026-11-30 23:59']);
});

it('validates the create form before calling KadiApi', function (array $data, array $errors): void {
    fakePromoCodesApi();
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->callAction('createPromoCode', data: $data)
        ->assertHasActionErrors($errors);

    Http::assertNotSent(fn (Request $request): bool => isPromoCodeCreateRequest($request));
})->with([
    'code too short' => [['code' => 'ABC', 'expires_at' => '2026-11-30 23:59:00'], ['code' => 'min']],
    'code with spaces' => [['code' => 'LAUNCH OCT', 'expires_at' => '2026-11-30 23:59:00'], ['code' => 'regex']],
    'expiry in the past' => [['code' => 'LAUNCH', 'expires_at' => '2026-09-25 23:59:00'], ['expires_at']],
    'no expiry' => [['code' => 'LAUNCH'], ['expires_at' => 'required']],
    'zero maximum' => [['code' => 'LAUNCH', 'expires_at' => '2026-11-30 23:59:00', 'max_redemptions' => 0], ['max_redemptions' => 'min']],
]);

it('maps KadiApi 422 errors onto the form and uses a new idempotency key for the corrected submit', function (): void {
    fakePromoCodesApi(['*/promo-codes' => Http::sequence()
        ->push(['message' => 'That promo code already exists.', 'errors' => ['code' => ['That promo code already exists.']]], 422)
        ->push(['success' => true, 'data' => promoCodePayload(['code' => 'LAUNCH-OCT2'])], 201)
        ->whenEmpty(Http::response(['success' => true, 'data' => ['items' => []]])),
    ]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->mountAction('createPromoCode')
        ->fillForm(['code' => 'LAUNCH-OCT', 'expires_at' => '2026-10-31 23:59:00'])
        ->callMountedAction()
        ->assertHasActionErrors(['code' => 'That promo code already exists.'])
        ->assertActionMounted('createPromoCode')
        ->fillForm(['code' => 'LAUNCH-OCT2'])
        ->callMountedAction()
        ->assertNotified('Promo code LAUNCH-OCT2 created');

    $keys = Http::recorded(fn (Request $request): bool => isPromoCodeCreateRequest($request))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(2)
        ->and(AuditLog::query()->where('event', 'create_failed')->sole()->new_values['status'])->toBe(422);
});

it('keeps the create form open and reuses the idempotency key after a rate limit', function (): void {
    fakePromoCodesApi(['*/promo-codes' => Http::sequence()
        ->push(['message' => 'Too Many Attempts.'], 429)
        ->push(['success' => true, 'data' => promoCodePayload()], 201)
        ->whenEmpty(Http::response(['success' => true, 'data' => ['items' => []]])),
    ]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->mountAction('createPromoCode')
        ->fillForm(['code' => 'LAUNCH-OCT', 'expires_at' => '2026-10-31 23:59:00'])
        ->callMountedAction()
        ->assertNotified(Notification::make()->title('Could not create the promo code')->body('Rate limit reached. Please retry shortly.')->danger())
        ->assertActionMounted('createPromoCode')
        ->callMountedAction()
        ->assertNotified('Promo code LAUNCH-OCT created');

    $keys = Http::recorded(fn (Request $request): bool => isPromoCodeCreateRequest($request))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(1);
});

it('deactivates a code after the warning and logs the write', function (): void {
    fakePromoCodesApi(['*/promo-codes/*/deactivate' => Http::response(['success' => true, 'data' => promoCodePayload([
        'status' => 'deactivated',
        'deactivated_at' => '2026-09-26T10:00:00+03:00',
        'deactivated_by' => 'api_key:2',
    ])])]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->mountAction(TestAction::make('deactivate')->table('3'))
        ->assertActionMounted(TestAction::make('deactivate')->table('3'))
        ->assertMountedActionModalSee(PromoCodesPage::DEACTIVATE_WARNING)
        ->callMountedAction()
        ->assertNotified('Promo code LAUNCH-OCT deactivated');

    Http::assertSent(fn (Request $request): bool => isPromoCodeDeactivateRequest($request)
        && ! str_contains($request->url(), '/promo-codes/3/')
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\\PromoCode')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->auditable_id)->toBe(3)
        ->and($log->event)->toBe('deactivated')
        ->and($log->new_values['response']['status'])->toBe('deactivated');
});

it('names the GMS user who deactivated a code', function (): void {
    fakePromoCodesApi(['*/promo-codes*' => Http::response(['success' => true, 'data' => ['items' => [promoCodePayload([
        'status' => 'deactivated',
        'deactivated_at' => '2026-09-26T10:00:00+03:00',
        'deactivated_by' => 'api_key:2',
    ])]]])]);
    AuditLog::create(['user_id' => $this->admin->id, 'auditable_type' => 'KadiApi\\PromoCode', 'auditable_id' => 3, 'event' => 'deactivated', 'new_values' => []]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->filterTable('status', 'deactivated')
        ->assertSee('26 Sep 2026, 10:00 by Grace Admin')
        ->assertActionHidden(TestAction::make('deactivate')->table('3'));
});

it('shows the message and refreshes when the code is already deactivated', function (): void {
    fakePromoCodesApi(['*/promo-codes/*/deactivate' => Http::response(['success' => false, 'message' => 'Promo code is already deactivated'], 409)]);
    $this->actingAs($this->admin);

    Livewire::test(PromoCodesPage::class)
        ->callAction(TestAction::make('deactivate')->table('3'))
        ->assertNotified(Notification::make()->title('Promo code not deactivated')->body('Promo code is already deactivated')->warning())
        ->assertActionNotMounted(TestAction::make('deactivate')->table('3'));

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\\PromoCode')->sole();
    expect($log->event)->toBe('deactivate_failed')
        ->and($log->new_values['status'])->toBe(409)
        ->and($log->new_values['response']['message'])->toBe('Promo code is already deactivated');
});

it('lets managers view promo codes but not create or deactivate them', function (): void {
    fakePromoCodesApi();
    $this->actingAs($this->manager);

    Livewire::test(PromoCodesPage::class)
        ->assertSee('LAUNCH-OCT')
        ->assertActionHidden('createPromoCode')
        ->assertActionHidden(TestAction::make('deactivate')->table('3'));
});

it('forbids the page to users who cannot view customers', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->actingAs($user);

    get(PromoCodesPage::getUrl())->assertForbidden();
});
