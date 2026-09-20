<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['department', 'creator'])
            ->withCount('activities')
            ->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effet' => ['nullable', 'string'],
            'extrant' => ['nullable', 'string'],
            'budget_previsionnel' => ['nullable', 'numeric', 'min:0'],
            'parties_prenantes' => ['nullable', 'array'],
            'parties_prenantes.*' => ['string'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $user = $request->user();

        if (! $user->isChefOf($data['department_id']) && ! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'department_id' => ["Vous n'êtes pas chef de ce département."],
            ]);
        }

        $project = Project::create([
            ...$data,
            'created_by' => $user->id,
            'status' => ProjectStatus::Brouillon->value,
        ]);

        // Le créateur devient automatiquement chef de département sur le projet.
        $project->members()->attach($user->id, ['role' => \App\Enums\ProjectRole::ChefDepartement->value]);

        return (new ProjectResource($this->fullyLoad($project)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return new ProjectResource($this->fullyLoad($project));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'effet' => ['sometimes', 'nullable', 'string'],
            'extrant' => ['sometimes', 'nullable', 'string'],
            'budget_previsionnel' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parties_prenantes' => ['sometimes', 'nullable', 'array'],
            'parties_prenantes.*' => ['string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
        ]);

        if (array_key_exists('status', $data)) {
            $target = ProjectStatus::from($data['status']);
            if (! $project->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Transition impossible : « {$project->status->label()} » → « {$target->label()} ».",
                ]);
            }
        }

        $project->update($data);

        return new ProjectResource($this->fullyLoad($project));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Projet supprimé.']);
    }

    private function fullyLoad(Project $project): Project
    {
        return $project->refresh()->load([
            'department',
            'creator',
            'members',
            'activities.subTasks',
            'activities.assignees',
            'activities.dependsOn',
            'activities.evaluation',
            'milestones',
            'expenses.creator',
            'deliverables.uploader',
        ]);
    }
}
