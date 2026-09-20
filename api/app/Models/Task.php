<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    /** Pouvoirs délégables par le chef au responsable d'une tâche. */
    public const ABILITY_CREATE_SUBTASKS = 'create_subtasks';
    public const ABILITY_MANAGE_TEAM = 'manage_team';
    public const ABILITY_REQUEST_COLLABORATION = 'request_collaboration';
    public const ABILITY_CHANGE_STATUS = 'change_status';

    public const DELEGATABLE_ABILITIES = [
        self::ABILITY_CREATE_SUBTASKS,
        self::ABILITY_MANAGE_TEAM,
        self::ABILITY_REQUEST_COLLABORATION,
        self::ABILITY_CHANGE_STATUS,
    ];

    protected $fillable = [
        'department_id',
        'parent_id',
        'project_id',
        'created_by',
        'supervisor_id',
        'delegated_abilities',
        'title',
        'description',
        'starts_at',
        'due_at',
        'status',
        'progress',
        'is_team',
        'completed_at',
    ];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => TaskStatus::class,
            'progress' => 'integer',
            'is_team' => 'boolean',
            'delegated_abilities' => 'array',
        ];
    }

    // ----- Relations -----------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Projet auquel appartient cette tâche (« Activité » ou « Sous-activité » du PTAB). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tâche parente (si cette tâche est une sous-tâche). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /** Sous-tâches rattachées à cette tâche. */
    public function subTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Personne qui supervise l'avancement (le chef ou son délégué). */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['instructions', 'estimated_hours', 'is_external', 'is_done', 'done_at', 'reopen_requested_at'])
            ->withTimestamps();
    }

    /** Tâches dont dépend celle-ci (doivent être validées avant qu'elle ne démarre). */
    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }

    /** Tâches bloquées tant que celle-ci n'est pas validée. */
    public function blockedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id');
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProgressUpdate::class)->latest();
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->oldest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }

    public function collaborationRequests(): HasMany
    {
        return $this->hasMany(CollaborationRequest::class);
    }

    // ----- Helpers -----------------------------------------------------

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->status->isClosed()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function hasAssignee(int $userId): bool
    {
        return $this->assignees->contains('id', $userId);
    }

    public function isSupervisedBy(int $userId): bool
    {
        return $this->supervisor_id === $userId;
    }

    /** Dépendances non encore validées (bloquent le démarrage de cette tâche). */
    public function unmetDependencies()
    {
        return $this->dependsOn()->get()->reject(fn (Task $t) => $t->status === TaskStatus::Validee);
    }

    /** Le responsable (et lui seul) dispose-t-il du pouvoir délégué demandé ? */
    public function supervisorCan(int $userId, string $ability): bool
    {
        return $this->isSupervisedBy($userId)
            && in_array($ability, $this->delegated_abilities ?? [], true);
    }

    // ----- Scopes -----------------------------------------------------

    /** Restreint la requête aux tâches visibles par l'utilisateur. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSupervisor()) {
            return $query;
        }

        $ledDepartmentIds = $user->ledDepartmentIds();

        return $query->where(function (Builder $q) use ($user, $ledDepartmentIds) {
            $q->whereIn('department_id', $ledDepartmentIds)
                ->orWhere('created_by', $user->id)
                ->orWhere('supervisor_id', $user->id)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('users.id', $user->id))
                ->orWhereHas('project.members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }
}
