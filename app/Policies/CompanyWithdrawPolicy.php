<?php

namespace App\Policies;

use App\Models\CompanyWithdraw;
use App\Models\User;

class CompanyWithdrawPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, CompanyWithdraw $companyWithdraw): bool
    {
        return false;
    }
}
