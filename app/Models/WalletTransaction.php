<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'holder_id',
        'distribution_id',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'transaction_type',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class, 'holder_id');
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(IncomeDistribution::class, 'distribution_id');
    }

    public function scopeCredits($query)
    {
        return $query->where('transaction_type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('transaction_type', 'debit');
    }
}
