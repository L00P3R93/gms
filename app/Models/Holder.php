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

    /**
     * Set the phone attribute - convert 0 prefix to 254 for storage
     */
    public function setPhoneAttribute($value): void
    {
        $phone = trim($value);
        // If phone starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }
        $this->attributes['phone'] = $phone;
    }
}
