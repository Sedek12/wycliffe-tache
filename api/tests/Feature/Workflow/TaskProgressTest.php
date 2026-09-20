<?php

namespace Tests\Feature\Workflow;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskProgressTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_reporting_progress_raises_the_task_percentage_and_starts_it(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Assignee->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/progress", [
                'stage' => 'demarrage',
                'comment' => 'Je démarre la traduction.',
            ])
            ->assertOk();

        $task->refresh();
        $this->assertSame(30, $task->progress);
        $this->assertSame(TaskStatus::EnCours, $task->status);
    }

    public function test_progress_update_requires_either_a_file_or_a_comment(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/progress", ['stage' => 'demarrage'])
            ->assertStatus(422);
    }

    public function test_final_delivery_stage_locks_the_assignees_part_automatically(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/progress", [
                'stage' => 'livraison',
                'comment' => 'Version finale.',
            ])
            ->assertOk();

        $pivot = $task->assignees()->where('users.id', $assignee->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->is_done);
        $this->assertNotNull($pivot->done_at);
    }

    public function test_an_assignee_with_a_locked_part_cannot_submit_more_progress(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id, ['is_done' => true, 'done_at' => now()]);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/progress", [
                'stage' => 'finalisation',
                'comment' => 'Encore une retouche.',
            ])
            ->assertStatus(422);
    }

    public function test_a_progress_update_can_carry_a_file_proof(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->postJson("/api/tasks/{$task->id}/progress", [
                'stage' => 'mi_parcours',
                'file' => UploadedFile::fake()->create('brouillon.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $update = $task->progressUpdates()->first();
        Storage::disk('public')->assertExists($update->path);
    }

    public function test_chef_can_mark_a_progress_update_as_reviewed(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);
        $update = $task->progressUpdates()->create([
            'user_id' => $assignee->id,
            'percent' => 30,
            'stage' => 'demarrage',
            'comment' => 'Démarrage',
        ]);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/progress/{$update->id}/review", [])
            ->assertOk();

        $this->assertNotNull($update->fresh()->reviewed_at);
    }

    public function test_a_plain_teammate_cannot_review_a_colleagues_progress_via_the_api(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $author = $this->makeEmploye($department);
        $teammate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($author->id);
        $task->assignees()->attach($teammate->id);
        $update = $task->progressUpdates()->create([
            'user_id' => $author->id,
            'percent' => 30,
            'stage' => 'demarrage',
            'comment' => 'Démarrage',
        ]);

        $this->actingAs($teammate)
            ->postJson("/api/tasks/{$task->id}/progress/{$update->id}/review", [])
            ->assertStatus(403);
    }

    public function test_rejecting_a_locked_delivery_reopens_the_assignees_part(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id, ['is_done' => true, 'done_at' => now()]);
        $update = $task->progressUpdates()->create([
            'user_id' => $assignee->id,
            'percent' => 100,
            'stage' => 'livraison',
            'comment' => 'Version finale',
        ]);

        $this->actingAs($chef)
            ->postJson("/api/tasks/{$task->id}/progress/{$update->id}/reject", ['note' => 'À revoir'])
            ->assertOk();

        $pivot = $task->assignees()->where('users.id', $assignee->id)->first()->pivot;
        $this->assertFalse((bool) $pivot->is_done);
        $this->assertNotNull($update->fresh()->rejected_at);
    }
}
