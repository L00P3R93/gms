<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MpesaAccountBalance extends Model
{
    protected $table = 'mpesa_account_balances';

    protected $fillable = [
        'type',
        'conversation_id',
        'originator_conversation_id',
        'transaction_id',
        'working_account_balance',
        'utility_account_balance',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'working_account_balance' => 'decimal:2',
            'utility_account_balance' => 'decimal:2',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeLatestOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->latest('fetched_at');
    }

    // -------------------------------------------------------------------------
    // Parsing
    // -------------------------------------------------------------------------

    /**
     * Parse the Safaricom AccountBalance response and store it.
     *
     * The ResultParameters contain an "AccountBalance" key with a value like:
     *   "Working Account|KES|0.00|0.00|0.00|0.00&Utility Account|KES|9023.00|9023.00|0.00|0.00&..."
     *
     * Each account entry is pipe-delimited:
     *   [0] Account Name
     *   [1] Currency
     *   [2] Book Balance
     *   [3] Available Balance  ← we store this one
     *   [4] Reserved
     *   [5] Last updated
     */
    public static function storeFromCallback(string $type, array $result): ?self
    {
        $params = $result['ResultParameters']['ResultParameter'] ?? [];

        $balanceString = null;
        foreach ($params as $param) {
            if (($param['Key'] ?? '') === 'AccountBalance') {
                $balanceString = $param['Value'] ?? null;
                break;
            }
        }

        if (! $balanceString) {
            return null;
        }

        $parsed = self::parseAccountBalanceString($balanceString);

        return self::create([
            'type' => $type,
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'transaction_id' => $result['TransactionID'] ?? null,
            'working_account_balance' => $parsed['Working Account'] ?? 0,
            'utility_account_balance' => $parsed['Utility Account'] ?? 0,
            'raw_response' => $result,
            'fetched_at' => now(),
        ]);
    }

    /**
     * Parse the pipe-and-ampersand delimited balance string.
     *
     * @return array {Working Account: float, Utility Account: float}
     */
    public static function parseAccountBalanceString(string $balanceString): array
    {
        $accounts = [];

        $entries = explode('&', $balanceString);

        foreach ($entries as $entry) {
            $parts = explode('|', $entry);
            if (count($parts) >= 4) {
                $name = trim($parts[0]);
                $availableBalance = (float) trim($parts[3]);
                $accounts[$name] = $availableBalance;
            }
        }

        return $accounts;
    }
}
