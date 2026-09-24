<?php

namespace Tests\Feature\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_a_chef_admin_or_directeur_can_create_a_project(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $employe = $this->makeEmploye($department);
        $directeur = $this->makeDirecteur();
        $admin = $this->makeAdmin();

        $this->assertTrue($chef->can('create', Project::class));
        $this->assertTrue($admin->can('create', Project::class));
        // Contrairement aux tâches, le directeur a un accès total aux projets (comme l'admin).
        $this->assertTrue($directeur->can('create', Project::class));
        // Un employé sans aucun rôle projet ni de département ne peut pas en créer.
        $this->assertFalse($employe->can('create', Project::class));
    }

    public function test_directeur_has_full_access_to_any_project_like_admin(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $directeur = $this->makeDirecteur();
        $project = $this->makeProject($department, $chef);

        $this->assertTrue($directeur->can('view', $project));
        $this->assertTrue($directeur->can('update', $project));
        $this->assertTrue($directeur->can('delete', $project));
        $this->assertTrue($directeur->can('manageMembers', $project));
        $this->assertTrue($directeur->can('manageMilestones', $project));
        $this->assertTrue($directeur->can('manageBudget', $project));
        $this->assertTrue($directeur->can('manageDocuments', $project));
        $this->assertTrue($directeur->can('createActivity', $project));
    }

    public function test_holding_any_project_role_elsewhere_grants_the_right_to_create_a_new_project(): void
    {
        // Aligné sur le CDC : les 5 rôles projet (y compris Moniteur) peuvent créer
        // un projet, dès lors qu'ils sont déjà impliqués sur au moins un projet existant.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $moniteur = $this->makeEmploye($department);
        $existingProject = $this->makeProject($department, $chef);
        $this->attachProject($moniteur, $existingProject, ProjectRole::Moniteur);

        $this->assertTrue($moniteur->can('create', Project::class));
    }

    public function test_all_five_project_roles_have_manager_level_rights(): void
    {
        // Aligné sur le CDC : Chef de département, Chef de service, Coordonnateur/Facilitateur,
        // Facilitateur de zone et Moniteur ont tous les mêmes droits d'édition sur le projet.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $project = $this->makeProject($department, $chef);

        foreach (ProjectRole::cases() as $role) {
            $member = $this->makeEmploye($this->makeDepartment());
            $this->attachProject($member, $project, $role);

            $this->assertTrue($member->can('manageMilestones', $project), "manageMilestones a échoué pour {$role->value}");
            $this->assertTrue($member->can('manageBudget', $project), "manageBudget a échoué pour {$role->value}");
            $this->assertTrue($member->can('manageDocuments', $project), "manageDocuments a échoué pour {$role->value}");
            $this->assertTrue($member->can('update', $project), "update a échoué pour {$role->value}");
        }
    }

    public function test_an_outsider_cannot_view_a_project(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $outsider = $this->makeEmploye($this->makeDepartment());
        $project = $this->makeProject($department, $chef);

        $this->assertFalse($outsider->can('view', $project));
    }

    public function test_only_department_chef_can_delete_a_project(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $coordinator = $this->makeEmploye($department);
        $project = $this->makeProject($department, $chef);
        $this->attachProject($coordinator, $project, ProjectRole::CoordonnateurFacilitateur);

        // Rôle managérial sur le projet, mais pas chef de département : ne peut pas supprimer.
        $this->assertFalse($coordinator->can('delete', $project));
        $this->assertTrue($chef->can('delete', $project));
    }
}
