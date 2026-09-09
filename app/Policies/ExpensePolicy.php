<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expenses.edit');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('expenses.delete');
    }
}
