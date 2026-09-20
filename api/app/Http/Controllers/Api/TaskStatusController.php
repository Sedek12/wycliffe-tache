<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskStatusController extends Controller
{
    public function update(Request $request, Task $task, TaskWorkflow $workflow)
    {
        $this->authorize('changeStatus', $task);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'note' => ['nullable', 'string'],
        ]);

        $workflow->transition(
            $task,
            TaskStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
        );

        return new TaskResource($task->load([
            'department', 'creator', 'supervisor', 'assignees',
            'progressUpdates.user', 'progressUpdates.reviewer', 'deliverables.uploader',
            'statusHistory.changedBy', 'evaluation.evaluator', 'activities.actor',
        ]));
    }
}
