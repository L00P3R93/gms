<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->storeAuditLog('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = $model->getOriginal();
            $newValues = $model->getChanges();
            unset($oldValues['updated_at'], $newValues['updated_at']);

            if (! empty($newValues)) {
                $model->storeAuditLog('updated', $oldValues, $newValues);
            }
        });

        static::deleted(function ($model) {
            $model->storeAuditLog('deleted', $model->getOriginal(), []);
        });
    }

    protected function storeAuditLog(string $event, array $oldValues, array $newValues): void
    {
        $userId = null;

        if (auth()->check()) {
            $userId = auth()->id();
        } else {
            try {
                if (Schema::hasTable('users') && User::where('id', 1)->exists()) {
                    $userId = 1;
                }
            } catch (\Throwable $e) {
                $userId = null;
            }
        }

        AuditLog::create([
            'user_id' => $userId,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
