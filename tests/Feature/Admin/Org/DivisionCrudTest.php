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

class DivisionCrudTest extends TestCase
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

    public function test_updating_a_division_cannot_change_its_code(): void
    {
        $department = Department::create(['code' => 'PARENTDEPT', 'name' => 'Parent Dept']);
        $division = Division::create(['dept_code' => 'PARENTDEPT', 'code' => 'ORIGDIVN', 'name' => 'Original Division']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/division/'.$division->id),
            ['dept_code' => 'PARENTDEPT', 'code' => 'HACKEDDIVN', 'name' => 'Updated Division', 'is_active' => 1]
        );

        $response->assertRedirect(backpack_url('org/division'));
        $division->refresh();
        $this->assertSame('ORIGDIVN', $division->code);
        $this->assertSame('Updated Division', $division->name);
    }

    public function test_a_division_cannot_be_activated_under_an_inactive_department(): void
    {
        $department = Department::create(['code' => 'INACTDEPT', 'name' => 'Inactive Dept', 'is_active' => false]);
        $division = Division::create(['dept_code' => 'INACTDEPT', 'code' => 'LOCKEDDIVN', 'name' => 'Locked Division', 'is_active' => false]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/division/'.$division->id),
            ['dept_code' => 'INACTDEPT', 'code' => 'LOCKEDDIVN', 'name' => 'Locked Division', 'is_active' => 1]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertFalse((bool) $division->fresh()->is_active);
    }

    public function test_disabling_a_division_is_blocked_while_it_has_an_active_employee(): void
    {
        $department = Department::create(['code' => 'DEPTBLK', 'name' => 'Dept Blocking']);
        $division = Division::create(['dept_code' => 'DEPTBLK', 'code' => 'BLOCKDIVN', 'name' => 'Blocked Division']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'primary_div_code' => 'BLOCKDIVN',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/division/'.$division->id),
            ['dept_code' => 'DEPTBLK', 'code' => 'BLOCKDIVN', 'name' => 'Blocked Division', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $division->fresh()->is_active);
    }

    public function test_disabling_a_division_succeeds_once_it_has_no_active_employees(): void
    {
        $department = Department::create(['code' => 'DEPTFREE', 'name' => 'Dept Free']);
        $division = Division::create(['dept_code' => 'DEPTFREE', 'code' => 'FREEDIVN', 'name' => 'Free Division']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/division/'.$division->id),
            ['dept_code' => 'DEPTFREE', 'code' => 'FREEDIVN', 'name' => 'Free Division', 'is_active' => 0]
        );

        $response->assertRedirect(backpack_url('org/division'));
        $this->assertFalse((bool) $division->fresh()->is_active);
    }

    public function test_a_user_without_the_permission_cannot_update_a_division(): void
    {
        $department = Department::create(['code' => 'DEPTNP', 'name' => 'Dept No Perm']);
        $division = Division::create(['dept_code' => 'DEPTNP', 'code' => 'NOPERMDIVN', 'name' => 'No Perm Division']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/division/'.$division->id),
            ['dept_code' => 'DEPTNP', 'code' => 'NOPERMDIVN', 'name' => 'Attempted Update', 'is_active' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Division', $division->fresh()->name);
    }
}
