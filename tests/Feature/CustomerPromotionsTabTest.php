<?php

use App\Enums\UserStatus;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Livewire\CustomerPromotionsTable;
use App\Models\User;
use App\Services\GameApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * A customer's signup bonus as KadiApi returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPromotionPayload(array $overrides = []): array
{
    return [
        'id' => 7,
        'promotion' => 'signup_bonus',
        'promo_code' => 'LAUNCH-OCT',
        'gross_amount' => 21.05,
        'excise_amount' => 1.05,
        'net_amount' => 20,
        'wagered' => 5,
        'locked_amount' => 15,
        'unlocked' => false,
        'status' => 'granted',
        'granted_at' => '2026-10-02T10:15:00+03:00',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>|null  $data  The `data` payload, or null to answer 500.
 */
function fakeCustomerPromotionsApi(?array $data): void
{
    Http::preventStrayRequests();

    Http::fake([
        '*/customers/*/promotions' => $data === null
            ? Http::response(['message' => 'Server Error'], 500)
            : Http::response(['success' => true, 'data' => $data]),
    ]);
}

beforeEach(function (): void {
    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
        'services.game_api.openssl_key' => 'a9378f354771d2bdf46c1fd1b5bcaf38',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

it('adds a Promotions tab to the customer page without fetching bonuses up front', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andReturn(['id' => 42, 'name' => 'Jane Doe', 'status' => 1])
        ->shouldReceive('getCustomerGamesPlayed')->andReturn(['single_games' => [], 'tournament_games' => [], 'jackpot_games' => []])
        ->shouldReceive('getCustomerTransactions')->andReturn(['transactions' => []])
        ->shouldReceive('getCustomerPurchases')->andReturn([])
        ->shouldNotReceive('getCustomerPromotions');

    $this->actingAs($this->agent);

    Livewire::test(ViewAccount::class, ['record' => 42])
        ->assertOk()
        ->assertSee('Promotions');
});

it('shows the code, amount received, amount staked and what is still locked', function (): void {
    fakeCustomerPromotionsApi(['locked_amount' => 15, 'items' => [customerPromotionPayload()]]);
    $this->actingAs($this->agent);

    Livewire::test(CustomerPromotionsTable::class, ['customerId' => 42])
        ->assertSee('LAUNCH-OCT')
        ->assertSee('KES 20.00')
        ->assertSee('House paid KES 21.05 incl. KES 1.05 excise')
        ->assertSee('KES 5.00 of KES 20.00')
        ->assertSee('KES 15.00 still locked.')
        ->assertSee('signup_bonus_locked')
        ->assertSee('Locked');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/customers/')
        && str_ends_with($request->url(), '/promotions')
        && ! str_contains($request->url(), '/customers/42/'));
});

it('shows an unlocked bonus without a locked amount in the heading', function (): void {
    fakeCustomerPromotionsApi(['locked_amount' => 0, 'items' => [customerPromotionPayload(['wagered' => 20, 'locked_amount' => 0, 'unlocked' => true])]]);
    $this->actingAs($this->agent);

    Livewire::test(CustomerPromotionsTable::class, ['customerId' => 42])
        ->assertSee('Unlocked')
        ->assertSee('KES 20.00 of KES 20.00')
        ->assertDontSee('still locked');
});

it('says when the customer has no bonus', function (): void {
    fakeCustomerPromotionsApi(['locked_amount' => 0, 'items' => []]);
    $this->actingAs($this->agent);

    Livewire::test(CustomerPromotionsTable::class, ['customerId' => 42])
        ->assertSee('No signup bonus');
});

it('shows an error state instead of failing when KadiApi is down', function (): void {
    fakeCustomerPromotionsApi(null);
    $this->actingAs($this->agent);

    Livewire::test(CustomerPromotionsTable::class, ['customerId' => 42])
        ->assertSee('Could not be loaded from KadiApi');
});
