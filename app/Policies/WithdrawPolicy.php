<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdraw;

class WithdrawPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Withdraw $withdraw): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Withdraw $withdraw): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Withdraw $withdraw): bool
    {
        return false;
    }
}
