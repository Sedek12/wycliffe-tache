<?php

namespace Tests\Feature\Policies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class DepartmentPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_only_directeur_and_admin_can_create_or_delete_departments(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $directeur = $this->makeDirecteur();
        $admin = $this->makeAdmin();

        $this->assertFalse($chef->can('create', \App\Models\Department::class));
        $this->assertTrue($directeur->can('create', \App\Models\Department::class));
        $this->assertTrue($admin->can('create', \App\Models\Department::class));

        $this->assertFalse($chef->can('delete', $department));
        $this->assertTrue($directeur->can('delete', $department));
    }

    public function test_a_department_chef_cannot_manage_its_own_members(): void
    {
        // Contre-intuitif mais volontaire : nommer/retirer des membres reste
        // une prérogative du directeur (et de l'admin), même pour le chef du département.
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);
        $directeur = $this->makeDirecteur();

        $this->assertFalse($chef->can('manageMembers', $department));
        $this->assertTrue($directeur->can('manageMembers', $department));
    }

    public function test_only_members_and_supervisors_can_view_a_department(): void
    {
        $department = $this->makeDepartment();
        $member = $this->makeEmploye($department);
        $outsider = $this->makeEmploye($this->makeDepartment());
        $directeur = $this->makeDirecteur();

        $this->assertTrue($member->can('view', $department));
        $this->assertFalse($outsider->can('view', $department));
        $this->assertTrue($directeur->can('view', $department));
    }
}
