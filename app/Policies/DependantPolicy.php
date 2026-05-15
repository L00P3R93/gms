<?php

namespace App\Policies;

use App\Models\Dependant;
use App\Models\User;

class DependantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Dependant $dependant): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Dependant $dependant): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Dependant $dependant): bool
    {
        return false;
    }
}
