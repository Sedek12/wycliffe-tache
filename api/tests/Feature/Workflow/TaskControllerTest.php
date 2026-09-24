<?php

namespace Tests\Feature\Workflow;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_directeur_can_create_a_task_in_any_department_via_the_real_endpoint(): void
    {
        // Régression : la policy autorisait déjà le directeur (before() -> isSupervisor()),
        // mais TaskController::store() avait son propre contrôle manuel isChefOf()||isAdmin()
        // qui le bloquait quand même (même bug que sur ProjectController::store()).
        $department = $this->makeDepartment();
        $directeur = $this->makeDirecteur();

        $this->actingAs($directeur)->postJson('/api/tasks', [
            'department_id' => $department->id,
            'title' => 'Tâche supervisée par la direction',
            'description' => 'Créée directement par le directeur.',
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ])->assertCreated();
    }

    public function test_an_employee_cannot_create_a_task_via_the_real_endpoint(): void
    {
        $department = $this->makeDepartment();
        $employe = $this->makeEmploye($department);

        $this->actingAs($employe)->postJson('/api/tasks', [
            'department_id' => $department->id,
            'title' => 'Non autorisée',
            'description' => 'Test',
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ])->assertStatus(403);
    }
}
