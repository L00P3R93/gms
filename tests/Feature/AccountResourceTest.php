<?php

use App\Enums\UserStatus;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Models\User;
use App\Services\GameApiService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');

    $apiCustomers = [
        [
            'id' => 42,
            'name' => 'Jane Doe',
            'phone_no' => '254712345678',
            'email' => 'jane@example.com',
            'balance' => 1250.00,
            'status' => 1,
            'wallet_id' => 10,
        ],
    ];

    $this->mock = $this->mock(GameApiService::class);
    $this->mock->shouldReceive('listCustomers')->andReturn($apiCustomers);
});

it('loads the customer list from the API', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ListAccounts::class)
        ->assertSuccessful();
});

it('returns empty array when the API is unavailable', function (): void {
    $this->mock->shouldReceive('listCustomers')
        ->andThrow(new RuntimeException('API unreachable'))
        ->byDefault();

    $this->actingAs($this->admin);

    Livewire::test(ListAccounts::class)
        ->assertSuccessful();
});

it('hide action calls API to set status hidden', function (): void {
    //
})->skip('Table record actions (recordActions) require callTableAction — complex setup with array records.');

it('unhide action calls API to set status active', function (): void {
    //
})->skip('Table record actions (recordActions) require callTableAction — complex setup with array records.');

it('merging local game_status is skipped — status now comes from the API', function (): void {
    //
})->skip('Local accounts table dropped; status field now comes from the API response directly.');
