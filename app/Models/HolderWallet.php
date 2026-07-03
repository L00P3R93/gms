<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolderWallet extends Model
{
    protected $table = 'holders_wallet';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:4',
    ];

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class, 'holder_id');
    }
}
