<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $table = 'shop';

    public $timestamps = false;

    protected $guarded = [];

    public function toys(): HasMany
    {
        return $this->hasMany(Toy::class, 'item_id');
    }
}
