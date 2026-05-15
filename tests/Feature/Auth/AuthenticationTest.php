<?php

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'status' => UserStatus::Active->value,
    ]);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'status' => UserStatus::Active->value,
    ]);

    Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'wrong-password')
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create(['status' => UserStatus::Active->value]);

    $this->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
