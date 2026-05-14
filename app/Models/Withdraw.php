<?php

namespace App\Models;

use App\Enums\WithdrawStatus;
use App\Enums\WithdrawType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdraw extends Model
{
    use Auditable;

    protected $table = 'withdraws';

    protected $guarded = [];

    protected $casts = [
        'status' => WithdrawStatus::class,
        'type' => WithdrawType::class,
    ];

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class, 'receiver_id');
    }

    public function dependant(): BelongsTo
    {
        return $this->belongsTo(Dependant::class, 'receiver_id');
    }

    public function getReceiverNameAttribute(): string
    {
        return $this->type === WithdrawType::Holder
            ? optional($this->holder)->name ?? '—'
            : optional($this->dependant)->name ?? '—';
    }
}
