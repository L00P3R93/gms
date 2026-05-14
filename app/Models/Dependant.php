<?php

namespace App\Models;

use App\Enums\DependantStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dependant extends Model
{
    use Auditable;

    protected $table = 'dependants';

    protected $guarded = [];

    protected $casts = [
        'status' => DependantStatus::class,
    ];

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class, 'holder_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdraw::class, 'receiver_id')->where('type', 'dependant');
    }

    public function getSharePercentAttribute(): float
    {
        return $this->share * 100;
    }
}
