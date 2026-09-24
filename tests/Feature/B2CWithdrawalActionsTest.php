<?php

use App\Enums\CompanyWithdrawStatus;
use App\Enums\HolderStatus;
use App\Enums\UserStatus;
use App\Enums\WithdrawStatus;
use App\Filament\Resources\CompanyWithdraws\Pages\ListCompanyWithdraws;
use App\Filament\Resources\Holders\Pages\ListHolders;
use App\Models\CompanyWallet;
use App\Models\CompanyWithdraw;
use App\Models\Holder;
use App\Models\HolderWallet;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\MpesaService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

/**
 * @return array<string, string>
 */
function acceptedB2cResponse(): array
{
    return [
        'ConversationID' => 'AG_20260924_B2C_0001',
        'OriginatorConversationID' => 'abc-123',
        'ResponseCode' => '0',
        'ResponseDescription' => 'Accept the service request successfully.',
    ];
}

it('pays a shareholder withdrawal with the B2C request array', function (): void {
    $holder = Holder::create(['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'id_no' => '12345678', 'share' => 10, 'status' => HolderStatus::Active->value, 'user_id' => $this->admin->id]);
    HolderWallet::create(['holder_id' => $holder->id, 'balance' => 5000]);

    $this->mock(MpesaService::class)
        ->shouldReceive('b2c')->once()
        ->with(['Amount' => 1500, 'PartyB' => '254712345678', 'Remarks' => 'Shareholder Payout', 'Occasion' => ''])
        ->andReturn(acceptedB2cResponse());

    Livewire::test(ListHolders::class)
        ->callAction(TestAction::make('withdraw')->table($holder), data: ['amount' => 1500])
        ->assertHasNoActionErrors()
        ->assertNotified('Withdrawal initiated');

    $withdraw = Withdraw::sole();
    expect($withdraw->status)->toBe(WithdrawStatus::Processing)
        ->and($withdraw->conversation_id)->toBe('AG_20260924_B2C_0001')
        ->and((float) $holder->wallet->fresh()->balance)->toBe(3500.0);
});

it('rejects a shareholder withdrawal that is not whole shillings', function (): void {
    $holder = Holder::create(['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'id_no' => '12345678', 'share' => 10, 'status' => HolderStatus::Active->value, 'user_id' => $this->admin->id]);
    HolderWallet::create(['holder_id' => $holder->id, 'balance' => 5000]);

    $this->mock(MpesaService::class)->shouldNotReceive('b2c');

    Livewire::test(ListHolders::class)
        ->callAction(TestAction::make('withdraw')->table($holder), data: ['amount' => 150.5])
        ->assertHasActionErrors(['amount' => 'integer']);

    expect(Withdraw::count())->toBe(0);
});

it('pays an approved company withdrawal with the B2C request array', function (): void {
    DB::table('company_wallet')->insert(['id' => CompanyWallet::MAIN_WALLET, 'type' => 'company', 'balance' => 10000, 'updated_at' => now()]);
    $withdrawal = CompanyWithdraw::create([
        'phone' => '254700000001', 'amount' => 2500, 'user_id' => $this->admin->id,
        'reason' => str_repeat('Server hosting renewal ', 6), 'status' => CompanyWithdrawStatus::Pending->value,
    ]);

    $this->mock(MpesaService::class)
        ->shouldReceive('b2c')->once()
        ->withArgs(fn (array $params): bool => $params['Amount'] === 2500
            && $params['PartyB'] === '254700000001'
            && $params['Remarks'] === mb_substr(str_repeat('Server hosting renewal ', 6), 0, 100)
            && $params['Occasion'] === '')
        ->andReturn(acceptedB2cResponse());

    Livewire::test(ListCompanyWithdraws::class)
        ->callAction(TestAction::make('approve')->table($withdrawal))
        ->assertNotified('Payment initiated');

    expect($withdrawal->fresh()->status)->toBe(CompanyWithdrawStatus::Processing)
        ->and((float) CompanyWallet::find(CompanyWallet::MAIN_WALLET)->balance)->toBe(7500.0);
});

it('refuses to pay a company withdrawal that is not whole shillings', function (): void {
    DB::table('company_wallet')->insert(['id' => CompanyWallet::MAIN_WALLET, 'type' => 'company', 'balance' => 10000, 'updated_at' => now()]);
    $withdrawal = CompanyWithdraw::create([
        'phone' => '254700000001', 'amount' => 2500.5, 'user_id' => $this->admin->id,
        'reason' => 'Hosting', 'status' => CompanyWithdrawStatus::Pending->value,
    ]);

    $this->mock(MpesaService::class)->shouldNotReceive('b2c');

    Livewire::test(ListCompanyWithdraws::class)
        ->callAction(TestAction::make('approve')->table($withdrawal))
        ->assertNotified('Amount must be whole shillings');

    expect($withdrawal->fresh()->status)->toBe(CompanyWithdrawStatus::Pending);
});
