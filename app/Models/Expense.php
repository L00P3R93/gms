<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Auditable;

    protected $table = 'expenses';

    protected $guarded = [];

    protected $casts = [
        'category' => ExpenseCategory::class,
    ];
}
