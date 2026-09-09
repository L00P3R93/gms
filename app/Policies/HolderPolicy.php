<?php

namespace App\Policies;

use App\Models\Holder;
use App\Models\User;

class HolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('holders.view');
    }

    public function view(User $user, Holder $holder): bool
    {
        return $user->hasPermissionTo('holders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('holders.create');
    }

    public function update(User $user, Holder $holder): bool
    {
        return $user->hasPermissionTo('holders.edit');
    }

    public function delete(User $user, Holder $holder): bool
    {
        return false;
    }
}
