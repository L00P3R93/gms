<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPicViolation extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_email',
        'violation_count',
    ];

    protected function casts(): array
    {
        return [
            'violation_count' => 'integer',
        ];
    }
}
