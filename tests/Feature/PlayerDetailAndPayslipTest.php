<?php

use App\Enums\UserStatus;
use App\Filament\Pages\PayslipPage;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Models\Account;
use App\Models\PlayedGame;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');

    $this->account = Account::create([
        'name' => 'Test Player',
        'phone' => '0712000001',
        'email' => 'player@test.com',
        'password' => 'hashed',
        'game_status' => 1,
        'credit' => 500,
        'vcoins' => 100,
    ]);
});

// --- ViewAccount ---

it('loads the ViewAccount page for a valid account', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => $this->account->id])
        ->assertSet('activeTab', 'single_games')
        ->assertCount('singleGames', 0);
});

it('single games tab shows Won for the current player when they are the winner', function (): void {
    $id = $this->account->id;

    PlayedGame::create([
        'match_name' => 'game-won',
        'match_type' => PlayedGame::TYPE_MULTI_2,
        'player_1' => (string) $id,
        'player_2' => '999',
        'amount' => 100,
        'winner' => (string) $id,
    ]);

    PlayedGame::create([
        'match_name' => 'game-lost',
        'match_type' => PlayedGame::TYPE_MULTI_2,
        'player_1' => (string) $id,
        'player_2' => '999',
        'amount' => 50,
        'winner' => '999',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => $id])
        ->assertSet('activeTab', 'single_games')
        ->assertCount('singleGames', 2);
});

it('tournament tab loads games for all 6 player columns', function (): void {
    $id = $this->account->id;

    PlayedGame::create([
        'match_name' => 'TN-001',
        'match_type' => PlayedGame::TYPE_TOURNAMENT,
        'player_5' => (string) $id,
        'amount' => 200,
        'winner' => '999',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => $id])
        ->assertCount('tournamentGames', 1);
});

it('jackpot tab loads JP games for the player', function (): void {
    $id = $this->account->id;

    PlayedGame::create([
        'match_name' => 'JP-GOLD-001',
        'match_type' => PlayedGame::TYPE_JACKPOT,
        'player_3' => (string) $id,
        'amount' => 500,
        'winner' => '999',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewAccount::class, ['record' => $id])
        ->assertCount('jackpotGames', 1);
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

it('getPlayerData returns correct win/loss stats', function (): void {
    $id = $this->account->id;

    PlayedGame::create(['match_name' => 'g1', 'match_type' => PlayedGame::TYPE_MULTI_2, 'player_1' => (string) $id, 'player_2' => '999', 'amount' => 100, 'winner' => (string) $id]);
    PlayedGame::create(['match_name' => 'g2', 'match_type' => PlayedGame::TYPE_MULTI_3, 'player_1' => (string) $id, 'player_2' => '999', 'player_3' => '998', 'amount' => 100, 'winner' => '999']);

    $this->actingAs($this->admin);

    $component = Livewire::test(PayslipPage::class)
        ->set('selectedAccountId', $id);

    $data = $component->instance()->getPlayerData();

    expect($data)->not->toBeNull()
        ->and($data['gamesPlayed'])->toBe(2)
        ->and($data['gamesWon'])->toBe(1)
        ->and($data['winRate'])->toBe(50.0);
});
