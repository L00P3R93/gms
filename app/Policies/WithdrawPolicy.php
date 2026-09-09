<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdraw;

class WithdrawPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('withdrawals.view');
    }

    public function view(User $user, Withdraw $withdraw): bool
    {
        return $user->hasPermissionTo('withdrawals.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Withdraw $withdraw): bool
    {
        return false;
    }

    public function delete(User $user, Withdraw $withdraw): bool
    {
        return false;
    }
}
