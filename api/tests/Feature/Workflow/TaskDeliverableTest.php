<?php

namespace Tests\Feature\Workflow;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskDeliverableTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_directeur_can_delete_any_task_deliverable_via_the_real_endpoint(): void
    {
        // Régression : la policy autorisait déjà le directeur (before() -> isSupervisor()),
        // mais DeliverableController::destroy() avait son propre abort_unless() manuel
        // (uploadeur/chef/responsable/isAdmin()) qui le bloquait quand même — même bug
        // que sur ProjectController::store() et TaskController::store().
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $directeur = $this->makeDirecteur();
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::EnCours->value]);
        $task->assignees()->attach($assignee->id);

        $upload = $this->actingAs($assignee)->postJson("/api/tasks/{$task->id}/deliverables", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertOk();

        $deliverableId = $upload->json('data.deliverables.0.id');

        $this->actingAs($directeur)
            ->deleteJson("/api/tasks/{$task->id}/deliverables/{$deliverableId}")
            ->assertOk();
    }
}
