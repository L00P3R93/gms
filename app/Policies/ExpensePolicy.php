<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasRole('super-admin');
    }
}
