<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Notifications\DeliverableSubmitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliverableController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorize('submitDeliverable', $task);

        // Un membre dont la part est validée ne peut plus téléverser.
        $me = $task->assignees->firstWhere('id', $request->user()->id);
        if ($me && $me->pivot->is_done) {
            throw ValidationException::withMessages([
                'file' => ['Votre part est validée. Demandez sa réouverture au responsable pour ajouter un fichier.'],
            ]);
        }

        $config = Setting::get('files');
        $maxKb = (int) ($config['max_size_mb'] ?? 50) * 1024;
        $allowed = $config['allowed_extensions'] ?? [];

        $data = $request->validate([
            'file' => [
                'required', 'file', "max:{$maxKb}",
                Rule::when(! empty($allowed), 'mimes:' . implode(',', $allowed)),
            ],
            'note' => ['nullable', 'string'],
        ]);

        $file = $data['file'];
        $path = $file->store("deliverables/{$task->id}", 'public');

        $deliverable = $task->deliverables()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'note' => $data['note'] ?? null,
        ]);

        \App\Models\TaskActivity::log(
            $task,
            'deliverable_added',
            "{$request->user()->name} a publié le livrable « {$deliverable->original_name} ».",
            $request->user(),
        );

        // Notifie le chef créateur (s'il n'est pas l'auteur du dépôt).
        if ($task->creator && $task->creator->id !== $request->user()->id) {
            $task->creator->notify(new DeliverableSubmitted($task, $deliverable, $request->user()));
        }

        return new TaskResource($task->load([
            'department', 'creator', 'supervisor', 'assignees',
            'progressUpdates.user', 'progressUpdates.reviewer',
            'deliverables.uploader',
            'statusHistory.changedBy', 'evaluation.evaluator', 'activities.actor',
        ]));
    }

    /** Dépose un document au niveau du projet (bibliothèque documentaire), avec versioning. */
    public function storeForProject(Request $request, Project $project)
    {
        $this->authorize('manageDocuments', $project);

        $config = Setting::get('files');
        $maxKb = (int) ($config['max_size_mb'] ?? 50) * 1024;
        $allowed = $config['allowed_extensions'] ?? [];

        $data = $request->validate([
            'file' => [
                'required', 'file', "max:{$maxKb}",
                Rule::when(! empty($allowed), 'mimes:' . implode(',', $allowed)),
            ],
            'note' => ['nullable', 'string'],
            'replaces_id' => ['nullable', 'exists:deliverables,id'],
        ]);

        $file = $data['file'];
        $path = $file->store("deliverables/projects/{$project->id}", 'public');

        $groupKey = null;
        $version = 1;
        if (! empty($data['replaces_id'])) {
            $previous = Deliverable::where('project_id', $project->id)->findOrFail($data['replaces_id']);
            $groupKey = $previous->group_key;
            $version = (int) Deliverable::where('group_key', $groupKey)->max('version') + 1;
        }

        $deliverable = $project->deliverables()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'note' => $data['note'] ?? null,
            'group_key' => $groupKey,
            'version' => $version,
        ]);

        return new ProjectResource($project->load('deliverables.uploader'));
    }

    public function destroyForProject(Project $project, Deliverable $deliverable)
    {
        $this->authorize('manageDocuments', $project);
        abort_unless($deliverable->project_id === $project->id, 404);

        Storage::disk($deliverable->disk)->delete($deliverable->path);
        $deliverable->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }

    public function destroy(Task $task, Deliverable $deliverable)
    {
        $this->authorize('submitDeliverable', $task);
        abort_unless($deliverable->task_id === $task->id, 404);

        // L'auteur du dépôt, un chef du département, le responsable de la tâche, ou un admin/directeur.
        $user = request()->user();
        abort_unless(
            $deliverable->uploaded_by === $user->id
                || $user->isChefOf($task->department_id)
                || $task->isSupervisedBy($user->id)
                || $user->isSupervisor(),
            403
        );

        $byOwner = $deliverable->uploaded_by === $user->id;
        Storage::disk($deliverable->disk)->delete($deliverable->path);
        $name = $deliverable->original_name;
        $deliverable->delete();

        \App\Models\TaskActivity::log(
            $task,
            'deliverable_removed',
            "{$user->name} a retiré le livrable « {$name} »" . ($byOwner ? '.' : ' (action du responsable).'),
            $user,
            $byOwner ? \App\Models\TaskActivity::STREAM_ACTIVITY : \App\Models\TaskActivity::STREAM_AUTHORITY,
        );

        return response()->json(['message' => 'Livrable supprimé.']);
    }
}
