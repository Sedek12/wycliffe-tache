<?php

namespace Tests\Feature\Workflow;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class ProjectDocumentAndDashboardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_uploading_a_new_version_shares_the_same_group_key(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);

        $first = $this->actingAs($chef)->postJson("/api/projects/{$project->id}/deliverables", [
            'file' => UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $firstId = $first->json('data.deliverables.0.id');

        $second = $this->actingAs($chef)->postJson("/api/projects/{$project->id}/deliverables", [
            'file' => UploadedFile::fake()->create('rapport-v2.pdf', 100, 'application/pdf'),
            'replaces_id' => $firstId,
        ])->assertOk();

        $deliverables = collect($second->json('data.deliverables'));
        $this->assertCount(2, $deliverables);
        $this->assertSame(
            $deliverables->firstWhere('id', $firstId)['group_key'],
            $deliverables->firstWhere('version', 2)['group_key'],
        );
        $this->assertSame(2, $deliverables->firstWhere('version', 2)['version']);
    }

    public function test_a_non_manager_cannot_upload_a_project_document(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $employe = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);

        $this->actingAs($employe)->postJson("/api/projects/{$project->id}/deliverables", [
            'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertStatus(403);
    }

    public function test_dashboard_projects_endpoint_flags_projects_at_risk(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef, ['budget_previsionnel' => 100]);
        $project->expenses()->create([
            'title' => 'Dépassement',
            'amount' => 150,
            'date' => now(),
            'created_by' => $chef->id,
        ]);

        $response = $this->actingAs($chef)->getJson('/api/dashboard/projects')->assertOk();

        $this->assertSame(1, $response->json('at_risk_count'));
        $this->assertTrue($response->json('data.0.risk_flag'));
    }
}
