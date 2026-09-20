<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isSupervisor()
            || $user->isChefOf($task->department_id)
            || $task->created_by === $user->id
            || $task->isSupervisedBy($user->id)
            || $task->hasAssignee($user->id)
            || ($task->project_id && $user->isMemberOfProject($task->project_id))) {
            return true;
        }

        // Une personne concernée par une demande de collaboration sur cette tâche
        // (émetteur, personne visée, ou chef d'un des deux départements) peut la consulter.
        $ledIds = $user->ledDepartmentIds();

        return $task->collaborationRequests()
            ->where(function ($q) use ($user, $ledIds) {
                $q->where('requested_by', $user->id)
                    ->orWhere('target_user_id', $user->id)
                    ->orWhereIn('to_department_id', $ledIds)
                    ->orWhereIn('from_department_id', $ledIds);
            })
            ->exists();
    }

    /** Le directeur ne crée pas de tâches : seul le chef du département. */
    public function create(User $user): bool
    {
        return $user->isChefSomewhere();
    }

    public function update(User $user, Task $task): bool
    {
        if ($task->status === TaskStatus::Validee) {
            return false;
        }

        return $this->isChefLevel($user, $task)
            || $task->supervisorCan($user->id, Task::ABILITY_MANAGE_TEAM);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task);
    }

    /** Renseigner l'avancement (%) : les assignés, le chef, ou le responsable si le pouvoir est délégué. */
    public function updateProgress(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->hasAssignee($user->id)
            || $task->supervisorCan($user->id, Task::ABILITY_CHANGE_STATUS);
    }

    /** Déposer un livrable : les assignés, le chef, ou le responsable si le pouvoir est délégué. */
    public function submitDeliverable(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->hasAssignee($user->id)
            || $task->supervisorCan($user->id, Task::ABILITY_CHANGE_STATUS);
    }

    /** Faire évoluer le statut. Le détail des transitions est vérifié dans le service. */
    public function changeStatus(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->hasAssignee($user->id)
            || $task->supervisorCan($user->id, Task::ABILITY_CHANGE_STATUS);
    }

    /** Valider + évaluer : uniquement le chef (ou responsable de projet) qui a créé la tâche. */
    public function evaluate(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            && $this->isChefLevel($user, $task);
    }

    /** Constituer l'équipe : chef, ou responsable si « gérer l'équipe » est délégué. */
    public function manageTeam(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->supervisorCan($user->id, Task::ABILITY_MANAGE_TEAM);
    }

    /** Envoyer une demande de collaboration : chef, ou responsable si le pouvoir est délégué. */
    public function requestCollaboration(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->supervisorCan($user->id, Task::ABILITY_REQUEST_COLLABORATION);
    }

    /** Créer une sous-tâche : chef, ou responsable si le pouvoir est délégué. */
    public function createSubtask(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->supervisorCan($user->id, Task::ABILITY_CREATE_SUBTASKS);
    }

    /**
     * Piloter les versions déposées (marquer revue / dévaluer) : chef, ou responsable
     * si le pouvoir est délégué. Contrairement à changeStatus(), un simple assigné
     * ne peut pas piloter les versions de ses coéquipiers.
     */
    public function reviewProgress(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task)
            || $task->supervisorCan($user->id, Task::ABILITY_CHANGE_STATUS);
    }

    /** Gérer les dépendances entre tâches (planification) : chef / responsable de projet uniquement. */
    public function manageDependencies(User $user, Task $task): bool
    {
        return $this->isChefLevel($user, $task);
    }

    /**
     * Chef du département de la tâche, ou responsable managérial (rôle « chef »)
     * du projet auquel elle appartient (l'admin passe déjà par before()).
     */
    private function isChefLevel(User $user, Task $task): bool
    {
        return $user->isChefOf($task->department_id)
            || ($task->project_id && $user->isProjectManagerOf($task->project_id));
    }
}
