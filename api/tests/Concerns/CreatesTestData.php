<?php

namespace Tests\Concerns;

use App\Enums\DepartmentRole;
use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

trait CreatesTestData
{
    protected function makeDepartment(array $attributes = []): Department
    {
        return Department::create(array_merge([
            'name' => 'Traduction '.uniqid(),
            'is_active' => true,
        ], $attributes));
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function makeAdmin(): User
    {
        return $this->makeUser(['system_role' => SystemRole::Admin]);
    }

    protected function makeDirecteur(): User
    {
        return $this->makeUser(['system_role' => SystemRole::Directeur]);
    }

    protected function attach(User $user, Department $department, DepartmentRole $role): User
    {
        $department->members()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    protected function makeChef(Department $department): User
    {
        return $this->attach($this->makeUser(), $department, DepartmentRole::Chef);
    }

    protected function makeEmploye(Department $department): User
    {
        return $this->attach($this->makeUser(), $department, DepartmentRole::Employe);
    }

    protected function makeTask(Department $department, User $creator, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'department_id' => $department->id,
            'created_by' => $creator->id,
            'title' => 'Tâche de test',
            'description' => 'Description de test',
            'starts_at' => now(),
            'due_at' => now()->addWeek(),
            'status' => 'brouillon',
            'progress' => 0,
            'is_team' => false,
        ], $attributes));
    }

    protected function makeProject(Department $department, User $creator, array $attributes = []): Project
    {
        return Project::create(array_merge([
            'department_id' => $department->id,
            'created_by' => $creator->id,
            'title' => 'Projet de test',
            'description' => 'Description de test',
            'status' => 'brouillon',
        ], $attributes));
    }

    protected function attachProject(User $user, Project $project, ProjectRole $role): User
    {
        $project->members()->attach($user->id, ['role' => $role->value]);

        return $user;
    }
}
