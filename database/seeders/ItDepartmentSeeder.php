<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\User;
use App\Services\IAM\UserScopeService;
use App\Services\Org\DepartmentService;
use App\Services\Org\DivisionService;
use App\Services\Org\EmployeeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the IT department with its default (same-coded) division (DEC-046).
 *
 * Division codes are unique and an `IT` division already existed under Admin, so that division
 * is moved to the new department instead of creating a second one. Employees whose users
 * workbook row says "Primary Department = IT" (BMPL-0365, BMPL-0630) get IT as primary
 * department/division and matching scopes. Idempotent: safe to run on every environment.
 * Every write goes through the entity services (DEC-050).
 *
 * Run: php artisan db:seed --class=ItDepartmentSeeder
 */
class ItDepartmentSeeder extends Seeder
{
    private const CODE = 'IT';

    /** Employees whose master-data row places them in IT (storage/userdata.xlsx). */
    private const EMPLOYEES = ['BMPL-0365', 'BMPL-0630'];

    public function run(): void
    {
        DB::transaction(function () {
            $departments = app(DepartmentService::class);
            $department = Department::withTrashed()->where('code', self::CODE)->first();
            if ($department?->trashed()) {
                $department->restore();
            }
            // DepartmentService creates the default IT division unless one already exists.
            $department
                ? $departments->update($department, ['name' => 'IT', 'is_active' => true])
                : $departments->create(['code' => self::CODE, 'name' => 'IT', 'is_active' => true]);

            $division = Division::withTrashed()->where('code', self::CODE)->first();
            if ($division && $division->dept_code !== self::CODE) {
                $from = $division->dept_code;
                if ($division->trashed()) {
                    $division->restore();
                }
                app(DivisionService::class)->update($division, ['dept_code' => self::CODE, 'name' => 'IT', 'is_active' => true]);
                $this->command?->info("Division IT: dept_code {$from} → IT");
            }

            foreach (self::EMPLOYEES as $empCode) {
                $employee = Employee::where('code', $empCode)->first();
                $placed = $employee && in_array($employee->primary_dept_code, [null, self::CODE], true);
                if ($placed) {
                    app(EmployeeService::class)->update($employee, ['primary_dept_code' => self::CODE, 'primary_div_code' => self::CODE]);
                }

                $userId = User::where('employee_code', $empCode)->value('id');
                if ($userId) {
                    foreach (['department', 'division'] as $type) {
                        app(UserScopeService::class)->grant((int) $userId, $type, self::CODE);
                    }
                }

                $this->command?->info("{$empCode}: primary IT ".($placed ? 'set' : 'unchanged (missing or another department)').($userId ? ', scopes ensured' : ', no user'));
            }
        });
    }
}
