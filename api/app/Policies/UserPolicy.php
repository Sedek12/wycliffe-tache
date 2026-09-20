<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /** Gestion des comptes : admin (via before) et directeur. */
    public function viewAny(User $user): bool
    {
        return $user->isSupervisor() || $user->isChefSomewhere();
    }

    public function manage(User $user): bool
    {
        return $user->isDirecteur();
    }
}
