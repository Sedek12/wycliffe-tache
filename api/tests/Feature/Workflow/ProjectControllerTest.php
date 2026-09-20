<?php

namespace Tests\Feature\Workflow;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_chef_can_create_a_project_and_becomes_its_manager(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);

        $response = $this->actingAs($chef)->postJson('/api/projects', [
            'department_id' => $department->id,
            'title' => 'Programme de traduction 2026',
            'description' => 'PTAB annuel',
            'effet' => 'Les Écritures sont accessibles',
            'extrant' => 'Nouveau Testament traduit',
            'budget_previsionnel' => 5000000,
            'parties_prenantes' => ['Église locale', 'Bailleur X'],
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addYear()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Programme de traduction 2026');
        $response->assertJsonPath('data.status', 'brouillon');

        $project = \App\Models\Project::first();
        $this->assertTrue($project->isManagedBy($chef->id));
    }

    public function test_an_employee_cannot_create_a_project(): void
    {
        $department = $this->makeDepartment();
        $employe = $this->makeEmploye($department);

        $this->actingAs($employe)->postJson('/api/projects', [
            'department_id' => $department->id,
            'title' => 'Projet non autorisé',
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_project_status_follows_its_own_transition_rules(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);

        // brouillon -> cloture directement : interdit.
        $this->actingAs($chef)
            ->putJson("/api/projects/{$project->id}", ['status' => 'cloture'])
            ->assertStatus(422);

        $this->actingAs($chef)
            ->putJson("/api/projects/{$project->id}", ['status' => 'en_cours'])
            ->assertOk()
            ->assertJsonPath('data.status', 'en_cours');
    }

    public function test_creating_an_activity_under_a_project_inherits_its_department(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);

        $response = $this->actingAs($chef)->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Traduire Marc',
            'description' => 'Activité PTAB',
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.project_id', $project->id);
        $response->assertJsonPath('data.department_id', $department->id);
    }

    public function test_project_coordinator_can_create_an_activity_without_being_department_chef(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $coordinator = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);
        $this->attachProject($coordinator, $project, ProjectRole::CoordonnateurFacilitateur);

        $this->actingAs($coordinator)->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Réviser Luc',
            'description' => 'Activité PTAB',
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ])->assertCreated();
    }
}
