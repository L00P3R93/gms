<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ReferralsPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * A referral as KadiApi's `/referrals` list returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function referralPayload(array $overrides = []): array
{
    return [
        'id' => 7,
        'referrer_id' => 42,
        'referred_id' => 97,
        'referred_name' => 'Achieng Wafula',
        'referred_phone' => '2547****5678',
        'code_used' => 'KADI2026',
        'status' => 'deposited',
        'verified_at' => '2026-09-24T10:00:00+03:00',
        'first_deposited_at' => '2026-09-24T11:30:00+03:00',
        'earned' => 20.0,
        'bonuses' => [
            ['milestone' => 'signup', 'amount' => 10.0, 'paid_at' => '2026-09-24T10:00:00+03:00'],
            ['milestone' => 'first_deposit', 'amount' => 10.0, 'paid_at' => '2026-09-24T11:30:00+03:00'],
        ],
        'created_at' => '2026-09-24T09:55:00+03:00',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $stubs
 */
function fakeReferralsApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/referrals*' => Http::response([
            'data' => [referralPayload()],
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

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');
});

it('lists referrals with their bonuses and links both customers', function (): void {
    fakeReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(ReferralsPage::class)
        ->assertSee('Achieng Wafula')
        ->assertSee('KADI2026')
        ->assertSee('Deposited')
        ->assertSee('Signup KES 10.00')
        ->assertSee('First deposit KES 10.00')
        ->assertSee('KES 20.00')
        ->assertSee(AccountResource::getUrl('view', ['record' => 42]))
        ->assertSee(AccountResource::getUrl('view', ['record' => 97]));
});

it('passes the status and signup date filters to the API', function (): void {
    fakeReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(ReferralsPage::class)
        ->filterTable('status', 'verified')
        ->filterTable('signed_up', ['from' => '2026-09-01', 'to' => '2026-09-20']);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/referrals?')
            && array_intersect_key($query, array_flip(['status', 'from', 'to', 'page', 'per_page'])) == [
                'status' => 'verified', 'from' => '2026-09-01', 'to' => '2026-09-20', 'page' => '1', 'per_page' => '50',
            ];
    });
});

it('shows a referral that has not paid a bonus yet', function (): void {
    fakeReferralsApi(['*/referrals*' => Http::response([
        'data' => [referralPayload(['status' => 'pending_verification', 'verified_at' => null, 'first_deposited_at' => null, 'earned' => 0.0, 'bonuses' => []])],
        'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 1],
    ])]);
    $this->actingAs($this->manager);

    Livewire::test(ReferralsPage::class)
        ->assertSee('Pending verification')
        ->assertSee('None yet');
});

it('shows an empty state instead of failing when KadiApi is down', function (): void {
    fakeReferralsApi(['*/referrals*' => Http::response(['message' => 'Internal server error'], 500)]);
    $this->actingAs($this->manager);

    Livewire::test(ReferralsPage::class)
        ->assertSee('Referrals unavailable');
});

it('keeps users who cannot view customers out of the referrals list', function (): void {
    fakeReferralsApi();
    $this->actingAs(User::factory()->create(['status' => UserStatus::Active->value]));

    get(ReferralsPage::getUrl())->assertForbidden();
});
