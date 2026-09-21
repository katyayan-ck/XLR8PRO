<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DesignationCrudTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * actingAs($user, 'backpack') calls Auth::shouldUse('backpack'), which — unlike a real
     * Backpack login — overwrites config('auth.defaults.guard'). Spatie resolves a
     * permission's guard from that same config value when none is given explicitly, so every
     * permission this app seeds with guard_name 'web' would otherwise appear not to exist.
     * Logging in directly on the 'backpack' guard without touching the default sidesteps that
     * testing-only artifact and matches how the real login flow behaves.
     */
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

    public function test_updating_a_designation_cannot_change_its_code(): void
    {
        $designation = Designation::create(['code' => 'ORIG_CODE', 'name' => 'Original Name']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'HACKED_CODE', 'name' => 'Updated Name', 'is_active' => 1, 'rank' => 1]
        );

        $response->assertRedirect(backpack_url('org/designation'));
        $designation->refresh();
        $this->assertSame('ORIG_CODE', $designation->code);
        $this->assertSame('Updated Name', $designation->name);
    }

    public function test_disabling_a_designation_is_blocked_while_it_has_active_employees(): void
    {
        $designation = Designation::create(['code' => 'BLOCKED', 'name' => 'Blocked Designation']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'designation_code' => 'BLOCKED',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'BLOCKED', 'name' => 'Blocked Designation', 'is_active' => 0, 'rank' => 1]
        );

        $response->assertSessionHasErrors('is_active');
        $designation->refresh();
        $this->assertTrue((bool) $designation->is_active);
    }

    public function test_disabling_a_designation_succeeds_once_it_has_no_active_employees(): void
    {
        $designation = Designation::create(['code' => 'FREE_TO_DISABLE', 'name' => 'Free Designation']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'FREE_TO_DISABLE', 'name' => 'Free Designation', 'is_active' => 0, 'rank' => 1]
        );

        $response->assertRedirect(backpack_url('org/designation'));
        $designation->refresh();
        $this->assertFalse((bool) $designation->is_active);
    }

    public function test_a_user_without_the_permission_cannot_update_a_designation(): void
    {
        $designation = Designation::create(['code' => 'NO_PERM', 'name' => 'No Perm Designation']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'NO_PERM', 'name' => 'Attempted Update', 'is_active' => 1, 'rank' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Designation', $designation->fresh()->name);
    }

    public function test_a_designation_cannot_report_to_a_lower_ranked_designation(): void
    {
        Designation::create(['code' => 'LOW_RANK', 'name' => 'Low Rank Designation', 'rank' => 5]);
        $designation = Designation::create(['code' => 'HIGH_RANK', 'name' => 'High Rank Designation', 'rank' => 1]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'HIGH_RANK', 'name' => 'High Rank Designation', 'is_active' => 1, 'rank' => 1, 'parent_desig_code' => 'LOW_RANK']
        );

        $response->assertSessionHasErrors('parent_desig_code');
        $this->assertNull($designation->fresh()->parent_desig_code);
    }

    public function test_a_designation_can_report_to_a_same_or_higher_ranked_designation(): void
    {
        Designation::create(['code' => 'TOP_RANK', 'name' => 'Top Rank Designation', 'rank' => 1]);
        $designation = Designation::create(['code' => 'MID_RANK', 'name' => 'Mid Rank Designation', 'rank' => 3]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/designation/'.$designation->id),
            ['code' => 'MID_RANK', 'name' => 'Mid Rank Designation', 'is_active' => 1, 'rank' => 3, 'parent_desig_code' => 'TOP_RANK']
        );

        $response->assertRedirect(backpack_url('org/designation'));
        $this->assertSame('TOP_RANK', $designation->fresh()->parent_desig_code);
    }

    public function test_permissions_can_be_synced_to_a_designations_role(): void
    {
        $designation = Designation::create(['code' => 'PERM_SYNC', 'name' => 'Perm Sync Designation']);
        Permission::firstOrCreate(['name' => 'SLS_BKNG_VIEW', 'guard_name' => 'web']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->putJson(
            backpack_url('org/designation/'.$designation->id.'/permissions'),
            ['permissions' => ['ORG_ENTITY_MANAGE', 'SLS_BKNG_VIEW']]
        );

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['ORG_ENTITY_MANAGE', 'SLS_BKNG_VIEW'],
            $designation->fresh()->permissions()->pluck('name')->all()
        );
    }
}
