<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyWallet extends Model
{
    protected $table = 'company_wallet';

    public $timestamps = false;

    protected $guarded = [];

    const MAIN_WALLET = 1;

    const REFERRAL_WALLET = 2;
}
