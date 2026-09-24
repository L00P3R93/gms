<?php

use App\Models\MpesaAccountBalance;
use App\Services\MpesaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Safaricom AccountBalance result as the balance callback receives it.
 *
 * @return array<string, mixed>
 */
function accountBalanceResult(float $utility, string $conversationId = 'AG_20260924_0001'): array
{
    return [
        'ResultCode' => 0,
        'ConversationID' => $conversationId,
        'ResultParameters' => ['ResultParameter' => [
            ['Key' => 'AccountBalance', 'Value' => "Working Account|KES|10.00|10.00|0.00|0.00&Utility Account|KES|{$utility}|{$utility}|0.00|0.00"],
        ]],
    ];
}

it('keeps one row per balance type and updates it on every callback', function (): void {
    $this->travelTo('2026-09-24 10:00:00');
    $this->postJson('/b2c/balance/result', ['Result' => accountBalanceResult(9023, 'AG_1')])->assertOk();

    $this->travelTo('2026-09-24 11:00:00');
    $this->postJson('/b2c/balance/result', ['Result' => accountBalanceResult(8500.5, 'AG_2')])->assertOk();

    $balance = MpesaAccountBalance::sole();
    expect($balance->type)->toBe('b2c')
        ->and($balance->utility_account_balance)->toBe('8500.50')
        ->and($balance->working_account_balance)->toBe('10.00')
        ->and($balance->conversation_id)->toBe('AG_2')
        ->and($balance->fetched_at->toDateTimeString())->toBe('2026-09-24 11:00:00');
});

it('keeps only the newest row per type when the migration runs', function (): void {
    Schema::table('mpesa_account_balances', fn (Blueprint $table) => $table->dropUnique(['type']));

    $rows = [
        ['type' => 'b2c', 'utility_account_balance' => 100, 'fetched_at' => '2026-09-24 08:00:00'],
        ['type' => 'b2c', 'utility_account_balance' => 300, 'fetched_at' => '2026-09-24 10:00:00'],
        ['type' => 'b2c', 'utility_account_balance' => 200, 'fetched_at' => '2026-09-24 09:00:00'],
        ['type' => 'c2b', 'utility_account_balance' => 50, 'fetched_at' => '2026-09-24 07:00:00'],
        ['type' => 'c2b', 'utility_account_balance' => 60, 'fetched_at' => '2026-09-24 10:00:00'],
    ];
    DB::table('mpesa_account_balances')->insert($rows);

    (require database_path('migrations/2026_09_24_223256_keep_one_mpesa_account_balance_row_per_type.php'))->up();

    expect(DB::table('mpesa_account_balances')->orderBy('type')->pluck('utility_account_balance', 'type')->map(fn ($amount): float => (float) $amount)->all())
        ->toBe(['b2c' => 300.0, 'c2b' => 60.0]);
});

it('requests only the B2C balance and leaves storing it to the callback', function (): void {
    $this->mock(MpesaService::class)
        ->shouldReceive('b2cAccountBalance')->once()->andReturn([
            'OriginatorConversationID' => 'abc-123',
            'ConversationID' => 'AG_20260924_0001',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ])
        ->shouldNotReceive('c2bAccountBalance');

    $this->artisan('mpesa:fetch-balances')->assertSuccessful();

    expect(MpesaAccountBalance::count())->toBe(0);
});

it('fails the fetch when Safaricom rejects the balance request', function (): void {
    $this->mock(MpesaService::class)
        ->shouldReceive('b2cAccountBalance')->once()->andReturn([
            'ResponseCode' => '1',
            'ResponseDescription' => 'The initiator information is invalid.',
        ]);

    $this->artisan('mpesa:fetch-balances')
        ->expectsOutputToContain('The initiator information is invalid.')
        ->assertFailed();
});
