<?php

use App\Enums\UserStatus;
use App\Filament\Pages\ReferralWithdrawalDetailPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Livewire\CustomerReferralBonusesTable;
use App\Livewire\CustomerReferralsTable;
use App\Livewire\CustomerReferralSummary;
use App\Livewire\CustomerReferralWithdrawalsTable;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GameApiService;
use App\Support\ReferralCode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    $this->admin = User::factory()->create(['status' => UserStatus::Active->value]);
    $this->admin->assignRole('admin');
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

it('changes the referral code and rebuilds the link and QR code', function (): void {
    fakeCustomerReferralsApi([
        '*game-api.test/customers/*/referral-code' => function (Request $request) {
            return $request->method() === 'PUT'
                ? Http::response(['success' => true, 'data' => ['customer_id' => 42, 'code' => 'JANE2026', 'link' => $request['link'], 'qr_code' => $request['qr_code']]])
                : Http::response(['success' => true, 'data' => ['customer_id' => 42, 'code' => '7WMK98TW', 'link' => 'https://kadi.online/register?ref=7WMK98TW', 'qr_code' => REFERRAL_QR_DATA_URI]]);
        },
    ]);
    $this->actingAs($this->admin);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->callAction('changeReferralCode', data: ['code' => 'jane2026'])
        ->assertHasNoActionErrors()
        ->assertNotified('Referral code saved')
        ->assertSee('JANE2026')
        ->assertSee('https://kadi.online/register?ref=JANE2026');

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        $png = base64_decode(Str::after($request['qr_code'], 'data:image/png;base64,'), true);

        return $request['code'] === 'JANE2026'
            && $request['link'] === 'https://kadi.online/register?ref=JANE2026'
            && str_starts_with($request['qr_code'], 'data:image/png;base64,')
            && $png !== false && str_starts_with($png, "\x89PNG")
            && Str::isUuid($request->header('Idempotency-Key')[0] ?? '');
    });

    $log = AuditLog::query()->where('auditable_type', 'KadiApi\ReferralCode')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->auditable_id)->toBe(42)
        ->and($log->event)->toBe('updated')
        ->and($log->new_values['payload']['code'])->toBe('JANE2026')
        ->and($log->new_values['status'])->toBe(200);
});

it('builds the referral link from the configured format', function (): void {
    config(['services.game_api.referral_link' => 'https://kadi.test/join/{code}']);

    expect(ReferralCode::link('abcd12'))->toBe('https://kadi.test/join/ABCD12');
});

it('rejects a code that is not 4 to 20 letters or digits before calling KadiApi', function (string $code): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->admin);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->callAction('changeReferralCode', data: ['code' => $code])
        ->assertHasActionErrors(['code' => 'regex']);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
})->with([
    'too short' => ['AB1'],
    'too long' => ['ABCDEFGHIJ0123456789X'],
    'symbols' => ['JANE-2026'],
]);

it('puts a taken code on the form and retries with a new idempotency key', function (): void {
    $puts = 0;

    fakeCustomerReferralsApi([
        '*game-api.test/customers/*/referral-code' => function (Request $request) use (&$puts) {
            if ($request->method() !== 'PUT') {
                return Http::response(['success' => true, 'data' => ['customer_id' => 42, 'code' => '7WMK98TW', 'link' => null, 'qr_code' => null]]);
            }

            return ++$puts === 1
                ? Http::response(['success' => false, 'message' => 'Referral code is already taken'], 409)
                : Http::response(['success' => true, 'data' => ['customer_id' => 42, 'code' => 'JANE2027']]);
        },
    ]);
    $this->actingAs($this->admin);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->mountAction('changeReferralCode')
        ->fillForm(['code' => 'JANE2026'])
        ->callMountedAction()
        ->assertHasActionErrors(['code' => 'Referral code is already taken'])
        ->fillForm(['code' => 'JANE2027'])
        ->callMountedAction()
        ->assertNotified('Referral code saved');

    $keys = Http::recorded(fn (Request $request): bool => $request->method() === 'PUT')
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0])
        ->unique();

    expect($keys)->toHaveCount(2);
});

it('maps KadiApi validation errors onto the code field', function (): void {
    fakeCustomerReferralsApi([
        '*game-api.test/customers/*/referral-code' => fn (Request $request) => $request->method() === 'PUT'
            ? Http::response(['message' => 'Validation failed', 'errors' => ['code' => ['The code must be 4 to 20 letters or digits.']]], 422)
            : Http::response(['success' => true, 'data' => ['customer_id' => 42, 'code' => '7WMK98TW']]),
    ]);
    $this->actingAs($this->admin);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->callAction('changeReferralCode', data: ['code' => 'JANE2026'])
        ->assertHasActionErrors(['code' => 'The code must be 4 to 20 letters or digits.']);
});

it('hides the change code action from users without the permission', function (): void {
    fakeCustomerReferralsApi();
    $this->actingAs($this->manager);

    Livewire::test(CustomerReferralSummary::class, ['customerId' => 42])
        ->assertSee('7WMK98TW')
        ->assertActionHidden('changeReferralCode');
});
