<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Toy extends Model
{
    protected $table = 'toys';

    public $timestamps = false;

    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'user_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'item_id');
    }
}
