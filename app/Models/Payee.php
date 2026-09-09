<?php

namespace App\Models;

use App\Enums\PayeeStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payee extends Model
{
    use Auditable;

    protected $table = 'payees';

    protected $guarded = [];

    protected $casts = [
        'status' => PayeeStatus::class,
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'payee_id');
    }

    public function setPhoneAttribute($value): void
    {
        $phone = trim($value);
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }
        $this->attributes['phone'] = $phone;
    }
}
