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

class LocationCrudTest extends TestCase
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

    public function test_updating_a_location_cannot_change_its_code(): void
    {
        $branch = Branch::create(['code' => 'PARENTBRCH', 'name' => 'Parent Branch']);
        $location = Location::create(['branch_code' => 'PARENTBRCH', 'code' => 'ORIGLOCN', 'name' => 'Original Location']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/location/'.$location->id),
            ['branch_code' => 'PARENTBRCH', 'code' => 'HACKEDLOCN', 'name' => 'Updated Location', 'is_active' => 1]
        );

        $response->assertRedirect(backpack_url('org/location'));
        $location->refresh();
        $this->assertSame('ORIGLOCN', $location->code);
        $this->assertSame('Updated Location', $location->name);
    }

    public function test_disabling_a_location_is_blocked_while_it_has_an_active_employee(): void
    {
        $branch = Branch::create(['code' => 'BRCHBLK', 'name' => 'Branch Blocking']);
        $location = Location::create(['branch_code' => 'BRCHBLK', 'code' => 'BLOCKLOCN', 'name' => 'Blocked Location']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'primary_loc_code' => 'BLOCKLOCN',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/location/'.$location->id),
            ['branch_code' => 'BRCHBLK', 'code' => 'BLOCKLOCN', 'name' => 'Blocked Location', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $location->fresh()->is_active);
    }

    public function test_disabling_a_location_succeeds_once_it_has_no_active_employees(): void
    {
        $branch = Branch::create(['code' => 'BRCHFREE', 'name' => 'Branch Free']);
        $location = Location::create(['branch_code' => 'BRCHFREE', 'code' => 'FREELOCN', 'name' => 'Free Location']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/location/'.$location->id),
            ['branch_code' => 'BRCHFREE', 'code' => 'FREELOCN', 'name' => 'Free Location', 'is_active' => 0]
        );

        $response->assertRedirect(backpack_url('org/location'));
        $this->assertFalse((bool) $location->fresh()->is_active);
    }

    public function test_a_user_without_the_permission_cannot_update_a_location(): void
    {
        $branch = Branch::create(['code' => 'BRCHNP', 'name' => 'Branch No Perm']);
        $location = Location::create(['branch_code' => 'BRCHNP', 'code' => 'NOPERMLOCN', 'name' => 'No Perm Location']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/location/'.$location->id),
            ['branch_code' => 'BRCHNP', 'code' => 'NOPERMLOCN', 'name' => 'Attempted Update', 'is_active' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Location', $location->fresh()->name);
    }
}
