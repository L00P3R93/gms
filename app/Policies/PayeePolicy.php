<?php

namespace App\Policies;

use App\Models\Payee;
use App\Models\User;

class PayeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('payees.view');
    }

    public function view(User $user, Payee $payee): bool
    {
        return $user->hasPermissionTo('payees.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('payees.create');
    }

    public function update(User $user, Payee $payee): bool
    {
        return $user->hasPermissionTo('payees.edit');
    }

    public function delete(User $user, Payee $payee): bool
    {
        return $user->hasPermissionTo('payees.delete');
    }
}
