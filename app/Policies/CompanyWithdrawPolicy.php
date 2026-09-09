<?php

namespace App\Policies;

use App\Models\CompanyWithdraw;
use App\Models\User;

class CompanyWithdrawPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('company-withdrawals.view');
    }

    public function view(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return $user->hasPermissionTo('company-withdrawals.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('company-withdrawals.create');
    }

    public function update(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return false;
    }

    public function delete(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return false;
    }

    public function approve(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return $user->hasPermissionTo('company-withdrawals.approve');
    }
}
