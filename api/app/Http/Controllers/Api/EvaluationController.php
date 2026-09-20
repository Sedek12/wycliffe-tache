<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorize('evaluate', $task);

        if (! in_array($task->status, [TaskStatus::Livree, TaskStatus::Validee], true)) {
            throw ValidationException::withMessages([
                'task' => ['La tâche doit être livrée avant d\'être évaluée.'],
            ]);
        }

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:20'],
            'appreciation' => ['nullable', 'string'],
        ]);

        $evaluation = $task->evaluation()->updateOrCreate([], [
            'evaluated_by' => $request->user()->id,
            'score' => $data['score'],
            'appreciation' => $data['appreciation'] ?? null,
        ]);

        return new TaskResource($task->load([
            'department', 'creator', 'supervisor', 'assignees',
            'progressUpdates.user', 'progressUpdates.reviewer', 'deliverables.uploader',
            'statusHistory.changedBy', 'evaluation.evaluator', 'activities.actor',
        ]));
    }
}
