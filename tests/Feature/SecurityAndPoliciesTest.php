<?php

use App\Enums\UserStatus;
use App\Models\Account;
use App\Models\Holder;
use App\Models\PlayedGame;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\HolderPolicy;
use App\Policies\PlayedGamePolicy;
use App\Policies\UserPolicy;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');

    $this->agent = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->agent->assignRole('agent');
});

// --- UserPolicy ---

it('super-admin can viewAny users', function (): void {
    expect((new UserPolicy)->viewAny($this->admin))->toBeTrue();
});

it('agent cannot viewAny users', function (): void {
    expect((new UserPolicy)->viewAny($this->agent))->toBeFalse();
});

it('no one can delete users', function (): void {
    $user = User::factory()->create();
    expect((new UserPolicy)->delete($this->admin, $user))->toBeFalse();
    expect((new UserPolicy)->delete($this->agent, $user))->toBeFalse();
});

// --- HolderPolicy ---

it('super-admin can viewAny holders', function (): void {
    expect((new HolderPolicy)->viewAny($this->admin))->toBeTrue();
});

it('agent cannot viewAny holders', function (): void {
    expect((new HolderPolicy)->viewAny($this->agent))->toBeFalse();
});

it('no one can delete holders', function (): void {
    $holder = Holder::create(['name' => 'Test Holder', 'share' => 0.10, 'user_id' => $this->admin->id]);
    expect((new HolderPolicy)->delete($this->admin, $holder))->toBeFalse();
    expect((new HolderPolicy)->delete($this->agent, $holder))->toBeFalse();
});

// --- AccountPolicy ---

it('both roles can viewAny accounts', function (): void {
    $policy = new AccountPolicy;
    expect($policy->viewAny($this->admin))->toBeTrue();
    expect($policy->viewAny($this->agent))->toBeTrue();
});

it('super-admin can update accounts', function (): void {
    $account = Account::create([
        'name' => 'Test', 'phone' => '0700000001', 'email' => 'x@x.com',
        'password' => 'hash', 'game_status' => 1, 'credit' => 0, 'vcoins' => 0,
    ]);
    expect((new AccountPolicy)->update($this->admin, $account))->toBeTrue();
});

it('agent cannot update accounts', function (): void {
    $account = Account::create([
        'name' => 'Test', 'phone' => '0700000002', 'email' => 'y@y.com',
        'password' => 'hash', 'game_status' => 1, 'credit' => 0, 'vcoins' => 0,
    ]);
    expect((new AccountPolicy)->update($this->agent, $account))->toBeFalse();
});

it('no one can create or delete accounts', function (): void {
    $account = Account::create([
        'name' => 'Test', 'phone' => '0700000003', 'email' => 'z@z.com',
        'password' => 'hash', 'game_status' => 1, 'credit' => 0, 'vcoins' => 0,
    ]);
    $policy = new AccountPolicy;
    expect($policy->create($this->admin))->toBeFalse();
    expect($policy->delete($this->admin, $account))->toBeFalse();
    expect($policy->delete($this->agent, $account))->toBeFalse();
});

// --- PlayedGamePolicy ---

it('super-admin can viewAny played games', function (): void {
    expect((new PlayedGamePolicy)->viewAny($this->admin))->toBeTrue();
});

it('agent cannot viewAny played games', function (): void {
    expect((new PlayedGamePolicy)->viewAny($this->agent))->toBeFalse();
});

it('no one can create, update, or delete played games', function (): void {
    $game = PlayedGame::create([
        'match_name' => 'test', 'match_type' => PlayedGame::TYPE_MULTI_2,
        'player_1' => '1', 'player_2' => '2', 'amount' => 100, 'winner' => '1',
    ]);
    $policy = new PlayedGamePolicy;
    expect($policy->create($this->admin))->toBeFalse();
    expect($policy->update($this->admin, $game))->toBeFalse();
    expect($policy->delete($this->admin, $game))->toBeFalse();
});

// --- Gate integration ---

it('Gate respects AccountPolicy for agent viewAny', function (): void {
    $this->actingAs($this->agent);
    expect(Gate::check('viewAny', Account::class))->toBeTrue();
});

it('Gate respects HolderPolicy blocking agent', function (): void {
    $this->actingAs($this->agent);
    expect(Gate::check('viewAny', Holder::class))->toBeFalse();
});

// --- SafaricomIpWhitelist ---

it('b2c result route returns 403 in production for unknown IP', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
        ->postJson('/api/v1/b2c/result', []);

    $response->assertStatus(403);

    app()->detectEnvironment(fn () => 'testing');
});

it('b2c result route passes through in non-production', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
        ->postJson('/api/v1/b2c/result', []);

    // In testing env the middleware lets it through; controller handles the rest (not 403)
    $response->assertStatus(200);
});
