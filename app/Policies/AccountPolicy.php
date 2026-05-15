<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'agent']);
    }

    public function view(User $user, Account $account): bool
    {
        return $user->hasAnyRole(['super-admin', 'agent']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Account $account): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Account $account): bool
    {
        return false;
    }
}
