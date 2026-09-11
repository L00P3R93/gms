<?php

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');
});

it('updates a user\'s role via the edit modal action', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active->value]);
    $user->assignRole('agent');

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make(EditAction::class)->table($user), data: [
            'role' => 'manager',
        ])
        ->assertHasNoActionErrors();

    expect($user->refresh()->roles->pluck('name')->all())->toBe(['manager']);
});

it('assigns a role to a new user via the create modal action', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->callAction(CreateAction::class, data: [
            'name' => 'Jane Doe',
            'userName' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'role' => 'agent',
            'status' => UserStatus::Active->value,
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->roles->pluck('name')->all())->toBe(['agent']);
});
