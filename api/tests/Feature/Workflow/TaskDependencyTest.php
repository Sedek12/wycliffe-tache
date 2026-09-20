<?php

namespace Tests\Feature\Workflow;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskDependencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_chef_can_add_a_dependency_between_two_activities(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);
        $taskA = $this->makeTask($department, $chef, ['project_id' => $project->id, 'title' => 'A']);
        $taskB = $this->makeTask($department, $chef, ['project_id' => $project->id, 'title' => 'B']);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$taskB->id}/dependencies", ['depends_on_task_id' => $taskA->id])
            ->assertOk();

        $this->assertTrue($taskB->fresh()->dependsOn->contains('id', $taskA->id));
    }

    public function test_a_task_cannot_depend_on_itself(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $task = $this->makeTask($department, $chef);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/dependencies", ['depends_on_task_id' => $task->id])
            ->assertStatus(422);
    }

    public function test_a_reverse_dependency_cycle_is_rejected(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $taskA = $this->makeTask($department, $chef, ['title' => 'A']);
        $taskB = $this->makeTask($department, $chef, ['title' => 'B']);

        $taskB->dependsOn()->attach($taskA->id);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$taskA->id}/dependencies", ['depends_on_task_id' => $taskB->id])
            ->assertStatus(422);
    }

    public function test_starting_a_task_is_blocked_while_a_dependency_is_unmet(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $taskA = $this->makeTask($department, $chef, ['title' => 'A', 'status' => TaskStatus::Assignee->value]);
        $taskB = $this->makeTask($department, $chef, ['title' => 'B', 'status' => TaskStatus::Assignee->value]);
        $taskB->dependsOn()->attach($taskA->id);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$taskB->id}/status", ['status' => 'en_cours'])
            ->assertStatus(422);
    }

    public function test_starting_a_task_succeeds_once_its_dependency_is_validated(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $taskA = $this->makeTask($department, $chef, ['title' => 'A', 'status' => TaskStatus::Validee->value]);
        $taskB = $this->makeTask($department, $chef, ['title' => 'B', 'status' => TaskStatus::Assignee->value]);
        $taskB->dependsOn()->attach($taskA->id);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$taskB->id}/status", ['status' => 'en_cours'])
            ->assertOk();
    }
}
