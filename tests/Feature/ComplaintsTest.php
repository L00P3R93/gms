<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ComplaintDetailPage;
use App\Filament\Pages\ComplaintsPage;
use App\Filament\Widgets\ComplaintsStatsWidget;
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
 * A complaint as the wallet API's ComplaintResource returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function complaintPayload(array $overrides = []): array
{
    return [
        'id' => 3,
        'complaint_id' => '9b1c2f0e-6a57-4f7e-9d0a-1f3c5b8e2a41',
        'customer_id' => 42,
        'subject_type' => 'game',
        'game_wallet_id' => 1051,
        'competition_wallet_id' => null,
        'reason' => 'The winner kept playing after I disconnected',
        'description' => null,
        'status' => 'pending_dispute',
        'disputed_amount' => 190.0,
        'held_amount' => 150.0,
        'shortfall_amount' => 40.0,
        'refunded_amount' => 0.0,
        'house_cuts_reversed' => 0.0,
        'released_amount' => 0.0,
        'filed_by' => 'api_key:4',
        'resolution_note' => null,
        'closed_by' => null,
        'closed_at' => null,
        'created_at' => '2026-09-23T14:05:00+03:00',
        'disputed_transactions' => [[
            'id' => 5, 'transaction_type' => 'game_transaction', 'transaction_id' => 88213,
            'customer_id' => 57, 'source_wallet_type' => 'wallet', 'source_wallet_id' => 57,
            'amount' => 190.0, 'held_amount' => 150.0, 'shortfall_amount' => 40.0, 'balance' => 150.0,
            'status' => 'held',
        ]],
        'refunds' => [],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function resolvedComplaintPayload(): array
{
    return complaintPayload([
        'status' => 'resolved',
        'refunded_amount' => 200.0,
        'house_cuts_reversed' => 20.0,
        'resolution_note' => 'Winner used a bot',
        'closed_by' => 'api_key:4',
        'closed_at' => '2026-09-24T09:30:00+03:00',
        'refunds' => [['customer_id' => 42, 'wallet_id' => 42, 'amount' => 100.0]],
    ]);
}

/**
 * Whether a recorded request is a `GET /complaints` list call with exactly these query values.
 *
 * @param  array<string, string>  $expected
 */
function isComplaintListQuery(Request $request, array $expected): bool
{
    if ($request->method() !== 'GET' || ! str_contains($request->url(), '/complaints?')) {
        return false;
    }

    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return array_intersect_key($query, $expected) == $expected;
}

/**
 * Fake every wallet API call the complaint pages make. Close stubs go first because the
 * first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeWalletApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/complaints/*' => Http::response(['success' => true, 'data' => complaintPayload()]),
        '*/complaints*' => Http::response([
            'data' => [complaintPayload()],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 1],
        ]),
        '*/customers/*' => Http::response(['data' => ['id' => 57, 'name' => 'Wanjiru Otieno']]),
        '*/finance/disputes*' => Http::response(['success' => true, 'data' => ['summary' => ['currently_held' => 150.0], 'items' => []]]),
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

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

it('lists pending complaints by default', function (): void {
    fakeWalletApi();
    $this->actingAs($this->admin);

    Livewire::test(ComplaintsPage::class)
        ->assertSee('The winner kept playing after I disconnected')
        ->assertSee('9b1c2f0e');

    Http::assertSent(fn (Request $request): bool => isComplaintListQuery($request, [
        'status' => 'pending_dispute',
        'page' => '1',
        'per_page' => '50',
    ]));
});

it('passes the table filters through to the complaints query', function (): void {
    fakeWalletApi();
    $this->actingAs($this->admin);

    Livewire::test(ComplaintsPage::class)
        ->filterTable('status', 'resolved')
        ->filterTable('subject_type', 'tournament')
        ->filterTable('customer', ['customer_id' => 42])
        ->filterTable('filed', ['from' => '2026-09-01', 'to' => '2026-09-20']);

    Http::assertSent(fn (Request $request): bool => isComplaintListQuery($request, [
        'status' => 'resolved',
        'subject_type' => 'tournament',
        'customer_id' => '42',
        'from' => '2026-09-01',
        'to' => '2026-09-20',
    ]));
});

it('flags pending complaints older than three days as aged', function (string $filedAt, bool $isAged): void {
    $this->travelTo('2026-09-23 12:00:00');

    expect(ComplaintsPage::isAged(complaintPayload(['created_at' => $filedAt])))->toBe($isAged);
})->with([
    'four days old' => ['2026-09-19T11:00:00+03:00', true],
    'two days old' => ['2026-09-21T12:00:00+03:00', false],
]);

it('never flags a closed complaint as aged', function (): void {
    $this->travelTo('2026-09-23 12:00:00');

    expect(ComplaintsPage::isAged(complaintPayload(['status' => 'resolved', 'created_at' => '2026-08-01T10:00:00+03:00'])))->toBeFalse();
});

it('shows a complaint with its disputed transactions', function (): void {
    fakeWalletApi();
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->assertSee('Game wallet #1051')
        ->assertSee('Game transaction #88213')
        ->assertSee('Wanjiru Otieno (#57)')
        ->assertSee('Wallet #57')
        ->assertSee('KES 190.00')
        ->assertSee('KES 40.00')
        ->assertDontSee('Resolution');
});

it('returns 404 for a complaint the API does not know', function (): void {
    fakeWalletApi(['*/complaints/*' => Http::response(['success' => false, 'message' => 'Complaint not found'], 404)]);
    $this->actingAs($this->admin);

    get(ComplaintDetailPage::getUrl(['complaint' => 999]))->assertNotFound();
});

it('closes a pending complaint with the note and an idempotency key', function (string $outcome, array $closed, string $title, string $summary): void {
    fakeWalletApi([
        "*/complaints/*/{$outcome}" => Http::response(['success' => true, 'data' => $closed]),
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->callAction($outcome, data: ['note' => 'Checked the round logs'])
        ->assertHasNoActionErrors()
        ->assertNotified(Notification::make()->title($title)->body($summary)->success())
        ->assertSee('Checked the round logs')
        ->assertActionHidden($outcome);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), "/{$outcome}")
        && $request['note'] === 'Checked the round logs — by Grace Admin'
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));
})->with([
    'resolve' => ['resolve', fn (): array => [...resolvedComplaintPayload(), 'resolution_note' => 'Checked the round logs'], 'Complaint resolved', 'Refunded KES 200.00 · house cuts reversed KES 20.00.'],
    'reject' => ['reject', fn (): array => complaintPayload(['status' => 'rejected', 'released_amount' => 150.0, 'resolution_note' => 'Checked the round logs']), 'Complaint rejected', 'Released KES 150.00 back to the winner.'],
    'cancel' => ['cancel', fn (): array => complaintPayload(['status' => 'cancelled', 'released_amount' => 150.0, 'resolution_note' => 'Checked the round logs']), 'Complaint cancelled', 'Released KES 150.00 back to the winner.'],
]);

it('closes a pending complaint from the list row', function (): void {
    fakeWalletApi([
        '*/complaints/*/reject' => Http::response(['success' => true, 'data' => complaintPayload(['status' => 'rejected', 'released_amount' => 150.0])]),
    ]);
    $this->actingAs($this->admin);

    Livewire::test(ComplaintsPage::class)
        ->callAction(TestAction::make('reject')->table('3'), data: ['note' => 'Winner played fairly'])
        ->assertHasNoActionErrors()
        ->assertNotified('Complaint rejected');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/reject')
        && $request['note'] === 'Winner played fairly — by Grace Admin');
});

it('requires a note of at least three characters before closing', function (?string $note, string $rule): void {
    fakeWalletApi();
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->callAction('resolve', data: ['note' => $note])
        ->assertHasActionErrors(['note' => $rule]);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
})->with([
    'missing' => [null, 'required'],
    'too short' => ['ok', 'min'],
]);

it('reports an already closed complaint and shows its new status', function (): void {
    $closedElsewhere = false;

    fakeWalletApi([
        '*/complaints/*/resolve' => function () use (&$closedElsewhere) {
            $closedElsewhere = true;

            return Http::response(['success' => false, 'message' => 'Complaint is already rejected.'], 409);
        },
        '*/complaints/*' => function () use (&$closedElsewhere) {
            return Http::response(['success' => true, 'data' => $closedElsewhere
                ? complaintPayload(['status' => 'rejected', 'resolution_note' => 'Closed elsewhere'])
                : complaintPayload()]);
        },
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->callAction('resolve', data: ['note' => 'Winner used a bot'])
        ->assertNotified('Complaint already closed')
        ->assertSee('Closed elsewhere')
        ->assertActionHidden('resolve');
});

it('shows the API message when the wallet API refuses to close', function (): void {
    fakeWalletApi([
        '*/complaints/*/resolve' => Http::response(['success' => false, 'message' => 'The losing round for win 7712 was not found.'], 422),
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->callAction('resolve', data: ['note' => 'Winner used a bot'])
        ->assertNotified(Notification::make()->title('Could not close the complaint')->body('The losing round for win 7712 was not found.')->danger());
});

it('reuses the idempotency key when a rate-limited close is retried', function (): void {
    fakeWalletApi([
        '*/complaints/*/resolve' => Http::sequence()
            ->push(['message' => 'Too Many Attempts.'], 429)
            ->push(['success' => true, 'data' => resolvedComplaintPayload()]),
    ]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->mountAction('resolve')
        ->fillForm(['note' => 'Winner used a bot'])
        ->callMountedAction()
        ->assertActionMounted('resolve')
        ->callMountedAction()
        ->assertNotified('Complaint resolved');

    $keys = Http::recorded(fn (Request $request): bool => $request->method() === 'POST')
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(1);
});

it('shows the resolution and hides the close actions on a closed complaint', function (): void {
    fakeWalletApi(['*/complaints/*' => Http::response(['success' => true, 'data' => resolvedComplaintPayload()])]);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->assertSee('Winner used a bot')
        ->assertSee('KES 100.00')
        ->assertActionHidden('resolve')
        ->assertActionHidden('reject')
        ->assertActionHidden('cancel');
});

it('lets managers view complaints but not close them', function (): void {
    fakeWalletApi();
    $this->actingAs($this->manager);

    Livewire::withQueryParams(['complaint' => 3])
        ->test(ComplaintDetailPage::class)
        ->assertSee('Game transaction #88213')
        ->assertActionHidden('resolve')
        ->assertActionHidden('reject')
        ->assertActionHidden('cancel');
});

it('shows complaint stats on the dashboard to everyone who can view complaints', function (): void {
    $this->actingAs($this->manager);
    expect(ComplaintsStatsWidget::canView())->toBeTrue();

    $this->actingAs($this->agent);
    expect(ComplaintsStatsWidget::canView())->toBeFalse();
});

it('renders pending, held and aged dispute figures from the API', function (): void {
    fakeWalletApi();
    $this->actingAs($this->manager);

    Livewire::test(ComplaintsStatsWidget::class)
        ->assertSee('Pending Complaints')
        ->assertSee('KES 150.00')
        ->assertSee('Aged Disputes');

    Http::assertSent(fn (Request $request): bool => isComplaintListQuery($request, ['status' => 'pending_dispute', 'per_page' => '1']));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/finance/disputes?'));
});

it('keeps agents out of the complaint pages', function (): void {
    fakeWalletApi();
    $this->actingAs($this->agent);

    get(ComplaintsPage::getUrl())->assertForbidden();
    get(ComplaintDetailPage::getUrl(['complaint' => 3]))->assertForbidden();
});
