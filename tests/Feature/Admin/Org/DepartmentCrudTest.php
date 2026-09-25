<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DepartmentCrudTest extends TestCase
{
    use DatabaseTransactions;

    /** See BUG-079 in known-bugs-report.md for why actingAs($user, 'backpack') can't be used here. */
    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    private function userWithManagePermission(): User
    {
        Permission::firstOrCreate(['name' => 'ORG_ENTITY_MANAGE', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $user->givePermissionTo('ORG_ENTITY_MANAGE');

        return $user;
    }

    public function test_updating_a_department_cannot_change_its_code(): void
    {
        $department = Department::create(['code' => 'ORIGDEPT', 'name' => 'Original Dept']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/department/'.$department->id),
            ['code' => 'HACKEDDEPT', 'name' => 'Updated Dept', 'is_active' => 1]
        );

        $response->assertRedirect(backpack_url('org/department'));
        $department->refresh();
        $this->assertSame('ORIGDEPT', $department->code);
        $this->assertSame('Updated Dept', $department->name);
    }

    public function test_creating_a_department_also_creates_its_default_division(): void
    {
        $user = $this->userWithManagePermission();

        $this->actingAsBackpackUser($user)->post(backpack_url('org/department'), [
            'code' => 'NEWDEPT', 'name' => 'New Dept', 'is_active' => 1,
        ]);

        $this->assertDatabaseHas('xlr8_admin_division', ['code' => 'NEWDEPT', 'dept_code' => 'NEWDEPT']);
    }

    public function test_disabling_a_department_is_blocked_while_it_has_an_active_division(): void
    {
        $department = Department::create(['code' => 'BLOCKDEPT', 'name' => 'Blocked Dept']);
        Division::create(['dept_code' => 'BLOCKDEPT', 'code' => 'BLOCKDIVN', 'name' => 'Blocking Division', 'is_active' => true]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/department/'.$department->id),
            ['code' => 'BLOCKDEPT', 'name' => 'Blocked Dept', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $department->fresh()->is_active);
    }

    public function test_disabling_a_department_is_blocked_while_it_has_an_active_employee(): void
    {
        $department = Department::create(['code' => 'BLOCKDEPT2', 'name' => 'Blocked Dept Two']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'primary_dept_code' => 'BLOCKDEPT2',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/department/'.$department->id),
            ['code' => 'BLOCKDEPT2', 'name' => 'Blocked Dept Two', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $department->fresh()->is_active);
    }

    public function test_disabling_a_department_succeeds_once_it_has_no_active_dependents(): void
    {
        $department = Department::create(['code' => 'FREEDEPT', 'name' => 'Free Dept']);
        Division::create(['dept_code' => 'FREEDEPT', 'code' => 'FREEDIVN', 'name' => 'Free Division', 'is_active' => false]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/department/'.$department->id),
            ['code' => 'FREEDEPT', 'name' => 'Free Dept', 'is_active' => 0]
        );

        $response->assertRedirect(backpack_url('org/department'));
        $this->assertFalse((bool) $department->fresh()->is_active);
    }

    public function test_a_user_without_the_permission_cannot_update_a_department(): void
    {
        $department = Department::create(['code' => 'NOPERMDEPT', 'name' => 'No Perm Dept']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/department/'.$department->id),
            ['code' => 'NOPERMDEPT', 'name' => 'Attempted Update', 'is_active' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Dept', $department->fresh()->name);
    }
}
