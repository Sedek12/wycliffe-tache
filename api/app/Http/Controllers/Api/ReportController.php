<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function series(Request $request)
    {
        $user = $request->user();
        $departmentFilter = $request->integer('department_id') ?: null;

        $from = $request->date('from') ?? now()->startOfMonth()->subMonths(5);
        $to = $request->date('to') ?? now()->endOfMonth();

        $base = fn () => Task::query()
            ->visibleTo($user)
            ->when($departmentFilter, fn ($q, $id) => $q->where('department_id', $id));

        // --- Tâches terminées par mois + taux de ponctualité ---
        $completed = $base()
            ->where('status', TaskStatus::Validee->value)
            ->whereBetween('completed_at', [$from, $to])
            ->get(['completed_at', 'due_at']);

        $months = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $months[$key] = ['month' => $key, 'completed' => 0, 'on_time' => 0, 'late' => 0];
            $cursor->addMonth();
        }

        foreach ($completed as $task) {
            $key = $task->completed_at->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $months[$key]['completed']++;
            if ($task->completed_at <= $task->due_at) {
                $months[$key]['on_time']++;
            } else {
                $months[$key]['late']++;
            }
        }

        $monthly = array_values(array_map(function ($row) {
            $row['on_time_rate'] = $row['completed'] > 0
                ? round($row['on_time'] / $row['completed'] * 100, 1)
                : null;

            return $row;
        }, $months));

        // --- Note moyenne par département ---
        $byDepartment = $base()
            ->join('evaluations', 'evaluations.task_id', '=', 'tasks.id')
            ->join('departments', 'departments.id', '=', 'tasks.department_id')
            ->select('departments.id', 'departments.name', DB::raw('avg(evaluations.score) as avg_score'), DB::raw('count(*) as evaluated'))
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name')
            ->get()
            ->map(fn ($r) => [
                'department_id' => $r->id,
                'department' => $r->name,
                'avg_score' => round((float) $r->avg_score, 2),
                'evaluated' => (int) $r->evaluated,
            ]);

        // --- Classement des collaborateurs (note moyenne + ponctualité) ---
        $perUser = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->join('users', 'users.id', '=', 'task_user.user_id')
            ->leftJoin('evaluations', 'evaluations.task_id', '=', 'tasks.id')
            ->when($departmentFilter, fn ($q) => $q->where('tasks.department_id', $departmentFilter))
            ->when(! $user->isSupervisor(), fn ($q) => $q->whereIn('tasks.department_id', $user->ledDepartmentIds()))
            ->where('tasks.status', TaskStatus::Validee->value)
            ->whereBetween('tasks.completed_at', [$from, $to])
            ->select(
                'users.id',
                'users.name',
                DB::raw('count(distinct tasks.id) as completed'),
                DB::raw('avg(evaluations.score) as avg_score'),
                DB::raw('sum(case when tasks.completed_at <= tasks.due_at then 1 else 0 end) as on_time')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('avg_score')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->id,
                'name' => $r->name,
                'completed' => (int) $r->completed,
                'avg_score' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null,
                'on_time_rate' => $r->completed > 0 ? round($r->on_time / $r->completed * 100, 1) : null,
            ]);

        return response()->json([
            'range' => ['from' => Carbon::parse($from)->toDateString(), 'to' => Carbon::parse($to)->toDateString()],
            'monthly' => $monthly,
            'by_department' => $byDepartment,
            'by_user' => $perUser,
        ]);
    }
}
