<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'accounts';

    protected $guarded = [];

    const STATUS_HIDDEN = 0;

    const STATUS_ACTIVE = 1;

    public function toys(): HasMany
    {
        return $this->hasMany(Toy::class, 'user_id');
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class, 'user_id');
    }

    public function getEncryptedIdAttribute(): string
    {
        return app(EncryptionService::class)->encrypt($this->id);
    }
}
