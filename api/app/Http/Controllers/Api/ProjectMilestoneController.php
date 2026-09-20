<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $project->milestones()->get()->map(fn ($m) => $this->present($m)),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageMilestones', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        $milestone = $project->milestones()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($milestone)], 201);
    }

    public function update(Request $request, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $milestone->project);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'is_reached' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_reached', $data)) {
            $data['reached_at'] = $data['is_reached'] ? now() : null;
        }

        $milestone->update($data);

        return response()->json(['data' => $this->present($milestone)]);
    }

    public function destroy(ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $milestone->project);

        $milestone->delete();

        return response()->json(['message' => 'Jalon supprimé.']);
    }

    private function present(ProjectMilestone $m): array
    {
        return [
            'id' => $m->id,
            'project_id' => $m->project_id,
            'title' => $m->title,
            'date' => $m->date,
            'is_reached' => $m->is_reached,
            'reached_at' => $m->reached_at,
        ];
    }
}
