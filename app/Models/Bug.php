<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bug extends Model
{
    protected $table = 'bugs';

    protected $guarded = [];

    const STATUS_PENDING = 0;

    const STATUS_PROCESSING = 1;

    const STATUS_DUPLICATE = 3;

    const STATUS_NOT_A_BUG = 4;

    const STATUS_HIDDEN = 5;

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'user_id');
    }
}
