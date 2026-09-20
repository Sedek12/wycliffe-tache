<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskAssigneeController extends Controller
{
    /** L'assigné valide sa part (« j'ai terminé »). */
    public function completeMyPart(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->assignees->contains('id', $user->id), 403);

        $hasProof = $task->progressUpdates()->where('user_id', $user->id)->exists()
            || $task->deliverables()->where('uploaded_by', $user->id)->exists();

        if (! $hasProof) {
            throw ValidationException::withMessages([
                'part' => ['Déposez au moins une preuve (point d’avancement ou livrable) avant de valider votre part.'],
            ]);
        }

        $task->assignees()->updateExistingPivot($user->id, ['is_done' => true, 'done_at' => now()]);

        \App\Models\TaskActivity::log($task, 'part_completed', "{$user->name} a validé sa part (« j'ai terminé »).", $user);

        return $this->fresh($task);
    }

    /** L'assigné demande la réouverture de sa part verrouillée. */
    public function requestReopen(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->assignees->contains('id', $user->id), 403);

        $pivot = $task->assignees->firstWhere('id', $user->id)->pivot;
        abort_unless($pivot->is_done, 422, 'Votre part n’est pas verrouillée.');

        $task->assignees()->updateExistingPivot($user->id, ['reopen_requested_at' => now()]);

        \App\Models\TaskActivity::log(
            $task,
            'reopen_requested',
            "{$user->name} demande la réouverture de sa part.",
            $user,
            \App\Models\TaskActivity::STREAM_AUTHORITY,
        );

        return $this->fresh($task);
    }

    /** Le responsable / chef rouvre la part d'un membre. */
    public function reopen(Request $request, Task $task, User $user)
    {
        $actor = $request->user();
        abort_unless(
            $actor->isSupervisor()
                || $actor->isChefOf($task->department_id)
                || $task->supervisorCan($actor->id, Task::ABILITY_CHANGE_STATUS),
            403,
        );
        abort_unless($task->assignees->contains('id', $user->id), 404);

        $task->assignees()->updateExistingPivot($user->id, [
            'is_done' => false,
            'done_at' => null,
            'reopen_requested_at' => null,
        ]);

        \App\Models\TaskActivity::log(
            $task,
            'part_reopened',
            "{$actor->name} a rouvert la part de {$user->name}.",
            $actor,
            \App\Models\TaskActivity::STREAM_AUTHORITY,
        );

        return $this->fresh($task);
    }

    private function fresh(Task $task)
    {
        return new TaskResource($task->load([
            'department', 'creator', 'supervisor', 'assignees',
            'progressUpdates.user', 'progressUpdates.reviewer', 'progressUpdates.rejecter',
            'deliverables.uploader',
            'statusHistory.changedBy', 'evaluation.evaluator', 'activities.actor',
        ]));
    }
}
