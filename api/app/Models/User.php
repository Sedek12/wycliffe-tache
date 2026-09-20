<?php

namespace App\Models;

use App\Enums\DepartmentRole;
use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'last_name',
        'first_name',
        'middle_name',
        'birth_date',
        'gender',
        'email',
        'password',
        'system_role',
        'phone',
        'phone_secondary',
        'job_title',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url', 'initials'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'password' => 'hashed',
            'system_role' => SystemRole::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Compose le nom complet à partir des parties d'identité.
        static::saving(function (User $user) {
            if ($user->first_name || $user->last_name) {
                $user->name = trim(implode(' ', array_filter([
                    $user->first_name,
                    $user->middle_name,
                    $user->last_name,
                ]))) ?: $user->name;
            }
        });
    }

    // ----- Relations -------------------------------------------------------

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['role', 'position_id'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** Départements où l'utilisateur est chef. */
    public function ledDepartments(): BelongsToMany
    {
        return $this->departments()->wherePivot('role', DepartmentRole::Chef->value);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot(['instructions', 'is_external', 'is_done', 'done_at'])
            ->withTimestamps();
    }

    // ----- Rôles ---------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->system_role === SystemRole::Admin;
    }

    public function isDirecteur(): bool
    {
        return $this->system_role === SystemRole::Directeur;
    }

    /** Admin ou directeur : visibilité sur tous les départements. */
    public function isSupervisor(): bool
    {
        return $this->isAdmin() || $this->isDirecteur();
    }

    public function roleInDepartment(int $departmentId): ?DepartmentRole
    {
        $department = $this->relationLoaded('departments')
            ? $this->departments->firstWhere('id', $departmentId)
            : $this->departments()->where('departments.id', $departmentId)->first();

        return $department?->pivot?->role
            ? DepartmentRole::from($department->pivot->role)
            : null;
    }

    public function isChefOf(int $departmentId): bool
    {
        return $this->roleInDepartment($departmentId) === DepartmentRole::Chef;
    }

    public function isChefSomewhere(): bool
    {
        return $this->departments()->wherePivot('role', DepartmentRole::Chef->value)->exists();
    }

    /** IDs des départements où l'utilisateur est chef. */
    public function ledDepartmentIds(): array
    {
        return $this->ledDepartments()->pluck('departments.id')->all();
    }

    public function roleOnProject(int $projectId): ?ProjectRole
    {
        $project = $this->relationLoaded('projects')
            ? $this->projects->firstWhere('id', $projectId)
            : $this->projects()->where('projects.id', $projectId)->first();

        return $project?->pivot?->role ? ProjectRole::from($project->pivot->role) : null;
    }

    /** Rôle « niveau chef » (managérial) sur ce projet précis. */
    public function isProjectManagerOf(int $projectId): bool
    {
        return in_array($this->roleOnProject($projectId), ProjectRole::managerial(), true);
    }

    public function isMemberOfProject(int $projectId): bool
    {
        return $this->roleOnProject($projectId) !== null;
    }

    // ----- Accessors ---------------------------------------------------

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(fn ($p) => Str::upper(Str::substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'U';
    }
}
