<?php

namespace App\Traits;

trait SuperAdminAccess
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'admin']) ?? false;
    }
}
