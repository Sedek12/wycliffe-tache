<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * L'admin ET le directeur ont un accès total aux projets (création, cadrage,
     * planification, documents, budget, membres, suppression) — contrairement aux
     * tâches, où le directeur ne crée pas mais supervise seulement.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSupervisor() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isSupervisor()
            || $user->isChefOf($project->department_id)
            || $project->created_by === $user->id
            || $user->isMemberOfProject($project->id);
    }

    /**
     * Chef de département, ou toute personne ayant déjà un rôle managérial
     * (Chef service, Coordonnateur/Facilitateur, Facilitateur de zone, Moniteur)
     * sur au moins un projet existant — aligné sur le CDC. (Admin et directeur
     * passent déjà par before() et n'atteignent jamais ce code.)
     */
    public function create(User $user): bool
    {
        return $user->isChefSomewhere() || $this->hasManagerialProjectRoleSomewhere($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isChefOf($project->department_id);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    public function manageMilestones(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    public function manageDependencies(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    public function manageBudget(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    public function manageDocuments(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    /** Créer une activité (tâche racine) sous ce projet. */
    public function createActivity(User $user, Project $project): bool
    {
        return $this->isManagerLevel($user, $project);
    }

    /** Chef du département du projet, ou rôle managérial sur le projet (l'admin passe déjà par before()). */
    private function isManagerLevel(User $user, Project $project): bool
    {
        return $user->isChefOf($project->department_id) || $project->isManagedBy($user->id);
    }

    private function hasManagerialProjectRoleSomewhere(User $user): bool
    {
        $roles = array_map(fn (ProjectRole $r) => $r->value, ProjectRole::managerial());

        return $user->projects()->wherePivotIn('role', $roles)->exists();
    }
}
