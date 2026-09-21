<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Models\Admin\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BranchCrudTest extends TestCase
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

    public function test_updating_a_branch_cannot_change_its_code(): void
    {
        $branch = Branch::create(['code' => 'ORIGBRCH', 'name' => 'Original Branch']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$branch->code),
            ['code' => 'HACKEDBRCH', 'name' => 'Updated Branch', 'is_active' => 1]
        );

        $response->assertRedirect(backpack_url('org/branch'));
        $branch->refresh();
        $this->assertSame('ORIGBRCH', $branch->code);
        $this->assertSame('Updated Branch', $branch->name);
    }

    public function test_disabling_a_branch_is_blocked_while_it_has_an_active_location(): void
    {
        $branch = Branch::create(['code' => 'BLOCKBRCH', 'name' => 'Blocked Branch']);
        Location::create(['branch_code' => 'BLOCKBRCH', 'code' => 'BLOCKLOCN', 'name' => 'Blocking Location', 'is_active' => true]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$branch->code),
            ['code' => 'BLOCKBRCH', 'name' => 'Blocked Branch', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $branch->fresh()->is_active);
    }

    public function test_disabling_a_branch_is_blocked_while_it_has_an_active_employee(): void
    {
        $branch = Branch::create(['code' => 'BLOCKBRCH2', 'name' => 'Blocked Branch Two']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'primary_branch_code' => 'BLOCKBRCH2',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$branch->code),
            ['code' => 'BLOCKBRCH2', 'name' => 'Blocked Branch Two', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $branch->fresh()->is_active);
    }

    public function test_disabling_a_branch_succeeds_once_it_has_no_active_dependents(): void
    {
        $branch = Branch::create(['code' => 'FREEBRCH', 'name' => 'Free Branch']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$branch->code),
            ['code' => 'FREEBRCH', 'name' => 'Free Branch', 'is_active' => 0]
        );

        $response->assertRedirect(backpack_url('org/branch'));
        $this->assertFalse((bool) $branch->fresh()->is_active);
    }

    public function test_marking_a_branch_as_head_office_unsets_the_previous_one(): void
    {
        $first = Branch::create(['code' => 'HQOLD', 'name' => 'Old HQ', 'is_head_office' => true]);
        $second = Branch::create(['code' => 'HQNEW', 'name' => 'New HQ']);
        $user = $this->userWithManagePermission();

        $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$second->code),
            ['code' => 'HQNEW', 'name' => 'New HQ', 'is_active' => 1, 'is_head_office' => 1]
        );

        $this->assertFalse((bool) $first->fresh()->is_head_office);
        $this->assertTrue((bool) $second->fresh()->is_head_office);
    }

    public function test_a_user_without_the_permission_cannot_update_a_branch(): void
    {
        $branch = Branch::create(['code' => 'NOPERMBRCH', 'name' => 'No Perm Branch']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/branch/'.$branch->code),
            ['code' => 'NOPERMBRCH', 'name' => 'Attempted Update', 'is_active' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Branch', $branch->fresh()->name);
    }
}
