<?php

namespace App\Policies;

use App\Models\Dependant;
use App\Models\User;

class DependantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('dependants.view');
    }

    public function view(User $user, Dependant $dependant): bool
    {
        return $user->hasPermissionTo('dependants.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('dependants.create');
    }

    public function update(User $user, Dependant $dependant): bool
    {
        return $user->hasPermissionTo('dependants.edit');
    }

    public function delete(User $user, Dependant $dependant): bool
    {
        return $user->hasPermissionTo('dependants.delete');
    }
}
