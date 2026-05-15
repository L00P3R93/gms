<?php

namespace App\Policies;

use App\Models\PlayedGame;
use App\Models\User;

class PlayedGamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, PlayedGame $playedGame): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PlayedGame $playedGame): bool
    {
        return false;
    }

    public function delete(User $user, PlayedGame $playedGame): bool
    {
        return false;
    }
}
