<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ReferralWithdrawalDetailPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Livewire\CustomerReferralBonusesTable;
use App\Livewire\CustomerReferralsTable;
use App\Livewire\CustomerReferralSummary;
use App\Livewire\CustomerReferralWithdrawalsTable;
use App\Models\User;
use App\Services\GameApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const REFERRAL_QR_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

/**
 * Fake the KadiApi endpoints behind a customer's Referrals tab. More specific patterns go
 * first because the first matching pattern wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeCustomerReferralsApi(array $stubs = []): void
{
    Http::preventStrayRequests();

    Http::fake($stubs + [
        '*/customers/*/referral-code' => Http::response(['success' => true, 'data' => [
            'customer_id' => 42,
            'code' => '7WMK98TW',
            'link' => 'https://kadi.online/register?ref=7WMK98TW',
            'qr_code' => REFERRAL_QR_DATA_URI,
        ]]),
        '*/customers/*/referrals/stats' => Http::response(['success' => true, 'data' => [
            'code' => '7WMK98TW',
            'referrals' => ['total' => 12, 'verified' => 9, 'deposited' => 6, 'pending_verification' => 3, 'today' => 1, 'this_week' => 4, 'this_month' => 5],
            'earned' => ['total' => 150.0, 'signup' => 90.0, 'first_deposit' => 60.0, 'this_month' => 50.0],
            'wallet_balance' => 100.0,
        ]]),
        '*/customers/*/referrals*' => Http::response([
            'data' => [[
                'id' => 7, 'referred_id' => 97, 'referred_name' => 'Achieng Wafula', 'referred_phone' => '2547****5678',
                'status' => 'deposited', 'earned' => 20.0, 'created_at' => '2026-09-20T09:55:00+03:00',
                'bonuses' => [['milestone' => 'signup', 'amount' => 10.0, 'paid_at' => '2026-09-20T10:00:00+03:00']],
            ]],
            'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1],
        ]),
        '*/customers/*/referral-wallet/withdrawals*' => Http::response([
            'data' => [['id' => 5, 'amount' => 100.0, 'status' => 'completed', 'mpesa_receipt' => 'RKA1B2C3D4', 'created_at' => '2026-09-21T08:00:00+03:00']],
            'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1],
        ]),
        '*/customers/*/referral-wallet*' => Http::response([
            'success' => true,
            'data' => [
                'customer_id' => 42, 'balance' => 100.0, 'total_earned' => 150.0, 'withdrawable' => true, 'minimum_withdrawal' => 50,
                'bonuses' => [['id' => 31, 'referral_id' => 7, 'referred_id' => 97, 'referred_name' => 'Achieng Wafula', 'milestone' => 'first_deposit', 'amount' => 10.0, 'created_at' => '2026-09-21T07:00:00+03:00']],
            ],
            'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
        ]),
        '*.test/referrals*' => Http::response([
            'data' => [['id' => 3, 'referrer_id' => 18, 'referred_id' => 42, 'code_used' => 'OTIENO21', 'status' => 'verified', 'created_at' => '2026-08-02T12:00:00+03:00']],
            'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 1],
        ]),
    ]);
}

beforeEach(function (): void {
    config([
        'services.game_api.url' => 'https://game-api.test',
        'services.game_api.key' => 'test-api-key',
        'services.game_api.openssl_key' => 'a9378f354771d2bdf46c1fd1b5bcaf38',
    ]);

    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->manager = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->manager->assignRole('manager');
});

it('adds a Referrals tab to the customer page without fetching referral data up front', function (): void {
    $this->mock(GameApiService::class)
        ->shouldReceive('getCustomer')->andReturn(['id' => 42, 'name' => 'Jane Doe', 'status' => 1])
        ->shouldReceive('getCustomerGamesPlayed')->andReturn(['single_games' => [], 'tournament_games' => [], 'jackpot_games' => []])
        ->shouldReceive('getCustomerTransactions')->andReturn(['transactions' => []])
        ->shouldReceive('getCustomerPurchases')->andReturn([])
        ->shouldNotReceive('getCustomerReferralCode', 'getCustomerReferrer', 'getCustomerReferralStats', 'listCustomerReferrals', 'getCustomerReferralWallet', 'listCustomerReferralWithdrawals');

    $this->actingAs($this->manager);

    Livewire::test(ViewAccount::class, ['record' => 42])
        ->assertOk()
        ->assertSee('Referrals');
});

it('shows the customer\'s code, link, QR code, referrer and earnings', function (): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->assertSee('7WMK98TW')
        ->assertSee('https://kadi.online/register?ref=7WMK98TW')
        ->assertSeeHtml('src="'.REFERRAL_QR_DATA_URI.'"')
        ->assertSee('Customer #18')
        ->assertSee(AccountResource::getUrl('view', ['record' => 18]))
        ->assertSee('OTIENO21')
        ->assertSee('KES 100.00')
        ->assertSee('KES 150.00');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/referrals?') && str_contains($request->url(), 'referred_id=42'));
});

it('says when the customer has no code and was not referred', function (): void {
    fakeCustomerReferralsApi([
        '*/customers/*/referral-code' => Http::response(['success' => false, 'message' => 'Customer has no referral code'], 404),
        '*.test/referrals*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => 0]]),
    ]);
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->assertSee('No referral code yet.')
        ->assertSee('Not referred by another player.')
        ->assertSee('KES 100.00');
});

it('still shows the other sections when one KadiApi call fails', function (): void {
    fakeCustomerReferralsApi([
        '*/customers/*/referrals/stats' => Http::response(['message' => 'Internal server error'], 500),
    ]);
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->assertSee('Could not be loaded from KadiApi')
        ->assertSee('7WMK98TW')
        ->assertSee('OTIENO21');
});

it('only renders QR codes that are image data URIs or web URLs', function (?string $qrCode, ?string $expected): void {
    expect(CustomerReferralSummary::qrCodeSource($qrCode))->toBe($expected);
})->with([
    'png data URI' => [REFERRAL_QR_DATA_URI, REFERRAL_QR_DATA_URI],
    'https URL' => ['https://cdn.kadi.online/qr/7WMK98TW.png', 'https://cdn.kadi.online/qr/7WMK98TW.png'],
    'javascript URL' => ['javascript:alert(1)', null],
    'html data URI' => ['data:text/html;base64,PHNjcmlwdD4=', null],
    'empty' => [null, null],
]);

it('lists the players the customer referred', function (): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralsTable::class, ['customerId' => 42])
        ->assertSee('Achieng Wafula')
        ->assertSee('Signup KES 10.00')
        ->assertSee('KES 20.00')
        ->assertSee(AccountResource::getUrl('view', ['record' => 97]));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/referrals?') && str_contains($request->url(), 'per_page=20'));
});

it('lists the bonuses in the customer\'s referral wallet', function (): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralBonusesTable::class, ['customerId' => 42])
        ->assertSee('First deposit')
        ->assertSee('Achieng Wafula')
        ->assertSee('KES 10.00');
});

it('lists the customer\'s referral withdrawals with a link to each', function (): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralWithdrawalsTable::class, ['customerId' => 42])
        ->assertSee('RKA1B2C3D4')
        ->assertSee('Completed')
        ->assertSee(ReferralWithdrawalDetailPage::getUrl(['withdrawal' => 5]));
});

it('shows an error state in a referral table when KadiApi is down', function (): void {
    fakeCustomerReferralsApi([
        '*/customers/*/referral-wallet/withdrawals*' => Http::response(['message' => 'Internal server error'], 500),
    ]);
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralWithdrawalsTable::class, ['customerId' => 42])
        ->assertSee('Could not be loaded from KadiApi');
});
