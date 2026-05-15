<?php

namespace App\Policies;

use App\Models\Holder;
use App\Models\User;

class HolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Holder $holder): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Holder $holder): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Holder $holder): bool
    {
        return false;
    }
}
