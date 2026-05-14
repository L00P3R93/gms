<?php

use App\Enums\HolderStatus;
use App\Enums\UserStatus;
use App\Models\AccountSnapshot;
use App\Models\CompanyWallet;
use App\Models\Holder;
use App\Models\HolderWallet;
use App\Models\User;
use App\Services\GameApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed the company wallets required by the command
    CompanyWallet::insert([
        ['id' => CompanyWallet::MAIN_WALLET, 'balance' => 0, 'updated_at' => now()],
        ['id' => CompanyWallet::REFERRAL_WALLET, 'balance' => 0, 'updated_at' => now()],
    ]);
});

it('skips distribution when no new mpesa income', function () {
    // Snapshot at 10000 — same as current balance
    AccountSnapshot::create([
        'balance' => 10000,
        'type' => AccountSnapshot::TYPE_MPESA_BALANCE,
        'created_at' => now(),
    ]);

    $gameApi = Mockery::mock(GameApiService::class);
    $gameApi->shouldReceive('getB2CBalance')->once()->andReturn(10000.0);
    $gameApi->shouldReceive('getPurchasesByReferral')->never();

    $this->app->instance(GameApiService::class, $gameApi);

    $this->artisan('balances:update')->assertSuccessful();

    // No distribution means wallets unchanged
    expect(CompanyWallet::find(CompanyWallet::MAIN_WALLET)->balance)->toBe(0.0);

    // Two new snapshots always created
    expect(AccountSnapshot::count())->toBe(3);
});

it('distributes 40/60 split on new mpesa income', function () {
    AccountSnapshot::create([
        'balance' => 0,
        'type' => AccountSnapshot::TYPE_MPESA_BALANCE,
        'created_at' => now()->subHour(),
    ]);

    $holder = Holder::create([
        'name' => 'Test Holder',
        'phone' => '254700000001',
        'id_no' => 'A1234567',
        'status' => HolderStatus::Active->value,
        'share' => 1.0,
    ]);

    HolderWallet::create(['holder_id' => $holder->id, 'balance' => 0]);

    $gameApi = Mockery::mock(GameApiService::class);
    $gameApi->shouldReceive('getB2CBalance')->once()->andReturn(1000.0);
    $gameApi->shouldReceive('getPurchasesByReferral')->andReturn([]);

    $this->app->instance(GameApiService::class, $gameApi);

    $this->artisan('balances:update')->assertSuccessful();

    expect(CompanyWallet::find(CompanyWallet::MAIN_WALLET)->balance)->toBe(400.0);
    expect(HolderWallet::where('holder_id', $holder->id)->value('balance'))->toBe(600.0);
});

it('deducts referral commission from company share', function () {
    AccountSnapshot::create(['balance' => 0, 'type' => AccountSnapshot::TYPE_MPESA_BALANCE, 'created_at' => now()->subHour()]);
    AccountSnapshot::create(['balance' => 0, 'type' => AccountSnapshot::TYPE_REFERRAL_TOTAL, 'created_at' => now()->subHour()]);

    // Create an agent user with referral codes so the command fetches purchases
    User::factory()->create(['referral_codes' => json_encode(['REF001']), 'status' => UserStatus::Active->value]);

    $holder = Holder::create(['name' => 'Test Holder', 'phone' => '254700000002', 'id_no' => 'B1234567', 'status' => HolderStatus::Active->value, 'share' => 1.0]);
    HolderWallet::create(['holder_id' => $holder->id, 'balance' => 0]);

    $gameApi = Mockery::mock(GameApiService::class);
    $gameApi->shouldReceive('getB2CBalance')->once()->andReturn(1000.0);
    // Referral purchases went from 0 → 200, so 10% commission = 20
    $gameApi->shouldReceive('getPurchasesByReferral')->andReturn([['amount' => 200]]);

    $this->app->instance(GameApiService::class, $gameApi);

    $this->artisan('balances:update')->assertSuccessful();

    // Company: 40% of 1000 = 400, minus referral 10% of 200 = 20 → 380
    expect(CompanyWallet::find(CompanyWallet::MAIN_WALLET)->balance)->toBe(380.0);
    expect(CompanyWallet::find(CompanyWallet::REFERRAL_WALLET)->balance)->toBe(20.0);
    // Shareholder: 60% of 1000 = 600
    expect(HolderWallet::where('holder_id', $holder->id)->value('balance'))->toBe(600.0);
});

it('dry run commits nothing', function () {
    $gameApi = Mockery::mock(GameApiService::class);
    $gameApi->shouldReceive('getB2CBalance')->once()->andReturn(5000.0);
    $gameApi->shouldReceive('getPurchasesByReferral')->andReturn([]);

    $this->app->instance(GameApiService::class, $gameApi);

    $this->artisan('balances:update', ['--dry-run' => true])->assertSuccessful();

    // No snapshots committed
    expect(AccountSnapshot::count())->toBe(0);
    expect(CompanyWallet::find(CompanyWallet::MAIN_WALLET)->balance)->toBe(0.0);
});

it('rolls back on failure', function () {
    $gameApi = Mockery::mock(GameApiService::class);
    $gameApi->shouldReceive('getB2CBalance')->andThrow(new Exception('API down'));

    $this->app->instance(GameApiService::class, $gameApi);

    Log::shouldReceive('error')->once();

    $this->artisan('balances:update')->assertFailed();

    expect(AccountSnapshot::count())->toBe(0);
});
