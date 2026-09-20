<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        return \App\Http\Resources\UserResource::collection(
            $project->members()->orderBy('name')->get()
        );
    }

    /** Ajoute un membre ou met à jour son rôle sur le projet. */
    public function upsertMember(Request $request, Project $project)
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $project->members()->syncWithoutDetaching([
            $data['user_id'] => ['role' => $data['role']],
        ]);

        return new ProjectResource($project->load('members'));
    }

    public function removeMember(Project $project, User $user)
    {
        $this->authorize('manageMembers', $project);

        $project->members()->detach($user->id);

        return response()->json(['message' => 'Membre retiré du projet.']);
    }
}
