<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the IT department with its default (same-coded) division (DEC-046).
 *
 * Division codes are unique and an `IT` division already existed under Admin, so that division
 * is moved to the new department instead of creating a second one. Employees whose users
 * workbook row says "Primary Department = IT" (BMPL-0365, BMPL-0630) get IT as primary
 * department/division and matching scopes. Idempotent: safe to run on every environment.
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
            $department = Department::withTrashed()->firstOrNew(['code' => self::CODE]);
            $department->fill(['name' => 'IT', 'is_active' => true]); // title_case keeps IT (acronym list)
            if ($department->trashed()) {
                $department->restore();
            }
            $department->save();

            $division = Division::withTrashed()->where('code', self::CODE)->first();
            if ($division) {
                $from = $division->dept_code;
                $division->forceFill(['dept_code' => self::CODE, 'name' => 'IT', 'is_active' => true, 'deleted_at' => null])->save();
                $this->command?->info("Division IT: dept_code {$from} → IT");
            } else {
                Division::create(['dept_code' => self::CODE, 'code' => self::CODE, 'name' => 'IT', 'is_active' => true]);
                $this->command?->info('Division IT created');
            }

            foreach (self::EMPLOYEES as $empCode) {
                $updated = DB::table('xlr8_admin_employee')->where('code', $empCode)
                    ->where(fn ($q) => $q->whereNull('primary_dept_code')->orWhere('primary_dept_code', self::CODE))
                    ->update(['primary_dept_code' => self::CODE, 'primary_div_code' => self::CODE, 'updated_at' => now()]);

                $userId = DB::table('users')->where('employee_code', $empCode)->value('id');
                if ($userId) {
                    foreach (['department', 'division'] as $type) {
                        DB::table('xlr8_admin_user_scopes')->updateOrInsert(
                            ['user_id' => $userId, 'scope_type' => $type, 'scope_code' => self::CODE],
                            ['is_active' => 1, 'to_date' => null, 'deleted_at' => null, 'from_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                }

                $this->command?->info("{$empCode}: primary IT ".($updated ? 'set' : 'unchanged (has another department)').($userId ? ', scopes ensured' : ', no user'));
            }
        });
    }
}
