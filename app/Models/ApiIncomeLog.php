<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIncomeLog extends Model
{
    protected $table = 'api_income_logs';

    protected $fillable = [
        'api_total',
        'raw_response',
        'business_date',
    ];

    protected $casts = [
        'api_total' => 'decimal:4',
        'raw_response' => 'array',
        'business_date' => 'date',
    ];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }
}
