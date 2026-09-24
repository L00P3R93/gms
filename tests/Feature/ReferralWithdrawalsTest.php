<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ReferralWithdrawalDetailPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
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
 * A referral withdrawal as KadiApi returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function referralWithdrawalPayload(array $overrides = []): array
{
    return [
        'id' => 7,
        'customer_id' => 42,
        'amount' => 250.0,
        'phone_no' => '2547****5678',
        'status' => 'processing',
        'mpesa_receipt' => null,
        'result_code' => null,
        'result_desc' => null,
        'settled_by' => null,
        'settlement_note' => null,
        'completed_at' => null,
        'failed_at' => null,
        'created_at' => '2026-09-22T09:15:00+03:00',
        ...$overrides,
    ];
}

/**
 * Whether a recorded request is a `GET /referral-withdrawals` list call with exactly these query values.
 *
 * @param  array<string, string>  $expected
 */
function isReferralWithdrawalListQuery(Request $request, array $expected): bool
{
    if ($request->method() !== 'GET' || ! str_contains($request->url(), '/referral-withdrawals?')) {
        return false;
    }

    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return array_intersect_key($query, $expected) == $expected;
}

function isSettleRequest(Request $request): bool
{
    return $request->method() === 'POST' && str_ends_with($request->url(), '/settle');
}

/**
 * Fake every KadiApi call the referral withdrawal pages make. Settle stubs go first because
 * the first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeReferralWithdrawalsApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/referral-withdrawals/*' => Http::response(['success' => true, 'data' => referralWithdrawalPayload()]),
        '*/referral-withdrawals*' => Http::response([
            'data' => [referralWithdrawalPayload()],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 1],
        ]),
    ]);
}

beforeEach(function (): void {
    Cache::flush();

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

it('lists referral withdrawals and passes the filters to the API', function (): void {
    fakeReferralWithdrawalsApi();
    $this->actingAs($this->admin);

    Livewire::test(ReferralWithdrawalsPage::class)
        ->assertSee('KES 250.00')
        ->assertSee('2547****5678')
        ->filterTable('status', 'pending')
        ->filterTable('customer', ['customer_id' => 42]);

    Http::assertSent(fn (Request $request): bool => isReferralWithdrawalListQuery($request, [
        'status' => 'pending',
        'customer_id' => '42',
        'page' => '1',
        'per_page' => '50',
    ]));
});

it('shows an empty state instead of failing when KadiApi is down', function (): void {
    fakeReferralWithdrawalsApi(['*/referral-withdrawals*' => Http::response(['message' => 'Server Error'], 500)]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralWithdrawalsPage::class)
        ->assertSee('Referral withdrawals unavailable');
});

it('flags pending and processing withdrawals older than 24 hours as stuck', function (string $status, string $requestedAt, bool $isStuck): void {
    $this->travelTo('2026-09-24 12:00:00');

    expect(ReferralWithdrawalsPage::isStuck(referralWithdrawalPayload(['status' => $status, 'created_at' => $requestedAt])))->toBe($isStuck);
})->with([
    'processing for 25 hours' => ['processing', '2026-09-23T11:00:00+03:00', true],
    'pending for 25 hours' => ['pending', '2026-09-23T11:00:00+03:00', true],
    'processing for 2 hours' => ['processing', '2026-09-24T10:00:00+03:00', false],
    'completed long ago' => ['completed', '2026-08-01T10:00:00+03:00', false],
]);

it('shows a referral withdrawal', function (): void {
    fakeReferralWithdrawalsApi(['*/referral-withdrawals/*' => Http::response(['success' => true, 'data' => referralWithdrawalPayload([
        'status' => 'failed',
        'result_code' => 2001,
        'result_desc' => 'The initiator information is invalid.',
        'failed_at' => '2026-09-22T09:16:00+03:00',
    ])])]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->assertSee('KES 250.00')
        ->assertSee('The initiator information is invalid.')
        ->assertActionHidden('settle');
});

it('returns 404 for a withdrawal KadiApi does not know', function (): void {
    fakeReferralWithdrawalsApi(['*/referral-withdrawals/*' => Http::response(['success' => false, 'message' => 'Not found'], 404)]);
    $this->actingAs($this->admin);

    get(ReferralWithdrawalDetailPage::getUrl(['withdrawal' => 999]))->assertNotFound();
});

it('settles a withdrawal as completed with its receipt and logs the write', function (): void {
    $settled = referralWithdrawalPayload(['status' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'settled_by' => 'api_key:4', 'settlement_note' => 'Found on the statement — by Grace Admin']);
    fakeReferralWithdrawalsApi(['*/referral-withdrawals/*/settle' => Http::response(['success' => true, 'data' => $settled])]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->callAction('settle', data: ['outcome' => 'completed', 'mpesa_receipt' => 'rka1b2c3d4', 'note' => 'Found on the statement'])
        ->assertHasNoActionErrors()
        ->assertNotified(Notification::make()->title('Withdrawal marked completed')->body('Receipt RKA1B2C3D4 recorded.')->success())
        ->assertSee('Found on the statement — by Grace Admin')
        ->assertActionHidden('settle');

    Http::assertSent(fn (Request $request): bool => isSettleRequest($request)
        && $request->data() === ['outcome' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Found on the statement — by Grace Admin']
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\\ReferralWithdrawal')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->auditable_type)->toBe('KadiApi\\ReferralWithdrawal')
        ->and($log->auditable_id)->toBe(7)
        ->and($log->event)->toBe('settled')
        ->and($log->new_values['payload']['mpesa_receipt'])->toBe('RKA1B2C3D4')
        ->and($log->new_values['status'])->toBe(200)
        ->and($log->new_values['response']['status'])->toBe('completed');
});

it('settles a withdrawal as failed without a receipt once confirmed', function (): void {
    fakeReferralWithdrawalsApi(['*/referral-withdrawals/*/settle' => Http::response(['success' => true, 'data' => referralWithdrawalPayload(['status' => 'failed'])])]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->callAction('settle', data: ['outcome' => 'failed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Not on the statement', 'confirm_failed' => true])
        ->assertHasNoActionErrors()
        ->assertNotified(Notification::make()->title('Withdrawal marked failed')->body('KES 250.00 returned to the customer\'s referral wallet.')->success());

    Http::assertSent(fn (Request $request): bool => isSettleRequest($request)
        && $request->data() === ['outcome' => 'failed', 'note' => 'Not on the statement — by Grace Admin']);
});

it('settles a withdrawal from the list row', function (): void {
    fakeReferralWithdrawalsApi(['*/referral-withdrawals/*/settle' => Http::response(['success' => true, 'data' => referralWithdrawalPayload(['status' => 'completed'])])]);
    $this->actingAs($this->admin);

    Livewire::test(ReferralWithdrawalsPage::class)
        ->callAction(TestAction::make('settle')->table('7'), data: ['outcome' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Found on the statement'])
        ->assertHasNoActionErrors()
        ->assertNotified('Withdrawal marked completed');

    Http::assertSent(fn (Request $request): bool => isSettleRequest($request) && str_contains($request->url(), '/referral-withdrawals/'));
});

it('validates the settle form before calling KadiApi', function (array $data, array $errors): void {
    fakeReferralWithdrawalsApi();
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->callAction('settle', data: $data)
        ->assertHasActionErrors($errors);

    Http::assertNotSent(fn (Request $request): bool => isSettleRequest($request));
})->with([
    'completed without a receipt' => [['outcome' => 'completed', 'note' => 'Found it'], ['mpesa_receipt' => 'required']],
    'receipt too short' => [['outcome' => 'completed', 'mpesa_receipt' => 'RK1', 'note' => 'Found it'], ['mpesa_receipt' => 'regex']],
    'receipt with symbols' => [['outcome' => 'completed', 'mpesa_receipt' => 'RKA1-B2C3', 'note' => 'Found it'], ['mpesa_receipt' => 'regex']],
    'failed without confirming' => [['outcome' => 'failed', 'note' => 'Not on the statement'], ['confirm_failed' => 'accepted']],
    'note too short' => [['outcome' => 'failed', 'note' => 'no', 'confirm_failed' => true], ['note' => 'min']],
    'no outcome' => [['note' => 'Found it'], ['outcome' => 'required']],
]);

it('shows the message and refreshes when KadiApi answers 409', function (): void {
    $settledElsewhere = false;

    fakeReferralWithdrawalsApi([
        '*/referral-withdrawals/*/settle' => function () use (&$settledElsewhere) {
            $settledElsewhere = true;

            return Http::response(['success' => false, 'message' => 'Withdrawal is already completed.'], 409);
        },
        '*/referral-withdrawals/*' => function () use (&$settledElsewhere) {
            return Http::response(['success' => true, 'data' => $settledElsewhere
                ? referralWithdrawalPayload(['status' => 'completed', 'mpesa_receipt' => 'RKZ9Y8X7W6'])
                : referralWithdrawalPayload()]);
        },
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->callAction('settle', data: ['outcome' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Found it'])
        ->assertNotified(Notification::make()->title('Withdrawal not settled')->body('Withdrawal is already completed.')->warning())
        ->assertSee('RKZ9Y8X7W6')
        ->assertActionHidden('settle');

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\\ReferralWithdrawal')->sole();
    expect($log->event)->toBe('settle_failed')
        ->and($log->new_values['status'])->toBe(409)
        ->and($log->new_values['response']['message'])->toBe('Withdrawal is already completed.');
});

it('maps KadiApi 422 errors onto the form and uses a new idempotency key for the corrected submit', function (): void {
    fakeReferralWithdrawalsApi([
        '*/referral-withdrawals/*/settle' => Http::sequence()
            ->push(['message' => 'The given data was invalid.', 'errors' => ['mpesa_receipt' => ['This receipt does not match the statement format.']]], 422)
            ->push(['success' => true, 'data' => referralWithdrawalPayload(['status' => 'completed'])]),
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->mountAction('settle')
        ->fillForm(['outcome' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Found it'])
        ->callMountedAction()
        ->assertHasActionErrors(['mpesa_receipt' => 'This receipt does not match the statement format.'])
        ->assertActionMounted('settle')
        ->fillForm(['mpesa_receipt' => 'RKA1B2C3D5'])
        ->callMountedAction()
        ->assertNotified('Withdrawal marked completed');

    $keys = Http::recorded(fn (Request $request): bool => isSettleRequest($request))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(2);
});

it('reuses the idempotency key when a rate-limited settle is retried', function (): void {
    fakeReferralWithdrawalsApi([
        '*/referral-withdrawals/*/settle' => Http::sequence()
            ->push(['message' => 'Too Many Attempts.'], 429)
            ->push(['success' => true, 'data' => referralWithdrawalPayload(['status' => 'completed'])]),
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->mountAction('settle')
        ->fillForm(['outcome' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'note' => 'Found it'])
        ->callMountedAction()
        ->assertActionMounted('settle')
        ->callMountedAction()
        ->assertNotified('Withdrawal marked completed');

    $keys = Http::recorded(fn (Request $request): bool => isSettleRequest($request))
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(1);
});

it('lets managers view withdrawals but not settle them', function (): void {
    fakeReferralWithdrawalsApi();
    $this->actingAs($this->manager);

    Livewire::withQueryParams(['withdrawal' => 7])
        ->test(ReferralWithdrawalDetailPage::class)
        ->assertSee('KES 250.00')
        ->assertActionHidden('settle');

    Livewire::test(ReferralWithdrawalsPage::class)
        ->assertActionHidden(TestAction::make('settle')->table('7'));
});

it('keeps users who cannot view customers out of the withdrawal pages', function (): void {
    fakeReferralWithdrawalsApi();
    $this->actingAs(User::factory()->create(['status' => UserStatus::Active->value]));

    get(ReferralWithdrawalsPage::getUrl())->assertForbidden();
    get(ReferralWithdrawalDetailPage::getUrl(['withdrawal' => 7]))->assertForbidden();
});
