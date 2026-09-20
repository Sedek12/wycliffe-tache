<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    /** Tous les postes visibles (superviseurs : tous ; chefs : leurs départements). */
    public function all(Request $request)
    {
        $user = $request->user();

        $positions = Position::query()
            ->with('department:id,name')
            ->withCount('members')
            ->when(! $user->isSupervisor(), fn ($q) => $q->whereIn('department_id', $user->ledDepartmentIds()))
            ->orderBy('department_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'is_active' => $p->is_active,
                'members_count' => $p->members_count,
                'department_id' => $p->department_id,
                'department' => $p->department?->name,
            ]);

        return response()->json(['data' => $positions]);
    }

    /** Postes d'un département donné. */
    public function index(Department $department)
    {
        $this->authorize('view', $department);

        return response()->json(['data' => $department->positions()->withCount('members')->get()]);
    }

    /** Création : le département est choisi dans le corps de la requête. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::findOrFail($data['department_id']);
        $this->authorize('manageMembers', $department);

        $request->validate([
            'name' => [Rule::unique('positions', 'name')->where('department_id', $department->id)],
        ], [], ['name' => 'intitulé du poste']);

        $position = $department->positions()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($position->loadCount('members'), 201);
    }

    /** Modification : on peut aussi rattacher le poste à un autre département. */
    public function update(Request $request, Position $position)
    {
        $this->authorize('manageMembers', $position->department);

        $data = $request->validate([
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $targetDepartmentId = $data['department_id'] ?? $position->department_id;

        if ($targetDepartmentId != $position->department_id) {
            $this->authorize('manageMembers', Department::findOrFail($targetDepartmentId));
        }

        $name = $data['name'] ?? $position->name;
        $request->validate([
            'name' => [
                Rule::unique('positions', 'name')
                    ->where('department_id', $targetDepartmentId)
                    ->ignore($position->id),
            ],
        ], [], ['name' => 'intitulé du poste']);

        $position->update([
            'department_id' => $targetDepartmentId,
            'name' => $name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $position->description,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $position->is_active,
        ]);

        // Si le poste change de département, les affectations vers l'ancien département perdent ce poste.
        if ($position->wasChanged('department_id')) {
            DB::table('department_user')
                ->where('position_id', $position->id)
                ->where('department_id', '!=', $targetDepartmentId)
                ->update(['position_id' => null]);
        }

        return response()->json($position->fresh()->loadCount('members'));
    }

    public function destroy(Position $position)
    {
        $this->authorize('manageMembers', $position->department);

        $position->delete();

        return response()->json(['message' => 'Poste supprimé.']);
    }
}
