<?php

namespace Tests\Feature\Workflow;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskAssigneeCompletionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_completing_my_part_requires_at_least_one_proof(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/complete-my-part")
            ->assertStatus(422);
    }

    public function test_completing_my_part_succeeds_once_a_proof_exists(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);
        $task->progressUpdates()->create([
            'user_id' => $assignee->id,
            'percent' => 70,
            'stage' => 'mi_parcours',
            'comment' => 'Bien avancé.',
        ]);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/complete-my-part")
            ->assertOk();

        $pivot = $task->assignees()->where('users.id', $assignee->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->is_done);
    }

    public function test_a_non_assignee_cannot_complete_a_part(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $outsider = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);

        $this->actingAs($outsider)
            ->postJson("/api/tasks/{$task->id}/complete-my-part")
            ->assertStatus(403);
    }

    public function test_an_assignee_can_request_the_reopening_of_a_locked_part(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id, ['is_done' => true, 'done_at' => now()]);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/request-reopen")
            ->assertOk();

        $pivot = $task->assignees()->where('users.id', $assignee->id)->first()->pivot;
        $this->assertNotNull($pivot->reopen_requested_at);
    }

    public function test_chef_can_reopen_an_assignees_locked_part(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id, ['is_done' => true, 'done_at' => now()]);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/assignees/{$assignee->id}/reopen")
            ->assertOk();

        $pivot = $task->assignees()->where('users.id', $assignee->id)->first()->pivot;
        $this->assertFalse((bool) $pivot->is_done);
        $this->assertNull($pivot->reopen_requested_at);
    }

    public function test_a_plain_teammate_cannot_reopen_someone_elses_part(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $teammate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id, ['is_done' => true, 'done_at' => now()]);
        $task->assignees()->attach($teammate->id);

        $this->actingAs($teammate)
            ->postJson("/api/tasks/{$task->id}/assignees/{$assignee->id}/reopen")
            ->assertStatus(403);
    }
}
