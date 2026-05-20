<?php

use App\Enums\UserStatus;
use App\Filament\Pages\PayslipPage;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Models\User;
use App\Services\GameApiService;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

// --- ViewAccount ---

it('loads the ViewAccount page for a valid account ID', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andReturn(['id' => 1, 'name' => 'Test', 'status' => 1])
        ->shouldReceive('getCustomerGamesPlayed')->andReturn(['single_games' => [], 'tournament_games' => [], 'jackpot_games' => []])
        ->shouldReceive('getCustomerTransactions')->andReturn(['transactions' => []])
        ->shouldReceive('getCustomerPurchases')->andReturn([]);

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => 1])
        ->assertOk()
        ->assertCount('singleGames', 0);
});

it('single games tab shows data from API response', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andReturn(['id' => 1, 'name' => 'Test', 'status' => 1])
        ->shouldReceive('getCustomerGamesPlayed')->andReturn([
            'single_games' => [
                ['game_id' => 'G1', 'game_type' => 2, 'amount' => 100, 'payment_type' => 'win', 'created_at' => now()->toDateTimeString()],
                ['game_id' => 'G2', 'game_type' => 2, 'amount' => 50, 'payment_type' => 'deposit', 'created_at' => now()->toDateTimeString()],
            ],
            'tournament_games' => [],
            'jackpot_games' => [],
        ])
        ->shouldReceive('getCustomerTransactions')->andReturn(['transactions' => []])
        ->shouldReceive('getCustomerPurchases')->andReturn([]);

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => 1])
        ->assertCount('singleGames', 2);
});

it('ViewAccount sets apiUnavailable when API throws', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andThrow(new RuntimeException('API down'))
        ->shouldReceive('getCustomerGamesPlayed')->andThrow(new RuntimeException('API down'))
        ->shouldReceive('getCustomerTransactions')->andThrow(new RuntimeException('API down'))
        ->shouldReceive('getCustomerPurchases')->andThrow(new RuntimeException('API down'));

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => 99])
        ->assertSet('apiUnavailable', true);
});

// --- PayslipPage access ---

it('blocks agents from the payslip page', function (): void {
    $this->actingAs($this->agent);

    get(PayslipPage::getUrl())->assertForbidden();
});

it('allows super-admin to access the payslip page', function (): void {
    $this->actingAs($this->admin);

    get(PayslipPage::getUrl())->assertOk();
});

// --- PayslipPage player data ---

it('getPlayerData returns null when no account selected', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(PayslipPage::class)
        ->assertSet('selectedAccountId', null)
        ->call('getPlayerData')
        ->assertReturned(null);
});

it('getPlayerData returns correct win/loss stats from API', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andReturn(['id' => 1, 'name' => 'Test Player'])
        ->shouldReceive('getCustomerTransactions')->andReturn(['transactions' => []])
        ->shouldReceive('getCustomerPurchases')->andReturn([])
        ->shouldReceive('getCustomerGamesPlayed')->andReturn([
            'single_games' => [
                ['payment_type' => 'win', 'amount' => 100],
                ['payment_type' => 'deposit', 'amount' => 100],
            ],
            'tournament_games' => [],
            'jackpot_games' => [],
        ]);

    $this->actingAs($this->admin);

    $component = Livewire::test(PayslipPage::class)
        ->set('selectedAccountId', 1);

    $data = $component->instance()->getPlayerData();

    expect($data)->not->toBeNull()
        ->and($data['gamesPlayed'])->toBe(2)
        ->and($data['gamesWon'])->toBe(1)
        ->and($data['winRate'])->toBe(50.0);
});
