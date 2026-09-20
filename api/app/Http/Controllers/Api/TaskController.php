<?php

namespace App\Http\Controllers\Api;

use App\Enums\DepartmentRole;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();

        $tasks = Task::query()
            ->visibleTo($user)
            ->with(['department', 'creator', 'supervisor', 'assignees', 'evaluation', 'parent:id,title'])
            ->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->filled('assignee_id'), fn ($q) => $q->whereHas(
                'assignees',
                fn ($a) => $a->where('users.id', $request->integer('assignee_id'))
            ))
            ->when($request->boolean('mine'), fn ($q) => $q->whereHas(
                'assignees',
                fn ($a) => $a->where('users.id', $user->id)
            ))
            ->when($request->boolean('overdue'), fn ($q) => $q
                ->where('due_at', '<', now())
                ->whereNotIn('status', [TaskStatus::Validee->value]))
            ->orderByRaw("FIELD(status, 'a_refaire','en_cours','assignee','livree','brouillon','validee')")
            ->orderBy('due_at')
            ->paginate($request->integer('per_page', 20));

        return TaskResource::collection($tasks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => ['required_without_all:parent_id,project_id', 'exists:departments,id'],
            'parent_id' => ['nullable', 'exists:tasks,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after:starts_at'],
            'is_team' => ['boolean'],
            'supervisor_id' => ['nullable', 'exists:users,id'],
            'delegated_abilities' => ['nullable', 'array'],
            'delegated_abilities.*' => [Rule::in(Task::DELEGATABLE_ABILITIES)],
            'assignees' => ['array'],
            'assignees.*.user_id' => ['required', 'distinct', 'exists:users,id'],
            'assignees.*.instructions' => ['nullable', 'string'],
            'assignees.*.estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        // Trois cas : sous-tâche (hérite du parent), activité de projet, tâche de département.
        $parent = null;
        $project = null;
        if (! empty($data['parent_id'])) {
            $parent = Task::findOrFail($data['parent_id']);
            $this->authorize('createSubtask', $parent);
            $data['department_id'] = $parent->department_id; // hérité du parent
            $data['project_id'] = $parent->project_id; // une sous-activité reste dans le même projet
        } elseif (! empty($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            $this->authorize('createActivity', $project);
            $data['department_id'] = $project->department_id;
        } else {
            $this->authorize('create', Task::class);
            if (! $user->isChefOf($data['department_id']) && ! $user->isAdmin()) {
                throw ValidationException::withMessages([
                    'department_id' => ["Vous n'êtes pas chef de ce département."],
                ]);
            }
        }

        // Seul un chef (ou l'admin, ou un responsable managérial du projet) fixe les pouvoirs délégués.
        $isChefLevel = $user->isAdmin()
            || $user->isChefOf($data['department_id'])
            || ($project && $user->isProjectManagerOf($project->id));
        $abilities = $isChefLevel
            ? array_values(array_unique($data['delegated_abilities'] ?? []))
            : [];

        $department = Department::findOrFail($data['department_id']);
        $assignees = collect($data['assignees'] ?? []);
        $memberIds = $department->members()->pluck('users.id');
        if ($project) {
            // Un membre de projet (ex. Facilitateur de zone) n'est pas forcément membre formel du département.
            $memberIds = $memberIds->merge($project->members()->pluck('users.id'))->unique()->values();
        }

        // Les assignés doivent appartenir au département/projet (les externes passent par une demande de collaboration).
        if ($assignees->isNotEmpty()) {
            $invalid = $assignees->pluck('user_id')->diff($memberIds);
            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'assignees' => ['Tous les assignés doivent être membres du département ou du projet. Utilisez une demande de collaboration pour un membre externe.'],
                ]);
            }
        }

        $isTeam = ($data['is_team'] ?? false) || $assignees->count() > 1;
        $supervisorId = $this->resolveSupervisor($data['supervisor_id'] ?? null, $memberIds, $user, $department, $isTeam, $project);

        $task = DB::transaction(function () use ($data, $user, $assignees, $isTeam, $supervisorId, $parent, $abilities) {
            $status = $assignees->isNotEmpty() ? TaskStatus::Assignee : TaskStatus::Brouillon;

            $task = Task::create([
                'department_id' => $data['department_id'],
                'parent_id' => $parent?->id,
                'project_id' => $data['project_id'] ?? null,
                'created_by' => $user->id,
                'supervisor_id' => $supervisorId,
                'delegated_abilities' => $abilities ?: null,
                'title' => $data['title'],
                'description' => $data['description'],
                'starts_at' => $data['starts_at'],
                'due_at' => $data['due_at'],
                'is_team' => $isTeam,
                'status' => $status,
            ]);

            foreach ($assignees as $a) {
                $task->assignees()->attach($a['user_id'], [
                    'instructions' => $a['instructions'] ?? null,
                    'estimated_hours' => $a['estimated_hours'] ?? null,
                    'is_external' => false,
                ]);
            }

            $task->statusHistory()->create([
                'from_status' => null,
                'to_status' => $status->value,
                'changed_by' => $user->id,
            ]);

            \App\Models\TaskActivity::log($task, 'task_created', "Tâche créée par {$user->name}.", $user);
            if ($task->supervisor_id) {
                \App\Models\TaskActivity::log(
                    $task,
                    'authority_delegated',
                    'Responsable de la tâche : ' . ($task->supervisor?->name ?? '—') . '.',
                    $user,
                    \App\Models\TaskActivity::STREAM_AUTHORITY,
                );
            }

            return $task;
        });

        foreach ($task->assignees as $assignee) {
            if ($assignee->id !== $user->id) {
                $assignee->notify(new TaskAssigned($task, $user));
            }
        }
        if ($task->supervisor_id && $task->supervisor_id !== $user->id && ! $task->hasAssignee($task->supervisor_id)) {
            $task->supervisor?->notify(new TaskAssigned($task, $user));
        }

        return (new TaskResource($this->fullyLoad($task)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($this->fullyLoad($task));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'due_at' => ['sometimes', 'date'],
            'is_team' => ['sometimes', 'boolean'],
            'supervisor_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'delegated_abilities' => ['sometimes', 'nullable', 'array'],
            'delegated_abilities.*' => [Rule::in(Task::DELEGATABLE_ABILITIES)],
            'assignees' => ['sometimes', 'array'],
            'assignees.*.user_id' => ['required', 'distinct', 'exists:users,id'],
            'assignees.*.instructions' => ['nullable', 'string'],
            'assignees.*.estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Le responsable / responsable délégué ne peut pas se réattribuer des pouvoirs
        // ni changer le responsable : réservé au chef (ou à l'admin, ou au responsable de projet).
        $isChefLevel = $request->user()->isAdmin()
            || $request->user()->isChefOf($task->department_id)
            || ($task->project_id && $request->user()->isProjectManagerOf($task->project_id));
        if (! $isChefLevel) {
            unset($data['supervisor_id'], $data['delegated_abilities']);
        } elseif (array_key_exists('delegated_abilities', $data)) {
            $data['delegated_abilities'] = $data['delegated_abilities']
                ? array_values(array_unique($data['delegated_abilities']))
                : null;
        }

        $due = $data['due_at'] ?? $task->due_at;
        $start = $data['starts_at'] ?? $task->starts_at;
        if (strtotime((string) $due) <= strtotime((string) $start)) {
            throw ValidationException::withMessages([
                'due_at' => ["L'échéance doit être postérieure au début."],
            ]);
        }

        $willBeTeam = array_key_exists('is_team', $data) ? (bool) $data['is_team'] : $task->is_team;
        if (array_key_exists('assignees', $data)) {
            $willBeTeam = $willBeTeam || count($data['assignees']) > 1;
        }
        if (array_key_exists('supervisor_id', $data)) {
            $memberIds = $task->department->members()->pluck('users.id');
            if ($task->project_id) {
                $memberIds = $memberIds->merge($task->project->members()->pluck('users.id'))->unique()->values();
            }
            $data['supervisor_id'] = $this->resolveSupervisor(
                $data['supervisor_id'],
                $memberIds,
                $request->user(),
                $task->department,
                $willBeTeam,
                $task->project,
            );
        }

        $actor = $request->user();
        $previousSupervisorId = $task->supervisor_id;

        $abilitiesChanged = array_key_exists('delegated_abilities', $data)
            && ($data['delegated_abilities'] ?? []) != ($task->delegated_abilities ?? []);

        DB::transaction(function () use ($task, $data, $request, $actor, $previousSupervisorId, $abilitiesChanged) {
            $changedFields = collect($data)->only(['title', 'description', 'starts_at', 'due_at', 'is_team'])->keys();
            $task->update(collect($data)->only(['title', 'description', 'starts_at', 'due_at', 'is_team', 'supervisor_id', 'delegated_abilities'])->all());

            if ($changedFields->isNotEmpty()) {
                \App\Models\TaskActivity::log($task, 'task_updated', "Détails de la tâche modifiés par {$actor->name} (" . $changedFields->implode(', ') . ').', $actor);
            }

            if ($abilitiesChanged) {
                $list = collect($data['delegated_abilities'] ?? [])->implode(', ') ?: 'aucun';
                \App\Models\TaskActivity::log(
                    $task,
                    'authority_delegated',
                    "{$actor->name} a modifié les pouvoirs délégués au responsable : {$list}.",
                    $actor,
                    \App\Models\TaskActivity::STREAM_AUTHORITY,
                );
            }

            if (array_key_exists('supervisor_id', $data) && $data['supervisor_id'] !== $previousSupervisorId) {
                $name = $data['supervisor_id'] ? (User::find($data['supervisor_id'])?->name ?? '—') : 'aucun';
                \App\Models\TaskActivity::log(
                    $task,
                    'authority_delegated',
                    "{$actor->name} a confié la responsabilité de la tâche à : {$name}.",
                    $actor,
                    \App\Models\TaskActivity::STREAM_AUTHORITY,
                );
            }

            if ($request->has('assignees')) {
                $department = $task->department;
                $memberIds = $department->members()->pluck('users.id');
                if ($task->project_id) {
                    $memberIds = $memberIds->merge($task->project->members()->pluck('users.id'))->unique()->values();
                }
                $newOnes = collect($data['assignees']);

                $invalid = $newOnes->pluck('user_id')->diff($memberIds);
                // On tolère les membres externes déjà rattachés via une collaboration acceptée.
                $externalOk = $task->assignees()->wherePivot('is_external', true)->pluck('users.id');
                $invalid = $invalid->diff($externalOk);
                if ($invalid->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'assignees' => ['Assigné hors département/projet : passez par une demande de collaboration.'],
                    ]);
                }

                $sync = [];
                foreach ($newOnes as $a) {
                    $sync[$a['user_id']] = [
                        'instructions' => $a['instructions'] ?? null,
                        'estimated_hours' => $a['estimated_hours'] ?? null,
                        'is_external' => $externalOk->contains($a['user_id']),
                    ];
                }

                $before = $task->assignees()->pluck('users.id');
                $task->assignees()->sync($sync);
                $added = collect(array_keys($sync))->map('intval')->diff($before);
                $removed = $before->diff(collect(array_keys($sync))->map('intval'));

                foreach ($added as $userId) {
                    $u = User::find($userId);
                    if ($userId !== $actor->id) {
                        $u?->notify(new TaskAssigned($task, $actor));
                    }
                    \App\Models\TaskActivity::log($task, 'member_added', "{$actor->name} a ajouté {$u?->name} à l'équipe.", $actor);
                }
                foreach ($removed as $userId) {
                    $u = User::find($userId);
                    \App\Models\TaskActivity::log(
                        $task,
                        'member_removed',
                        "{$actor->name} a retiré {$u?->name} de l'équipe.",
                        $actor,
                        \App\Models\TaskActivity::STREAM_AUTHORITY,
                    );
                }
            }
        });

        return new TaskResource($this->fullyLoad($task));
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // La tâche est supprimée définitivement (avec son historique) : on garde
        // une trace du motif dans le journal applicatif.
        Log::notice(sprintf(
            'Tâche #%d "%s" (département %s) supprimée par %s (#%d). Motif : %s',
            $task->id,
            $task->title,
            $task->department_id,
            $request->user()->name,
            $request->user()->id,
            $data['reason'] ?? '—',
        ));

        $task->delete();

        return response()->json(['message' => 'Tâche supprimée.']);
    }

    private function fullyLoad(Task $task): Task
    {
        return $task->refresh()->load([
            'department',
            'project:id,title',
            'parent:id,title',
            'subTasks:id,parent_id,title,status,progress,due_at',
            'dependsOn:id,title,status',
            'creator',
            'supervisor',
            'assignees',
            'progressUpdates.user',
            'progressUpdates.reviewer',
            'progressUpdates.rejecter',
            'deliverables.uploader',
            'statusHistory.changedBy',
            'evaluation.evaluator',
            'collaborationRequests.targetUser',
            'activities.actor',
        ]);
    }

    /**
     * Détermine le responsable (superviseur) d'une tâche.
     *  - doit être membre du département (ou le chef créateur) ;
     *  - obligatoire pour une tâche d'équipe ;
     *  - à défaut, le chef qui crée la tâche.
     */
    private function resolveSupervisor(?int $supervisorId, $memberIds, User $actor, Department $department, bool $isTeam, ?Project $project = null): ?int
    {
        $actorIsManager = $actor->isChefOf($department->id) || ($project && $actor->isProjectManagerOf($project->id));

        if ($supervisorId) {
            $allowed = $memberIds->contains($supervisorId)
                || (User::find($supervisorId)?->isChefOf($department->id) ?? false)
                || ($project && (User::find($supervisorId)?->isProjectManagerOf($project->id) ?? false));
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'supervisor_id' => ['Le responsable doit être un membre de ce département ou du projet.'],
                ]);
            }

            return $supervisorId;
        }

        if ($isTeam) {
            // Pour une équipe, le responsable est obligatoire : on retombe sur le chef créateur.
            if ($actorIsManager) {
                return $actor->id;
            }
            throw ValidationException::withMessages([
                'supervisor_id' => ['Une tâche d’équipe doit avoir un responsable.'],
            ]);
        }

        return $actorIsManager ? $actor->id : null;
    }
}
