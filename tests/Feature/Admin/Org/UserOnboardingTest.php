<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Location;
use App\Models\IAM\Role;
use App\Models\User;
use App\Services\HR\EmployeeJourneyService;
use App\Services\PersonService;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    /** See BUG-079 in known-bugs-report.md for why actingAs($user, 'backpack') can't be used here. */
    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    private function adminUser(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $user = User::create([
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /** Short (<=10 char) unique suffix — several org-entity `code` columns are varchar(10). */
    private function shortCode(): string
    {
        return strtoupper(substr(uniqid(), -6));
    }

    /** @return array{branch: Branch, location: Location, department: Department, division: Division, designation: Designation} */
    private function orgFixture(): array
    {
        $suffix = $this->shortCode();
        $branch = Branch::create(['code' => 'B'.$suffix, 'name' => 'Test Branch']);
        $location = Location::create(['branch_code' => $branch->code, 'code' => 'L'.$suffix, 'name' => 'Test Location']);
        $department = Department::create(['code' => 'D'.$suffix, 'name' => 'Test Department']);
        $division = Division::create(['dept_code' => $department->code, 'code' => 'V'.$suffix, 'name' => 'Test Division']);
        $designation = Designation::create(['code' => 'G'.$suffix, 'name' => 'Test Designation', 'rank' => 3]);

        return compact('branch', 'location', 'department', 'division', 'designation');
    }

    public function test_user_type_seeder_creates_dsa_and_customer_types(): void
    {
        (new UserTypeSeeder)->run();

        $this->assertDatabaseHas('xlr8_iam_user_type', ['code' => 'DSA']);
        $this->assertDatabaseHas('xlr8_iam_user_type', ['code' => 'CUST']);
    }

    public function test_creating_an_employee_user_creates_the_employee_assigns_the_role_and_records_initial_history(): void
    {
        $fixture = $this->orgFixture();
        $person = PersonService::upsert([
            'display_name' => 'Onboarding Test Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9800011122', 'is_primary' => true]],
        ]);
        $admin = $this->adminUser(['ORG_USER_CREATE']);

        $response = $this->actingAsBackpackUser($admin)->post(backpack_url('org/user'), [
            'person_code' => $person->person_code,
            'user_type_code' => 'emp',
            'username' => 'onboarding_test_user',
            'password' => 'password123',
            'designation_code' => $fixture['designation']->code,
            'primary_branch_code' => $fixture['branch']->code,
            'primary_loc_code' => $fixture['location']->code,
            'primary_dept_code' => $fixture['department']->code,
            'primary_div_code' => $fixture['division']->code,
            'is_active' => '1',
        ]);

        $user = User::where('username', 'onboarding_test_user')->first();
        $response->assertRedirect(backpack_url("org/user/{$user->id}/show"));

        $employee = Employee::where('code', $user->employee_code)->first();
        $this->assertNotNull($employee, 'Employee record was not created');
        $this->assertSame($fixture['designation']->code, $employee->designation_code);
        $this->assertSame($fixture['branch']->code, $employee->primary_branch_code);

        $role = Role::where('code', $fixture['designation']->code)->first();
        $this->assertTrue($user->hasRole($role->name));

        $history = EmployeeHistory::where('emp_code', $employee->code)->first();
        $this->assertNotNull($history, 'No EmployeeHistory record was written on creation');
        $this->assertSame($fixture['branch']->code, $history->primary_branch_code);
    }

    public function test_addon_branches_and_permission_overrides_are_applied_on_create(): void
    {
        $fixture = $this->orgFixture();
        $addonBranch = Branch::create(['code' => 'B'.$this->shortCode(), 'name' => 'Addon Branch']);
        $person = PersonService::upsert([
            'display_name' => 'Addon Onboarding Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9800011123', 'is_primary' => true]],
        ]);
        Permission::firstOrCreate(['name' => 'TEST_ONBOARD_ADD', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['code' => $fixture['designation']->code], ['name' => $fixture['designation']->name, 'guard_name' => 'web']);
        $basePerm = Permission::firstOrCreate(['name' => 'TEST_ONBOARD_BASE', 'guard_name' => 'web']);
        $role->givePermissionTo($basePerm);
        $admin = $this->adminUser(['ORG_USER_CREATE']);

        $this->actingAsBackpackUser($admin)->post(backpack_url('org/user'), [
            'person_code' => $person->person_code,
            'user_type_code' => 'emp',
            'username' => 'addon_onboard_user',
            'password' => 'password123',
            'designation_code' => $fixture['designation']->code,
            'primary_branch_code' => $fixture['branch']->code,
            'primary_loc_code' => $fixture['location']->code,
            'primary_dept_code' => $fixture['department']->code,
            'primary_div_code' => $fixture['division']->code,
            'is_active' => '1',
            'addon_branch_codes' => [$addonBranch->code],
            'added_permissions' => ['TEST_ONBOARD_ADD'],
            'removed_permissions' => ['TEST_ONBOARD_BASE'],
        ]);

        $user = User::where('username', 'addon_onboard_user')->first()->fresh();

        $this->assertContains($addonBranch->code, $user->getScopeCodes('branch'));
        $this->assertTrue($user->can('TEST_ONBOARD_ADD'));
        $this->assertFalse($user->can('TEST_ONBOARD_BASE'));
    }

    public function test_org_change_on_update_requires_a_reason_and_effective_date(): void
    {
        $fixture = $this->orgFixture();
        $newBranch = Branch::create(['code' => 'B'.$this->shortCode(), 'name' => 'New Branch']);
        $newLocation = Location::create(['branch_code' => $newBranch->code, 'code' => 'L'.$this->shortCode(), 'name' => 'New Location']);

        [$user, $employee] = $this->createEmployeeUser($fixture);
        $admin = $this->adminUser(['ORG_USER_EDIT']);

        $response = $this->actingAsBackpackUser($admin)->put(backpack_url("org/user/{$user->id}"), [
            'username' => $user->username,
            'user_type_code' => 'emp',
            'designation_code' => $employee->designation_code,
            'primary_branch_code' => $newBranch->code,
            'primary_loc_code' => $newLocation->code,
            'primary_dept_code' => $employee->primary_dept_code,
            'primary_div_code' => $employee->primary_div_code,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('change_reason');
        $this->assertSame($fixture['branch']->code, $employee->fresh()->primary_branch_code);
    }

    public function test_org_change_with_reason_updates_employee_and_records_history(): void
    {
        $fixture = $this->orgFixture();
        $newBranch = Branch::create(['code' => 'B'.$this->shortCode(), 'name' => 'New Branch 2']);
        $newLocation = Location::create(['branch_code' => $newBranch->code, 'code' => 'L'.$this->shortCode(), 'name' => 'New Location 2']);

        [$user, $employee] = $this->createEmployeeUser($fixture);
        $admin = $this->adminUser(['ORG_USER_EDIT']);
        $journeyService = app(EmployeeJourneyService::class);
        $before = $journeyService->journey($employee->code)->count();

        $response = $this->actingAsBackpackUser($admin)->put(backpack_url("org/user/{$user->id}"), [
            'username' => $user->username,
            'user_type_code' => 'emp',
            'designation_code' => $employee->designation_code,
            'primary_branch_code' => $newBranch->code,
            'primary_loc_code' => $newLocation->code,
            'primary_dept_code' => $employee->primary_dept_code,
            'primary_div_code' => $employee->primary_div_code,
            'is_active' => '1',
            'change_reason' => 'transfer',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(backpack_url("org/user/{$user->id}/show"));
        $employee->refresh();
        $this->assertSame($newBranch->code, $employee->primary_branch_code);

        $journey = $journeyService->journey($employee->code);
        $this->assertSame($before + 1, $journey->count());
        $this->assertSame('transfer', $journey->last()->change_reason);
    }

    public function test_a_user_without_the_permission_cannot_create_a_user(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'No Perm Onboard Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9800011199', 'is_primary' => true]],
        ]);
        $user = User::create(['username' => 'noperm_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);

        $response = $this->actingAsBackpackUser($user)->post(backpack_url('org/user'), [
            'person_code' => $person->person_code,
            'user_type_code' => 'cust',
            'username' => 'should_not_be_created',
            'password' => 'password123',
            'is_active' => '1',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'should_not_be_created']);
    }

    /** @return array{0: User, 1: Employee} */
    private function createEmployeeUser(array $fixture): array
    {
        $person = PersonService::upsert([
            'display_name' => 'Existing Employee Person '.uniqid(),
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '98000'.random_int(10000, 99999), 'is_primary' => true]],
        ]);

        $employee = Employee::create([
            'code' => 'TEMP'.uniqid(),
            'person_code' => $person->person_code,
            'designation_code' => $fixture['designation']->code,
            'primary_branch_code' => $fixture['branch']->code,
            'primary_loc_code' => $fixture['location']->code,
            'primary_dept_code' => $fixture['department']->code,
            'primary_div_code' => $fixture['division']->code,
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'joining_date' => now(),
        ]);

        $user = User::create([
            'username' => 'existing_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'person_code' => $person->person_code,
            'employee_code' => $employee->code,
            'is_active' => 1,
        ]);

        app(EmployeeJourneyService::class)->recordChange(
            $employee,
            [
                'designation_code' => $employee->designation_code,
                'primary_branch_code' => $employee->primary_branch_code,
                'primary_loc_code' => $employee->primary_loc_code,
                'primary_dept_code' => $employee->primary_dept_code,
                'primary_div_code' => $employee->primary_div_code,
            ],
            'other',
            now(),
        );

        return [$user, $employee];
    }
}
