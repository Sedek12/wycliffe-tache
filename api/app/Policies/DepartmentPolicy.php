<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /** L'admin passe toutes les barrières. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Department $department): bool
    {
        return $user->isSupervisor()
            || $user->departments->contains('id', $department->id)
            || $user->departments()->where('departments.id', $department->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isDirecteur();
    }

    public function update(User $user, Department $department): bool
    {
        return $user->isDirecteur();
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->isDirecteur();
    }

    /** Ajouter / retirer des membres et nommer les chefs. */
    public function manageMembers(User $user, Department $department): bool
    {
        return $user->isDirecteur();
    }
}
