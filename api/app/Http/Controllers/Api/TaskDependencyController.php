<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskDependencyController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorize('manageDependencies', $task);

        $data = $request->validate([
            'depends_on_task_id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $dependsOnId = (int) $data['depends_on_task_id'];

        if ($dependsOnId === $task->id) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => ['Une tâche ne peut pas dépendre d\'elle-même.'],
            ]);
        }

        $dependsOn = Task::findOrFail($dependsOnId);

        $sameGroup = $task->project_id
            ? $dependsOn->project_id === $task->project_id
            : $dependsOn->department_id === $task->department_id;

        if (! $sameGroup) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => ['La dépendance doit appartenir au même projet (ou département).'],
            ]);
        }

        // Empêche un cycle direct A -> B -> A (la détection de cycles transitifs
        // plus complexes est laissée pour une itération future).
        if ($dependsOn->dependsOn()->where('depends_on_task_id', $task->id)->exists()) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => ['Cette dépendance créerait un cycle.'],
            ]);
        }

        $task->dependsOn()->syncWithoutDetaching([$dependsOnId]);

        return new TaskResource($task->load('dependsOn'));
    }

    public function destroy(Task $task, Task $dependsOnTask)
    {
        $this->authorize('manageDependencies', $task);

        $task->dependsOn()->detach($dependsOnTask->id);

        return new TaskResource($task->load('dependsOn'));
    }
}
