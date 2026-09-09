<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('payouts.view');
    }

    public function view(User $user, Payout $payout): bool
    {
        return $user->hasPermissionTo('payouts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('payouts.create');
    }

    public function update(User $user, Payout $payout): bool
    {
        return false;
    }

    public function delete(User $user, Payout $payout): bool
    {
        return $user->hasPermissionTo('payouts.view');
    }

    public function approve(User $user, Payout $payout): bool
    {
        return $user->hasPermissionTo('payouts.approve');
    }

    public function decline(User $user, Payout $payout): bool
    {
        return $user->hasPermissionTo('payouts.decline');
    }
}
