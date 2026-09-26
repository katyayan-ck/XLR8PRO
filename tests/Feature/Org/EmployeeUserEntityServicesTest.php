<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\UserScope;
use App\Models\User;
use App\Services\HR\HRJourneyService;
use App\Services\IAM\UserScopeService;
use App\Services\IAM\UserService;
use App\Services\Org\EmployeeService;
use App\Services\Person\PersonRecordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-054: employees, login accounts and data scopes are written only through their entity
 * services — the User screens, the Users_Import / User_Scopes sheets and HR changes call these.
 */
class EmployeeUserEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    private function personCode(): string
    {
        return app(PersonRecordService::class)->create(['display_name' => 'Entity Test Person'])->person_code;
    }

    private function aBranchCode(): string
    {
        return (string) DB::table('xlr8_admin_branch')->whereNull('deleted_at')->value('code') ?: $this->markTestSkipped('no branch');
    }

    public function test_a_new_employee_gets_the_next_code_and_the_legacy_designation_mirror(): void
    {
        $designation = DB::table('xlr8_admin_designation')->whereNull('deleted_at')->value('code') ?? $this->markTestSkipped('no designation');
        $employees = app(EmployeeService::class);
        $expected = $employees->nextCode();

        $employee = $employees->create(['person_code' => $this->personCode(), 'designation_code' => strtolower($designation), 'employment_type' => 'Contract']);

        $this->assertSame($expected, $employee->code);
        $this->assertSame([$designation, $designation, 'contract', 'active'], [$employee->designation_code, $employee->desig_code, $employee->employment_type, $employee->employment_status]);
    }

    public function test_an_unknown_org_code_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(EmployeeService::class)->create(['person_code' => $this->personCode(), 'primary_branch_code' => 'NOPE9']);
    }

    public function test_a_bad_stored_value_does_not_block_editing_another_field(): void
    {
        $employee = app(EmployeeService::class)->create(['person_code' => $this->personCode()]);
        DB::table('xlr8_admin_employee')->where('id', $employee->id)->update(['designation_code' => 'GONE-DESIG']);

        $updated = app(EmployeeService::class)->update($employee->fresh(), ['designation_code' => 'GONE-DESIG', 'mile_id' => 'M-77']);

        $this->assertSame('M-77', $updated->mile_id);
        $this->assertSame('GONE-DESIG', $updated->designation_code);
    }

    public function test_usernames_are_lower_case_and_passwords_are_hashed_as_typed(): void
    {
        $users = app(UserService::class);
        $user = $users->create(['username' => '  Test.User9 ', 'password' => ' secret pass ']);

        $this->assertSame('test.user9', $user->username);
        $this->assertTrue(Hash::check(' secret pass ', $user->password));

        $hash = $user->password;
        $users->update($user, ['password' => '', 'user_type' => 'dsa']);
        $this->assertSame([$hash, 'DSA'], [$user->fresh()->password, $user->fresh()->user_type]);
    }

    public function test_a_username_held_by_a_deleted_account_is_rejected(): void
    {
        $users = app(UserService::class);
        $users->create(['username' => 'gone.user', 'password' => 'password1'])->delete();

        $this->expectException(ValidationException::class);
        $users->create(['username' => 'GONE.USER', 'password' => 'password1']);
    }

    public function test_scopes_are_granted_restored_and_revoked_through_one_path(): void
    {
        $branch = $this->aBranchCode();
        $user = User::create(['username' => 'scope_'.uniqid(), 'password' => bcrypt('x'), 'user_type' => 'Emp', 'is_active' => 1]);
        $scopes = app(UserScopeService::class);

        $this->assertSame('inserted', $scopes->grant($user->id, 'branch', strtolower($branch)));
        UserScope::where('user_id', $user->id)->first()->delete();
        $this->assertSame('activated', $scopes->grant($user->id, 'branch', $branch));

        $counts = $scopes->sync($user->id, 'branch', []);
        $row = UserScope::where('user_id', $user->id)->first();
        $this->assertSame(1, $counts['deactivated']);
        $this->assertFalse($row->is_active);
        $this->assertNotNull($row->to_date);
    }

    public function test_a_scope_code_missing_from_its_master_is_rejected(): void
    {
        $user = User::create(['username' => 'scope_'.uniqid(), 'password' => bcrypt('x'), 'user_type' => 'Emp', 'is_active' => 1]);

        $this->expectException(ValidationException::class);
        app(UserScopeService::class)->grant($user->id, 'branch', 'NO-SUCH-BRANCH');
    }

    public function test_hr_designation_change_goes_through_the_employee_service(): void
    {
        $designation = DB::table('xlr8_admin_designation')->whereNull('deleted_at')->value('code') ?? $this->markTestSkipped('no designation');
        $employee = app(EmployeeService::class)->create(['person_code' => $this->personCode()]);

        app(HRJourneyService::class)->transfer($employee->code, $designation, now());

        $this->assertSame($designation, Employee::find($employee->id)->desig_code);
    }
}
