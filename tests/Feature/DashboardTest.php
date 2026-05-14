<?php

use App\Enums\UserStatus;
use App\Models\User;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

test('guests are redirected to the login page', function () {
    get('/')->assertRedirectContains('login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Active->value]);
    $user->assignRole('super-admin');

    $this->actingAs($user)->get('/')->assertOk();
});
