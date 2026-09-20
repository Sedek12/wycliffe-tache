<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWorkflow
{
    /**
     * Applique une transition de statut, journalise l'historique et notifie.
     *
     * @throws ValidationException si la transition n'est pas autorisée
     */
    public function transition(Task $task, TaskStatus $target, User $actor, ?string $note = null): Task
    {
        $current = $task->status;

        if ($current === $target) {
            return $task;
        }

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transition impossible : « {$current->label()} » → « {$target->label()} ».",
            ]);
        }

        // Planification : impossible de démarrer une tâche tant que ses dépendances
        // ne sont pas validées.
        if ($target === TaskStatus::EnCours) {
            $blocking = $task->unmetDependencies();
            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'Bloquée par : ' . $blocking->pluck('title')->implode(', ') . '. Ces tâches doivent être validées avant de démarrer.',
                ]);
            }
        }

        // Un assigné (non chef, non responsable délégué) ne peut que livrer une tâche en cours.
        $isManager = $actor->isChefOf($task->department_id)
            || $actor->isSupervisor()
            || $task->supervisorCan($actor->id, Task::ABILITY_CHANGE_STATUS);
        if (! $isManager) {
            $allowedForAssignee = $current === TaskStatus::EnCours && $target === TaskStatus::Livree;
            if (! $allowedForAssignee) {
                throw ValidationException::withMessages([
                    'status' => "Vous ne pouvez pas effectuer cette transition.",
                ]);
            }
        }

        return DB::transaction(function () use ($task, $current, $target, $actor, $note) {
            $task->status = $target;

            if ($target === TaskStatus::Validee) {
                $task->completed_at = now();
                $task->progress = 100;
            }

            if ($target === TaskStatus::Livree && $task->progress < 100) {
                $task->progress = 100;
            }

            if ($target === TaskStatus::EnCours) {
                $task->completed_at = null;
            }

            $task->save();

            $task->statusHistory()->create([
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by' => $actor->id,
                'note' => $note,
            ]);

            $authority = in_array($target, [TaskStatus::Annulee, TaskStatus::ARefaire], true);
            $verb = match ($target) {
                TaskStatus::Annulee => 'a annulé la tâche',
                TaskStatus::ARefaire => 'a renvoyé la tâche à refaire',
                default => "a fait passer la tâche à « {$target->label()} »",
            };
            \App\Models\TaskActivity::log(
                $task,
                $target === TaskStatus::Annulee ? 'task_cancelled' : 'status_changed',
                "{$actor->name} {$verb}." . ($note ? " Note : {$note}" : ''),
                $actor,
                $authority ? \App\Models\TaskActivity::STREAM_AUTHORITY : \App\Models\TaskActivity::STREAM_ACTIVITY,
            );

            $recipients = $task->assignees()
                ->where('users.id', '!=', $actor->id)
                ->get()
                ->push($task->creator)
                ->filter()
                ->unique('id')
                ->reject(fn (User $u) => $u->id === $actor->id);

            foreach ($recipients as $recipient) {
                $recipient->notify(new TaskStatusChanged($task, $current, $target, $actor));
            }

            return $task;
        });
    }
}
