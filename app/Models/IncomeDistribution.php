<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeDistribution extends Model
{
    protected $table = 'income_distributions';

    protected $fillable = [
        'previous_total',
        'current_total',
        'delta',
        'processed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'previous_total' => 'decimal:4',
        'current_total' => 'decimal:4',
        'delta' => 'decimal:4',
        'processed_at' => 'datetime',
    ];

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'distribution_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
