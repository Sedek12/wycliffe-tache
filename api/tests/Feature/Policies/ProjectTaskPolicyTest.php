<?php

namespace Tests\Feature\Policies;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class ProjectTaskPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_a_project_manager_gets_chef_level_on_that_projects_activities(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $coordinator = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);
        $this->attachProject($coordinator, $project, ProjectRole::CoordonnateurFacilitateur);

        $activity = $this->makeTask($department, $chef, ['project_id' => $project->id]);

        // Le coordonnateur n'est pas chef de département, mais gère ce projet.
        $this->assertTrue($coordinator->can('update', $activity));
        $this->assertTrue($coordinator->can('manageTeam', $activity));
        $this->assertTrue($coordinator->can('manageDependencies', $activity));
    }

    public function test_a_moniteur_also_gets_chef_level_on_project_activities(): void
    {
        // Aligné sur le CDC : Moniteur a les mêmes droits d'édition que les autres
        // rôles projet sur les activités (contrairement au rôle de département
        // "employé", qui lui reste sans droits d'édition en dehors du projet).
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $moniteur = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);
        $this->attachProject($moniteur, $project, ProjectRole::Moniteur);

        $activity = $this->makeTask($department, $chef, ['project_id' => $project->id]);

        $this->assertTrue($moniteur->can('update', $activity));
        $this->assertTrue($moniteur->can('manageDependencies', $activity));
    }

    public function test_a_department_employee_without_any_project_role_has_no_chef_level_on_activities(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $outsider = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);

        $activity = $this->makeTask($department, $chef, ['project_id' => $project->id]);

        $this->assertFalse($outsider->can('update', $activity));
        $this->assertFalse($outsider->can('manageDependencies', $activity));
    }

    public function test_a_project_member_can_view_the_projects_activities(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $member = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);
        $this->attachProject($member, $project, ProjectRole::Moniteur);

        $activity = $this->makeTask($department, $chef, ['project_id' => $project->id]);

        $this->assertTrue($member->can('view', $activity));
    }

    public function test_directeur_gets_chef_level_on_project_activities_without_being_a_project_member(): void
    {
        // Le directeur a un accès total aux projets (comme l'admin), même sans être
        // explicitement ajouté dans project_user.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $directeur = $this->makeDirecteur();
        $project = $this->makeProject($department, $chef);

        $activity = $this->makeTask($department, $chef, ['project_id' => $project->id]);

        $this->assertTrue($directeur->can('update', $activity));
        $this->assertTrue($directeur->can('manageTeam', $activity));
        $this->assertTrue($directeur->can('manageDependencies', $activity));
    }

    public function test_directeur_can_also_manage_a_plain_department_task_outside_any_project(): void
    {
        // Le directeur a désormais un accès total aux tâches, comme l'admin
        // (before() -> isSupervisor()), y compris hors de tout projet.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $directeur = $this->makeDirecteur();
        $task = $this->makeTask($department, $chef); // pas de project_id

        $this->assertTrue($directeur->can('update', $task));
        $this->assertTrue($directeur->can('create', \App\Models\Task::class));
    }
}
