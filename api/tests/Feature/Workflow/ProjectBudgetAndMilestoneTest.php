<?php

namespace Tests\Feature\Workflow;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class ProjectBudgetAndMilestoneTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_expenses_accumulate_into_depenses_engagees_and_consumed_pct(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef, ['budget_previsionnel' => 1000]);

        $this->actingAs($chef)->postJson("/api/projects/{$project->id}/expenses", [
            'title' => 'Achat matériel',
            'amount' => 250,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $this->actingAs($chef)->postJson("/api/projects/{$project->id}/expenses", [
            'title' => 'Transport',
            'amount' => 150,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $fresh = $project->fresh();
        $this->assertSame(400.0, $fresh->depenses_engagees);
        $this->assertSame(40.0, $fresh->budget_consumed_pct);
    }

    public function test_a_non_manager_cannot_record_an_expense(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $employe = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);

        $this->actingAs($employe)->postJson("/api/projects/{$project->id}/expenses", [
            'title' => 'Non autorisé',
            'amount' => 10,
            'date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_chef_can_create_and_reach_a_milestone(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);

        $create = $this->actingAs($chef)->postJson("/api/projects/{$project->id}/milestones", [
            'title' => 'Validation du Nouveau Testament',
            'date' => now()->addMonths(6)->toDateString(),
        ]);
        $create->assertCreated();
        $milestoneId = $create->json('data.id');

        $this->actingAs($chef)
            ->putJson("/api/milestones/{$milestoneId}", ['is_reached' => true])
            ->assertOk()
            ->assertJsonPath('data.is_reached', true);
    }
}
