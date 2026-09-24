<?php

namespace Tests\Feature\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_a_chef_admin_or_directeur_can_create_a_task(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $employe = $this->makeEmploye($department);
        $directeur = $this->makeDirecteur();
        $admin = $this->makeAdmin();

        $this->assertTrue($chef->can('create', Task::class));
        $this->assertTrue($admin->can('create', Task::class));
        // Le directeur a un accès total, comme l'admin (avant() -> isSupervisor()).
        $this->assertTrue($directeur->can('create', Task::class));
        $this->assertFalse($employe->can('create', Task::class));
    }

    public function test_an_unrelated_employee_cannot_view_the_task(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $outsider = $this->makeEmploye($this->makeDepartment());
        $task = $this->makeTask($department, $chef);

        $this->assertFalse($outsider->can('view', $task));
        $this->assertTrue($chef->can('view', $task));
    }

    public function test_an_assignee_can_view_but_not_update_the_task(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef);
        $task->assignees()->attach($assignee->id);

        $this->assertTrue($assignee->can('view', $task));
        // Modifier la tâche (titre, description, responsable...) reste réservé au chef,
        // même pour un assigné : seul le suivi d'avancement lui est ouvert.
        $this->assertFalse($assignee->can('update', $task));
        $this->assertTrue($chef->can('update', $task));
    }

    public function test_a_validated_task_can_no_longer_be_updated_even_by_its_chef(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Validee->value]);

        $this->assertFalse($chef->can('update', $task));
    }

    public function test_only_the_creating_chef_can_evaluate_the_task(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $employe = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef);

        $this->assertTrue($chef->can('evaluate', $task));
        $this->assertFalse($employe->can('evaluate', $task));
    }

    public function test_delegated_supervisor_gains_only_the_abilities_explicitly_granted(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $delegate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, [
            'supervisor_id' => $delegate->id,
            'delegated_abilities' => [Task::ABILITY_MANAGE_TEAM],
        ]);

        // Le pouvoir délégué (gérer l'équipe) est bien accordé au responsable...
        $this->assertTrue($delegate->can('manageTeam', $task));
        // ... mais pas les pouvoirs non délégués (demander une collaboration, par ex).
        $this->assertFalse($delegate->can('requestCollaboration', $task));
        $this->assertFalse($delegate->can('createSubtask', $task));
    }

    public function test_a_plain_assignee_without_delegation_can_still_report_progress(): void
    {
        // hasAssignee() ouvre déjà updateProgress/submitDeliverable/changeStatus,
        // sans qu'aucune délégation explicite soit nécessaire.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $assignee = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef);
        $task->assignees()->attach($assignee->id);

        $this->assertTrue($assignee->can('updateProgress', $task));
        $this->assertTrue($assignee->can('submitDeliverable', $task));
        $this->assertTrue($assignee->can('changeStatus', $task));

        // Mais pas gérer l'équipe ou déléguer, ça reste réservé au chef / responsable désigné.
        $this->assertFalse($assignee->can('manageTeam', $task));
    }

    public function test_a_plain_teammate_cannot_review_or_reject_another_members_progress(): void
    {
        // Régression : TaskProgressController::review()/reject() utilisaient l'ability
        // changeStatus, qui accorde aussi ce pouvoir à n'importe quel assigné via
        // hasAssignee(). Le frontend masquait les boutons, mais l'API restait ouverte.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $teammate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef);
        $task->assignees()->attach($teammate->id);

        $this->assertTrue($teammate->can('changeStatus', $task));
        $this->assertFalse($teammate->can('reviewProgress', $task));
        $this->assertTrue($chef->can('reviewProgress', $task));
    }

    public function test_delegated_change_status_ability_also_grants_review_progress(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $delegate = $this->makeEmploye($department);
        $task = $this->makeTask($department, $chef, [
            'supervisor_id' => $delegate->id,
            'delegated_abilities' => [Task::ABILITY_CHANGE_STATUS],
        ]);

        $this->assertTrue($delegate->can('reviewProgress', $task));
    }

    public function test_admin_bypasses_every_task_ability(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $admin = $this->makeAdmin();
        $task = $this->makeTask($department, $chef, ['status' => TaskStatus::Validee->value]);

        // Même une tâche validée (verrouillée pour tout le monde) reste modifiable par l'admin.
        $this->assertTrue($admin->can('update', $task));
        $this->assertTrue($admin->can('evaluate', $task));
    }
}
