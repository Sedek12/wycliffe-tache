<?php

namespace App\Http\Controllers\Api;

use App\Enums\DepartmentRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $user = $request->user();

        $departments = Department::query()
            ->withCount(['members', 'tasks', 'positions', 'chefs'])
            ->when(! $user->isSupervisor(), fn ($q) => $q->whereHas(
                'members',
                fn ($m) => $m->where('users.id', $user->id)
            ))
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($departments);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Department::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return (new DepartmentResource($department->loadCount(['members', 'tasks', 'positions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);

        $department->load([
            'members' => fn ($q) => $q->orderBy('name'),
            'positions',
        ])->loadCount('tasks');

        return new DepartmentResource($department);
    }

    public function update(Request $request, Department $department)
    {
        $this->authorize('update', $department);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department->update($data);

        return new DepartmentResource($department->loadCount(['members', 'tasks', 'positions']));
    }

    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(['message' => 'Département supprimé.']);
    }

    // ----- Membres ---------------------------------------------------------

    public function members(Request $request, Department $department)
    {
        $user = $request->user();

        // Consultable par les superviseurs, les membres du département, ou tout chef
        // (nécessaire pour les demandes de collaboration inter-départements).
        abort_unless(
            $user->isSupervisor()
                || $user->isChefSomewhere()
                || $user->departments()->where('departments.id', $department->id)->exists(),
            403,
        );

        return UserResource::collection(
            $department->members()->orderBy('name')->get()
        );
    }

    /** Ajoute un membre ou met à jour son rôle / poste dans le département. */
    public function upsertMember(Request $request, Department $department)
    {
        $this->authorize('manageMembers', $department);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::enum(DepartmentRole::class)],
            'position_id' => ['nullable', 'exists:positions,id'],
        ]);

        if (! empty($data['position_id'])) {
            $belongs = Position::where('id', $data['position_id'])
                ->where('department_id', $department->id)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'position_id' => ['Ce poste n\'appartient pas à ce département.'],
                ]);
            }
        }

        $department->members()->syncWithoutDetaching([
            $data['user_id'] => [
                'role' => $data['role'],
                'position_id' => $data['position_id'] ?? null,
            ],
        ]);

        return new DepartmentResource(
            $department->load(['members' => fn ($q) => $q->orderBy('name'), 'positions'])
        );
    }

    public function removeMember(Department $department, User $user)
    {
        $this->authorize('manageMembers', $department);

        $department->members()->detach($user->id);

        return response()->json(['message' => 'Membre retiré du département.']);
    }
}
