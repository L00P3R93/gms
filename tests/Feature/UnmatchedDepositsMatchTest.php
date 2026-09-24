<?php

use App\Enums\UserStatus;
use App\Filament\Pages\UnmatchedDepositsPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Widgets\UnmatchedDepositsWidget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GameApiService;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * KadiApi's match result: two deposits whose bill refs match account numbers.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function matchResult(bool $dryRun, array $overrides = []): array
{
    return ['success' => true, 'data' => [
        'dry_run' => $dryRun,
        'matched' => 2,
        'matched_amount' => 150.0,
        'assigned' => $dryRun ? 0 : 2,
        'assigned_amount' => $dryRun ? 0.0 : 150.0,
        'items' => [
            ['deposit_id' => 812, 'trans_id' => 'SIR7XYZ123', 'amount' => 100.0, 'bill_ref_no' => 'kk6aa8b1daaf392', 'customer_id' => 97, 'account_no' => 'KK-6AA8B1DAAF392', 'assigned' => ! $dryRun, 'message' => $dryRun ? 'Would be credited' : 'Deposit credited to the customer'],
            ['deposit_id' => 813, 'trans_id' => 'SIR7XYZ124', 'amount' => 50.0, 'bill_ref_no' => 'KK-7WMK98TW', 'customer_id' => 98, 'account_no' => 'KK-7WMK98TW', 'assigned' => ! $dryRun, 'message' => $dryRun ? 'Would be credited' : 'Deposit credited to the customer'],
        ],
        ...$overrides,
    ]];
}

/**
 * Fake the queue and the match endpoint, answering dry runs and live calls separately.
 */
function fakeMatchApi(mixed $dryRun = null, mixed $live = null): void
{
    Http::preventStrayRequests();

    $dryRun ??= Http::response(matchResult(true));
    $live ??= Http::response(matchResult(false));

    Http::fake([
        '*/deposits/unmatched/match' => function (Request $request) use ($dryRun, $live) {
            $response = $request->data()['dry_run'] === true ? $dryRun : $live;

            return $response instanceof ResponseSequence ? $response($request) : $response;
        },
        '*/deposits/unmatched*' => Http::response(['data' => [
            'summary' => ['unmatched_count' => 2, 'unmatched_amount' => 150.0],
            'items' => [],
            'pagination' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'last_page' => 1],
        ]]),
    ]);
}

/**
 * @return list<bool> The `dry_run` flag of every match call, in order.
 */
function matchCalls(): array
{
    return collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/deposits/unmatched/match'))
        ->map(fn (array $pair): bool => $pair[0]->data()['dry_run'])
        ->values()
        ->all();
}

beforeEach(function (): void {
    Cache::flush();

    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['name' => 'Amina Finance', 'status' => UserStatus::Active->value]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('previews with a dry run and lists what would be assigned without assigning anything', function (): void {
    fakeMatchApi();

    Livewire::test(UnmatchedDepositsPage::class)
        ->mountAction('matchByAccount')
        ->assertActionMounted('matchByAccount')
        ->assertMountedActionModalSee([
            '2 deposits', 'KES 150.00',
            'SIR7XYZ123', 'KES 100.00', 'kk6aa8b1daaf392', 'Customer #97', 'KK-6AA8B1DAAF392',
            'SIR7XYZ124', 'KES 50.00', 'Customer #98', 'KK-7WMK98TW',
            'Phone numbers are never used',
            'Assign 2 deposits',
        ])
        ->assertMountedActionModalSeeHtml(AccountResource::getUrl('view', ['record' => 97]));

    expect(matchCalls())->toBe([true]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/match') && ! $request->hasHeader('Idempotency-Key'));
});

it('assigns only after the admin confirms the dry run, with an idempotency key', function (): void {
    fakeMatchApi();
    Cache::put(GameApiService::UNMATCHED_SUMMARY_CACHE_KEY, ['unmatched_count' => 2], 60);

    Livewire::test(UnmatchedDepositsPage::class)
        ->callAction('matchByAccount')
        ->assertNotified('Assigned 2 deposits · KES 150.00')
        ->assertDispatched(UnmatchedDepositsWidget::REFRESH_EVENT)
        ->assertSet('matchPreview', null);

    expect(matchCalls())->toBe([true, false])
        ->and(Cache::has(GameApiService::UNMATCHED_SUMMARY_CACHE_KEY))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/match')
        && $request->data() === ['dry_run' => false]
        && Str::isUuid($request->header('Idempotency-Key')[0] ?? ''));

    expect(AuditLog::where('auditable_type', 'KadiApi\\DepositMatch')->sole()->event)->toBe('matched')
        ->and(AuditLog::where('auditable_type', 'KadiApi\\Deposit')->where('event', 'matched')->orderBy('auditable_id')->pluck('auditable_id')->all())->toBe([812, 813])
        ->and(AuditLog::where('auditable_type', 'KadiApi\\Deposit')->first()->user_id)->toBe($this->admin->id);
});

it('never makes the live call when the preview is closed', function (): void {
    fakeMatchApi();

    Livewire::test(UnmatchedDepositsPage::class)
        ->mountAction('matchByAccount')
        ->unmountAction();

    expect(matchCalls())->toBe([true]);
});

it('cannot be pushed into a live match by faking the preview from the browser', function (): void {
    fakeMatchApi();

    Livewire::test(UnmatchedDepositsPage::class)
        ->set('matchPreview', matchResult(true)['data']);
})->throws(CannotUpdateLockedPropertyException::class);

it('does not open when there is nothing to match or the dry run fails', function (mixed $dryRun, string $title): void {
    fakeMatchApi(dryRun: $dryRun);

    Livewire::test(UnmatchedDepositsPage::class)
        ->mountAction('matchByAccount')
        ->assertActionNotMounted('matchByAccount')
        ->assertNotified($title);

    expect(matchCalls())->toBe([true]);
})->with([
    'nothing matches' => [fn () => Http::response(matchResult(true, ['matched' => 0, 'matched_amount' => 0, 'items' => []])), 'Nothing to match'],
    'dry run fails' => [fn () => Http::response(['message' => 'Server Error'], 500), 'Could not preview the match'],
]);

it('keeps the modal open and replays the same key when the live call is rate limited', function (): void {
    fakeMatchApi(live: Http::sequence()
        ->push(['message' => 'Too Many Attempts.'], 429)
        ->push(matchResult(false)));

    Livewire::test(UnmatchedDepositsPage::class)
        ->callAction('matchByAccount')
        ->assertNotified('Could not match deposits')
        ->assertActionMounted('matchByAccount')
        ->callMountedAction()
        ->assertNotified('Assigned 2 deposits · KES 150.00');

    $keys = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/match') && $pair[0]->data()['dry_run'] === false)
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->values();

    expect(matchCalls())->toBe([true, false, false])
        ->and($keys[0])->toBe($keys[1])
        ->and(AuditLog::where('event', 'match_failed')->sole()->new_values['status'])->toBe(429);
});

it('says which deposits were not assigned and when the run differed from the preview', function (): void {
    fakeMatchApi(live: Http::response(matchResult(false, [
        'matched' => 3,
        'assigned' => 1,
        'assigned_amount' => 100.0,
        'items' => [
            ['deposit_id' => 812, 'trans_id' => 'SIR7XYZ123', 'amount' => 100.0, 'customer_id' => 97, 'account_no' => 'KK-6AA8B1DAAF392', 'assigned' => true, 'message' => 'Deposit credited to the customer'],
            ['deposit_id' => 813, 'trans_id' => 'SIR7XYZ124', 'amount' => 50.0, 'customer_id' => 98, 'account_no' => 'KK-7WMK98TW', 'assigned' => false, 'message' => 'Deposit is no longer unmatched'],
        ],
    ])));

    Livewire::test(UnmatchedDepositsPage::class)->callAction('matchByAccount');

    $sent = new Notifications;
    $sent->mount();
    $notification = $sent->notifications->first(fn (Notification $notification): bool => $notification->getTitle() === 'Assigned 1 deposit · KES 100.00');

    expect($notification)->not->toBeNull()
        ->and($notification->getBody())->toContain('The preview showed 2; 3 matched when it ran.')
        ->and($notification->getBody())->toContain('Not assigned: SIR7XYZ124 — Deposit is no longer unmatched')
        ->and($notification->getColor())->toBe('warning')
        ->and(AuditLog::where('auditable_type', 'KadiApi\\Deposit')->pluck('auditable_id')->all())->toBe([812]);
});

it('hides the match from support', function (): void {
    fakeMatchApi();
    $manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $manager->assignRole('manager');
    $this->actingAs($manager);

    Livewire::test(UnmatchedDepositsPage::class)->assertActionHidden('matchByAccount');

    expect(matchCalls())->toBe([]);
});
