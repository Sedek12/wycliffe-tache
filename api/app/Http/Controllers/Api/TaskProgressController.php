<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\ProgressUpdate;
use App\Models\Setting;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskProgressController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorize('updateProgress', $task);

        // Un membre dont la part est verrouillée ne peut plus soumettre : il doit demander une réouverture.
        $me = $task->assignees->firstWhere('id', $request->user()->id);
        if ($me && $me->pivot->is_done) {
            throw ValidationException::withMessages([
                'stage' => ['Votre part est verrouillée. Demandez sa réouverture au responsable.'],
            ]);
        }

        $config = Setting::get('files');
        $maxKb = (int) ($config['max_size_mb'] ?? 50) * 1024;
        $allowed = $config['allowed_extensions'] ?? [];

        // À chaque étape : un document OU une explication (au moins l'un des deux).
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(ProgressUpdate::STAGES))],
            'comment' => ['nullable', 'string', 'required_without:file'],
            'file' => [
                'nullable', 'required_without:comment', 'file', "max:{$maxKb}",
                Rule::when(! empty($allowed), 'mimes:' . implode(',', $allowed)),
            ],
        ], [
            'comment.required_without' => 'Joignez un document ou saisissez une explication.',
            'file.required_without' => 'Joignez un document ou saisissez une explication.',
        ]);

        $stageKey = $data['stage'];
        $percent = ProgressUpdate::STAGES[$stageKey]['percent'];

        $path = null;
        $file = $request->file('file');
        if ($file) {
            $path = $file->store("progress/{$task->id}", 'public');
        }

        DB::transaction(function () use ($task, $data, $request, $file, $path, $stageKey, $percent, $me) {
            $task->progressUpdates()->create([
                'user_id' => $request->user()->id,
                'percent' => $percent,
                'stage' => $stageKey,
                'comment' => $data['comment'] ?? null,
                'disk' => $file ? 'public' : null,
                'path' => $path,
                'original_name' => $file?->getClientOriginalName(),
                'mime' => $file?->getClientMimeType(),
                'size' => $file?->getSize(),
            ]);

            $label = ProgressUpdate::STAGES[$stageKey]['label'];
            \App\Models\TaskActivity::log(
                $task,
                'progress_added',
                "{$request->user()->name} a soumis une version — {$label} ({$percent} %).",
                $request->user(),
            );

            $task->progress = max((int) $task->progress, $percent);

            if ($task->status === TaskStatus::Assignee && $percent > 0) {
                $task->status = TaskStatus::EnCours;
                $task->statusHistory()->create([
                    'from_status' => TaskStatus::Assignee->value,
                    'to_status' => TaskStatus::EnCours->value,
                    'changed_by' => $request->user()->id,
                    'note' => 'Avancement démarré',
                ]);
            }

            $task->save();

            // Livraison finale par un assigné : sa part est verrouillée automatiquement.
            if ($stageKey === 'livraison' && $me) {
                $task->assignees()->updateExistingPivot($request->user()->id, [
                    'is_done' => true,
                    'done_at' => now(),
                    'reopen_requested_at' => null,
                ]);
                \App\Models\TaskActivity::log(
                    $task,
                    'part_completed',
                    "{$request->user()->name} a livré sa part à 100 % (part verrouillée).",
                    $request->user(),
                );
            }
        });

        return $this->fresh($task);
    }

    /** Le responsable / chef marque une version comme revue (ou annule ce marquage). */
    public function review(Request $request, Task $task, ProgressUpdate $progressUpdate)
    {
        $this->authorize('reviewProgress', $task); // pilotage réservé chef / responsable délégué
        abort_unless($progressUpdate->task_id === $task->id, 404);

        if ($progressUpdate->reviewed_at) {
            $progressUpdate->update(['reviewed_at' => null, 'reviewed_by' => null]);
        } else {
            $progressUpdate->update([
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejected_at' => null,
                'rejected_by' => null,
            ]);
            \App\Models\TaskActivity::log(
                $task,
                'progress_reviewed',
                "{$request->user()->name} a marqué une version comme revue.",
                $request->user(),
            );
        }

        return $this->fresh($task);
    }

    /**
     * Le responsable / chef « dévalue » une version : l'auteur devra en soumettre une nouvelle.
     * Si la version rejetée est la livraison à 100 %, la part de l'auteur est rouverte.
     */
    public function reject(Request $request, Task $task, ProgressUpdate $progressUpdate)
    {
        $this->authorize('reviewProgress', $task);
        abort_unless($progressUpdate->task_id === $task->id, 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $actor = $request->user();

        DB::transaction(function () use ($task, $progressUpdate, $actor, $data) {
            $progressUpdate->update([
                'rejected_at' => now(),
                'rejected_by' => $actor->id,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);

            $authorPivot = $task->assignees->firstWhere('id', $progressUpdate->user_id);
            if ($progressUpdate->stage === 'livraison' && $authorPivot?->pivot?->is_done) {
                $task->assignees()->updateExistingPivot($progressUpdate->user_id, [
                    'is_done' => false,
                    'done_at' => null,
                    'reopen_requested_at' => null,
                ]);
            }

            \App\Models\TaskActivity::log(
                $task,
                'progress_rejected',
                "{$actor->name} a dévalué une version" . (! empty($data['note']) ? " — {$data['note']}" : '') . '.',
                $actor,
                \App\Models\TaskActivity::STREAM_AUTHORITY,
            );
        });

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
