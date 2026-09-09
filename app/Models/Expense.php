<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia;

    protected $table = 'expenses';

    protected $guarded = [];

    protected $casts = [
        'category' => ExpenseCategory::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
            ]);
    }
}
