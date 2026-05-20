<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    public $timestamps = false;

    const STATUS_HIDDEN = 0;

    const STATUS_ACTIVE = 1;
}
