<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeProcessingState extends Model
{
    protected $table = 'income_processing_state';

    protected $fillable = [
        'business_date',
        'last_processed_total',
        'last_api_total',
        'last_checked_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'last_processed_total' => 'decimal:4',
        'last_api_total' => 'decimal:4',
        'last_checked_at' => 'datetime',
    ];

    /**
     * Get or create the processing state for the current business date.
     * Note: Row locking should be handled by the calling service within a transaction.
     */
    public static function forToday(): self
    {
        return self::firstOrCreate(
            ['business_date' => now()->toDateString()],
            [
                'last_processed_total' => 0,
                'last_api_total' => 0,
                'last_checked_at' => now(),
            ]
        );
    }

    /**
     * Reset the processing state for a new business day.
     */
    public function resetForNewDay(): void
    {
        $this->update([
            'last_processed_total' => 0,
            'last_api_total' => 0,
            'last_checked_at' => now(),
        ]);
    }
}
