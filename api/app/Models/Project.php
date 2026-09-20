<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'department_id',
        'created_by',
        'title',
        'description',
        'effet',
        'extrant',
        'budget_previsionnel',
        'parties_prenantes',
        'starts_at',
        'due_at',
        'status',
    ];

    protected $appends = ['depenses_engagees', 'budget_consumed_pct', 'progress', 'risk_flag'];

    protected function casts(): array
    {
        return [
            'budget_previsionnel' => 'decimal:2',
            'parties_prenantes' => 'array',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'status' => ProjectStatus::class,
        ];
    }

    // ----- Relations -----------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** Toutes les tâches du projet (activités racines + sous-activités). */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Activités de premier niveau (= « Activités » du PTAB). */
    public function activities(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->latest();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('date');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class)->latest();
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class)->latest();
    }

    // ----- Rôles -----------------------------------------------------------

    public function roleOf(int $userId): ?ProjectRole
    {
        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $userId)
            : $this->members()->where('users.id', $userId)->first();

        return $member?->pivot?->role ? ProjectRole::from($member->pivot->role) : null;
    }

    public function isManagedBy(int $userId): bool
    {
        return in_array($this->roleOf($userId), ProjectRole::managerial(), true);
    }

    // ----- Accesseurs --------------------------------------------------

    public function getDepensesEngageesAttribute(): float
    {
        return (float) $this->expenses->sum('amount');
    }

    public function getBudgetConsumedPctAttribute(): ?float
    {
        $budget = (float) ($this->budget_previsionnel ?? 0);

        return $budget > 0 ? round($this->depenses_engagees / $budget * 100, 1) : null;
    }

    public function getProgressAttribute(): int
    {
        $values = $this->activities->pluck('progress');

        return $values->isEmpty() ? 0 : (int) round($values->avg());
    }

    public function getRiskFlagAttribute(): bool
    {
        $overdueActivities = $this->activities
            ->contains(fn (Task $t) => ! $t->status->isClosed() && $t->due_at !== null && $t->due_at->isPast());

        $budgetOverrun = $this->budget_consumed_pct !== null && $this->budget_consumed_pct > 100;

        return $overdueActivities || $budgetOverrun;
    }

    // ----- Scopes -----------------------------------------------------

    /** Restreint la requête aux projets visibles par l'utilisateur. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSupervisor()) {
            return $query;
        }

        $ledDepartmentIds = $user->ledDepartmentIds();

        return $query->where(function (Builder $q) use ($user, $ledDepartmentIds) {
            $q->whereIn('department_id', $ledDepartmentIds)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }
}
