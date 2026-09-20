<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** KPI par projet : avancement, budget, retards, risque. */
    public function projects(Request $request)
    {
        $user = $request->user();
        $departmentFilter = $request->integer('department_id') ?: null;

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['department', 'activities', 'expenses'])
            ->withCount('activities')
            ->when($departmentFilter, fn ($q, $id) => $q->where('department_id', $id))
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'department' => $p->department?->name,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'progress' => $p->progress,
                'budget_previsionnel' => $p->budget_previsionnel !== null ? (float) $p->budget_previsionnel : null,
                'depenses_engagees' => $p->depenses_engagees,
                'budget_consumed_pct' => $p->budget_consumed_pct,
                'activities_count' => $p->activities_count,
                'risk_flag' => $p->risk_flag,
            ]);

        return response()->json([
            'data' => $projects,
            'at_risk_count' => $projects->where('risk_flag', true)->count(),
        ]);
    }

    public function overview(Request $request)
    {
        $user = $request->user();
        $departmentFilter = $request->integer('department_id') ?: null;

        // Renvoie un nouveau builder restreint aux tâches visibles à chaque appel.
        $base = fn () => Task::query()
            ->visibleTo($user)
            ->when($departmentFilter, fn ($q, $id) => $q->where('department_id', $id));

        $byStatus = $base()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = collect(TaskStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]);

        $overdue = $base()
            ->where('due_at', '<', now())
            ->where('status', '!=', TaskStatus::Validee->value)
            ->count();

        $completedCount = $base()->where('status', TaskStatus::Validee->value)->count();
        $onTime = $base()
            ->where('status', TaskStatus::Validee->value)
            ->whereColumn('completed_at', '<=', 'due_at')
            ->count();

        $avgScore = $base()
            ->join('evaluations', 'evaluations.task_id', '=', 'tasks.id')
            ->avg('evaluations.score');

        $departmentIds = $user->isSupervisor()
            ? Department::pluck('id')
            : collect($user->ledDepartmentIds());

        $perDepartment = Department::whereIn('id', $departmentIds)
            ->when($departmentFilter, fn ($q, $id) => $q->where('id', $id))
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', TaskStatus::Validee->value),
                'tasks as overdue_tasks_count' => fn ($q) => $q
                    ->where('due_at', '<', now())
                    ->where('status', '!=', TaskStatus::Validee->value),
                'members',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'tasks_count' => $d->tasks_count,
                'open_tasks_count' => $d->open_tasks_count,
                'overdue_tasks_count' => $d->overdue_tasks_count,
                'members_count' => $d->members_count,
            ]);

        $total = $statusCounts->sum();

        return response()->json([
            'scope' => $user->isSupervisor() ? 'global' : ($user->isChefSomewhere() ? 'departments' : 'personal'),
            'status_counts' => $statusCounts,
            'totals' => [
                'tasks' => $total,
                'open' => $total - $statusCounts[TaskStatus::Validee->value],
                'overdue' => $overdue,
                'completed' => $completedCount,
            ],
            'on_time_rate' => $completedCount > 0 ? round($onTime / $completedCount * 100, 1) : null,
            'average_score' => $avgScore !== null ? round((float) $avgScore, 2) : null,
            'per_department' => $perDepartment,
        ]);
    }
}
