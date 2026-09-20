<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpense;
use Illuminate\Http\Request;

class ProjectExpenseController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $project->expenses()->with('creator')->get()->map(fn ($e) => $this->present($e)),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageBudget', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $expense = $project->expenses()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($expense->load('creator'))], 201);
    }

    public function update(Request $request, ProjectExpense $expense)
    {
        $this->authorize('manageBudget', $expense->project);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $expense->update($data);

        return response()->json(['data' => $this->present($expense->load('creator'))]);
    }

    public function destroy(ProjectExpense $expense)
    {
        $this->authorize('manageBudget', $expense->project);

        $expense->delete();

        return response()->json(['message' => 'Dépense supprimée.']);
    }

    private function present(ProjectExpense $e): array
    {
        return [
            'id' => $e->id,
            'project_id' => $e->project_id,
            'title' => $e->title,
            'amount' => (float) $e->amount,
            'date' => $e->date,
            'note' => $e->note,
            'created_by' => $e->creator?->name,
            'created_at' => $e->created_at,
        ];
    }
}
