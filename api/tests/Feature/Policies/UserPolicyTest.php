<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    public function test_a_plain_employee_cannot_list_users(): void
    {
        $department = $this->makeDepartment();
        $employe = $this->makeEmploye($department);

        $this->assertFalse($employe->can('viewAny', User::class));
    }

    public function test_a_chef_can_view_the_user_list_but_not_manage_accounts(): void
    {
        $department = $this->makeDepartment();
        $chef = $this->makeChef($department);

        $this->assertTrue($chef->can('viewAny', User::class));
        // Voir la liste ne veut pas dire pouvoir créer/modifier des comptes.
        $this->assertFalse($chef->can('manage', User::class));
    }

    public function test_only_directeur_and_admin_can_manage_accounts(): void
    {
        $directeur = $this->makeDirecteur();
        $admin = $this->makeAdmin();

        $this->assertTrue($directeur->can('manage', User::class));
        $this->assertTrue($admin->can('manage', User::class));
    }
}
