<?php

namespace App\Http\Controllers\Api;

use App\Enums\CollaborationRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\CollaborationRequest;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CollaborationAnswered;
use App\Notifications\CollaborationRequested;
use App\Notifications\TaskAssigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollaborationRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ledIds = $user->ledDepartmentIds();

        $requests = CollaborationRequest::query()
            ->with(['task', 'requester', 'targetUser', 'fromDepartment', 'toDepartment', 'respondedBy'])
            ->where(function ($q) use ($user, $ledIds) {
                $q->whereIn('to_department_id', $ledIds)
                    ->orWhereIn('from_department_id', $ledIds)
                    ->orWhere('requested_by', $user->id)
                    ->orWhere('target_user_id', $user->id);
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'to_department_id' => ['required', 'exists:departments,id'],
            'target_user_id' => ['required_without:target_user_ids', 'exists:users,id'],
            'target_user_ids' => ['required_without:target_user_id', 'array', 'min:1'],
            'target_user_ids.*' => ['distinct', 'exists:users,id'],
            'message' => ['nullable', 'string'],
        ]);

        $task = Task::findOrFail($data['task_id']);
        $this->authorize('requestCollaboration', $task);

        if ($data['to_department_id'] == $task->department_id) {
            throw ValidationException::withMessages([
                'to_department_id' => ['Ce département est celui de la tâche : ajoutez simplement le membre.'],
            ]);
        }

        $targetDept = Department::findOrFail($data['to_department_id']);
        $targetIds = collect($data['target_user_ids'] ?? [$data['target_user_id']])->map('intval')->unique();

        $deptMemberIds = $targetDept->members()->pluck('users.id');
        $assignedIds = $task->assignees()->pluck('users.id');
        $pendingIds = CollaborationRequest::where('task_id', $task->id)
            ->where('status', CollaborationRequestStatus::EnAttente)
            ->pluck('target_user_id');

        if ($targetIds->diff($deptMemberIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'target_user_ids' => ['Toutes les personnes doivent appartenir au département sollicité.'],
            ]);
        }
        if ($targetIds->intersect($assignedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'target_user_ids' => ['Une personne sélectionnée est déjà assignée à la tâche.'],
            ]);
        }
        if ($targetIds->intersect($pendingIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'target_user_ids' => ['Une demande est déjà en attente pour une des personnes.'],
            ]);
        }

        $created = collect();
        DB::transaction(function () use ($targetIds, $task, $request, $data, &$created) {
            foreach ($targetIds as $uid) {
                $created->push(CollaborationRequest::create([
                    'task_id' => $task->id,
                    'requested_by' => $request->user()->id,
                    'target_user_id' => $uid,
                    'from_department_id' => $task->department_id,
                    'to_department_id' => $data['to_department_id'],
                    'status' => CollaborationRequestStatus::EnAttente,
                    'message' => $data['message'] ?? null,
                ]));
            }
        });

        $created = CollaborationRequest::with(['task', 'requester', 'targetUser', 'fromDepartment', 'toDepartment'])
            ->whereIn('id', $created->pluck('id'))
            ->get();

        $names = $created->map(fn ($c) => $c->targetUser?->name)->filter()->implode(', ');
        \App\Models\TaskActivity::log(
            $task,
            'collab_requested',
            "{$request->user()->name} a demandé au département « {$targetDept->name} » de prêter : {$names}.",
            $request->user(),
            \App\Models\TaskActivity::STREAM_AUTHORITY,
        );

        foreach ($targetDept->chefs()->get() as $chef) {
            foreach ($created as $collab) {
                $chef->notify(new CollaborationRequested($collab));
            }
        }

        return response()->json(['data' => $created], 201);
    }

    public function respond(Request $request, CollaborationRequest $collaborationRequest)
    {
        $this->authorize('respond', $collaborationRequest);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'response_note' => ['nullable', 'string'],
        ]);

        $accepted = $data['decision'] === 'accept';

        DB::transaction(function () use ($collaborationRequest, $request, $data, $accepted) {
            $collaborationRequest->update([
                'status' => $accepted ? CollaborationRequestStatus::Acceptee : CollaborationRequestStatus::Refusee,
                'response_note' => $data['response_note'] ?? null,
                'responded_by' => $request->user()->id,
                'responded_at' => now(),
            ]);

            if ($accepted) {
                $task = $collaborationRequest->task;
                $task->assignees()->syncWithoutDetaching([
                    $collaborationRequest->target_user_id => ['is_external' => true],
                ]);
                if (! $task->is_team && $task->assignees()->count() > 1) {
                    $task->update(['is_team' => true]);
                }
                $collaborationRequest->targetUser->notify(new TaskAssigned($task, $collaborationRequest->requester));
            }
        });

        $collaborationRequest->load(['task', 'requester', 'targetUser', 'fromDepartment', 'toDepartment', 'respondedBy']);
        $collaborationRequest->requester->notify(new CollaborationAnswered($collaborationRequest));

        \App\Models\TaskActivity::log(
            $collaborationRequest->task,
            $accepted ? 'collab_accepted' : 'collab_rejected',
            "{$request->user()->name} a " . ($accepted ? 'accepté' : 'refusé')
                . " le prêt de {$collaborationRequest->targetUser?->name} (département « {$collaborationRequest->toDepartment?->name} »).",
            $request->user(),
            \App\Models\TaskActivity::STREAM_AUTHORITY,
        );

        return response()->json($collaborationRequest);
    }

    public function cancel(CollaborationRequest $collaborationRequest)
    {
        $this->authorize('cancel', $collaborationRequest);

        $collaborationRequest->delete();

        return response()->json(['message' => 'Demande annulée.']);
    }
}
