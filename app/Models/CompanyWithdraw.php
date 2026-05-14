<?php

namespace App\Models;

use App\Enums\CompanyWithdrawStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyWithdraw extends Model
{
    use Auditable;

    protected $table = 'company_withdraws';

    protected $guarded = [];

    protected $casts = [
        'status' => CompanyWithdrawStatus::class,
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
