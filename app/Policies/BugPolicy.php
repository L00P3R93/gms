<?php

namespace App\Policies;

use App\Models\Bug;
use App\Models\User;

class BugPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Bug $bug): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Bug $bug): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Bug $bug): bool
    {
        return $user->hasRole('super-admin');
    }
}
