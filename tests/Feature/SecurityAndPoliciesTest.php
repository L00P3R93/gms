<?php

use App\Enums\UserStatus;
use App\Models\Holder;
use App\Models\User;
use App\Policies\HolderPolicy;
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

// --- AccountPolicy (skipped — accounts table dropped, model is API-only) ---

it('both roles can viewAny accounts', function (): void {
    //
})->skip('AccountPolicy removed; account access is controlled via API.');

it('super-admin can update accounts', function (): void {
    //
})->skip('AccountPolicy removed; account updates go through the API.');

it('agent cannot update accounts', function (): void {
    //
})->skip('AccountPolicy removed.');

it('no one can create or delete accounts', function (): void {
    //
})->skip('AccountPolicy removed.');

// --- PlayedGamePolicy (skipped — PlayedGame model removed) ---

it('super-admin can viewAny played games', function (): void {
    //
})->skip('PlayedGamePolicy removed; game data is fetched from the API.');

it('agent cannot viewAny played games', function (): void {
    //
})->skip('PlayedGamePolicy removed.');

it('no one can create, update, or delete played games', function (): void {
    //
})->skip('PlayedGamePolicy removed.');

// --- Gate integration ---

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
