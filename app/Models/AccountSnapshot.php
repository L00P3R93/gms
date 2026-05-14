<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSnapshot extends Model
{
    protected $table = 'account_snapshots';

    public $timestamps = false;

    protected $guarded = [];

    const TYPE_MPESA_BALANCE = 1;

    const TYPE_REFERRAL_TOTAL = 2;
}
