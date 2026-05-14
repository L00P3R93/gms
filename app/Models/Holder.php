<?php

namespace App\Models;

use App\Enums\HolderStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Holder extends Model
{
    use Auditable;

    protected $table = 'holders';

    protected $guarded = [];

    protected $casts = [
        'status' => HolderStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(HolderWallet::class, 'holder_id');
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class, 'holder_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdraw::class, 'receiver_id')->where('type', 'holder');
    }

    public function getSharePercentAttribute(): float
    {
        return $this->share * 100;
    }
}
