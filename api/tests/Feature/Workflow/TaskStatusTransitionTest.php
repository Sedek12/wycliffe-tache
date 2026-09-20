<?php

namespace Tests\Feature\Workflow;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskStatusTransitionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_chef_can_walk_a_task_through_the_full_lifecycle(): void
    {
        Notification::fake();

        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Assignee->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'en_cours'])
            ->assertOk();
        $this->assertSame(TaskStatus::EnCours, $task->fresh()->status);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'livree'])
            ->assertOk();
        $task->refresh();
        $this->assertSame(TaskStatus::Livree, $task->status);
        $this->assertSame(100, $task->progress);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'validee'])
            ->assertOk();
        $task->refresh();
        $this->assertSame(TaskStatus::Validee, $task->status);
        $this->assertNotNull($task->completed_at);

        // Une entrée d'historique par transition effectuée.
        $this->assertSame(3, $task->statusHistory()->count());
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Brouillon->value]);

        // brouillon -> validee n'est pas dans les transitions autorisées.
        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'validee'])
            ->assertStatus(422);

        $this->assertSame(TaskStatus::Brouillon, $task->fresh()->status);
    }

    public function test_a_plain_assignee_can_only_deliver_a_task_in_progress(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        // Autorisé : en_cours -> livree.
        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'livree'])
            ->assertOk();
        $this->assertSame(TaskStatus::Livree, $task->fresh()->status);
    }

    public function test_a_plain_assignee_cannot_perform_other_transitions(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        // en_cours -> annulee est une transition légale dans la machine à états,
        // mais réservée au chef / responsable : un assigné ne peut que livrer.
        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'annulee'])
            ->assertStatus(422);

        $this->assertSame(TaskStatus::EnCours, $task->fresh()->status);
    }

    public function test_delegated_supervisor_without_change_status_cannot_transition(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $delegate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, [
            'status' => TaskStatus::EnCours->value,
            'supervisor_id' => $delegate->id,
            'delegated_abilities' => [], // aucun pouvoir délégué
        ]);

        $this->actingAs($delegate)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'annulee'])
            ->assertStatus(403);
    }

    public function test_delegated_supervisor_with_change_status_can_transition_freely(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $delegate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, [
            'status' => TaskStatus::EnCours->value,
            'supervisor_id' => $delegate->id,
            'delegated_abilities' => [\App\Models\Task::ABILITY_CHANGE_STATUS],
        ]);

        $this->actingAs($delegate)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'annulee'])
            ->assertOk();

        $this->assertSame(TaskStatus::Annulee, $task->fresh()->status);
    }

    public function test_a_validated_task_cannot_transition_further(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Validee->value]);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'assignee'])
            ->assertStatus(422);
    }
}
