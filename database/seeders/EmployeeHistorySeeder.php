<?php

namespace Database\Seeders;

use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use Illuminate\Database\Seeder;

/**
 * xlr8_admin_employee_history (see its own migration, batch 22, pre-existing —
 * not created by this session) has never had anything written to it. This
 * seeds one initial "joining" snapshot per employee from their CURRENT org
 * info, so the Employment History card on the user show page has real
 * content instead of always being empty. Every future org-info change
 * (transfer, promotion, scope change, etc.) should close the current open
 * record (set effective_to) and insert a new one — that write-side workflow
 * is not built yet; this seeder only establishes the starting point.
 */
class EmployeeHistorySeeder extends Seeder
{
    public function run(): void
    {
        $seeded = 0;
        $skipped = 0;

        Employee::query()->chunk(50, function ($employees) use (&$seeded, &$skipped) {
            foreach ($employees as $employee) {
                if (EmployeeHistory::where('emp_code', $employee->code)->exists()) {
                    $skipped++;

                    continue;
                }

                EmployeeHistory::create([
                    'emp_code' => $employee->code,
                    'person_code' => $employee->person_code,
                    'designation_code' => $employee->designation_code,
                    'primary_branch_code' => $employee->primary_branch_code,
                    'primary_loc_code' => $employee->primary_loc_code,
                    'primary_dept_code' => $employee->primary_dept_code,
                    'primary_div_code' => $employee->primary_div_code,
                    'vertical_code' => $employee->vertical_code,
                    'segment_code' => $employee->segment_code,
                    'sub_segment_code' => $employee->sub_segment_code,
                    'reporting_manager_code' => $employee->reporting_manager_code,
                    'effective_from' => $employee->joining_date ?? $employee->created_at?->toDateString() ?? now()->toDateString(),
                    'effective_to' => null,
                    'change_reason' => 'joining',
                    'notes' => 'Initial record — seeded from current data, no prior history existed.',
                ]);
                $seeded++;
            }
        });

        $this->command?->info("EmployeeHistorySeeder: {$seeded} initial records seeded, {$skipped} already had history.");
    }
}
