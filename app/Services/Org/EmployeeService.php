<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Employee;
use App\Rules\EmployeeCode;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Employees (xlr8_admin_employee) — their only write path (DEC-050/054). Used by the User
 * onboarding screen, the user importer and HR designation changes.
 *
 * - `code` is BMPL-####, generated (next number) when not given; immutable.
 * - `person_code` is fixed at create; org placement columns are codes that must exist (live rows).
 * - `desig_code` (legacy) always mirrors `designation_code`.
 *
 * @extends EntityService<Employee>
 */
final class EmployeeService extends EntityService
{
    public const EMPLOYMENT_TYPES = ['permanent', 'probation', 'apprentice', 'contract', 'temporary'];

    public const EMPLOYMENT_STATUSES = ['active', 'inactive', 'separated', 'terminated', 'absconded'];

    protected function model(): string
    {
        return Employee::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 20)->label('Emp Code')->format('BMPL-#### (generated when blank)')
                ->rules(new EmployeeCode)->unique(includeTrashed: true)->immutable(),
            Field::reference('person_code', 'xlr8_admin_person', 20, 'person_code')->label(__('org.fields.person_code'))->required()->immutable(),
            Field::reference('designation_code', 'xlr8_admin_designation', 20)->label(__('org.fields.designation_code')),
            Field::code('desig_code', 20)->format('Legacy mirror of designation_code'),
            Field::reference('primary_branch_code', 'xlr8_admin_branch', 10)->label(__('org.fields.primary_branch_code')),
            Field::reference('primary_loc_code', 'xlr8_admin_location', 10)->label(__('org.fields.primary_loc_code')),
            Field::reference('primary_dept_code', 'xlr8_admin_department', 10)->label(__('org.fields.primary_dept_code')),
            Field::reference('primary_div_code', 'xlr8_admin_division', 10)->label(__('org.fields.primary_div_code')),
            Field::reference('vertical_code', 'xlr8_admin_vertical', 10)->label(__('org.fields.vertical_code')),
            Field::reference('segment_code', 'xlr8_vehicle_segment', 10)->label(__('org.fields.primary_segment_code')),
            Field::reference('sub_segment_code', 'xlr8_vehicle_subsegment', 10)->label(__('org.fields.primary_sub_segment_code')),
            Field::code('reporting_manager_code', 20)->label('Reporting Manager'),
            Field::code('reporting_emp_code', 20)->label('Reporting Employee'),
            Field::text('oem_id', 50)->label('OEM ID'),
            Field::text('mile_id', 30)->label('OEM Mile ID'),
            Field::choice('employment_type', self::EMPLOYMENT_TYPES)->label(__('org.fields.employment_type'))->required()->default('permanent'),
            Field::choice('employment_status', self::EMPLOYMENT_STATUSES)->label('Employee Status')->required()->default('active'),
            Field::date('joining_date')->label(__('org.fields.date_of_joining')),
            Field::date('confirmation_date'),
            Field::date('separation_date')->rules('after_or_equal:joining_date'),
            Field::text('separation_reason', 150),
            Field::make('blood_group')->format('A+, A-, B+, B-, AB+, AB-, O+, O-')->transform('trim', 'uppercase')
                ->rules('in:A+,A-,B+,B-,AB+,AB-,O+,O-'),
            Field::name('nationality', 50),
            Field::name('father_name', 100)->label('Father Name'),
            Field::name('mother_name', 100),
            Field::make('passport_no')->format('Letters/digits, upper-case')->transform('trim', 'uppercase')->rules('alpha_num', 'max:20'),
            Field::integer('no_of_children')->rules('max:20'),
            Field::date('marriage_date'),
            Field::text('biometric_id', 20),
            Field::flag('pf_eligible', false),
            Field::choice('pf_reg_type', ['new', 'existing']),
            Field::make('pf_number')->transform('trim', 'uppercase')->rules('string', 'max:30'),
            Field::make('uan_number')->label('UAN')->format('12 digits')->transform('trim')->rules('digits:12')->unique(includeTrashed: true),
            Field::date('pf_joining_date'),
            Field::flag('eps_membership', false),
            Field::flag('abry_eligible', false),
            Field::flag('esi_eligible', false),
            Field::make('esi_number')->label('ESI Number')->format('Digits, max 20')->transform('trim')->rules('regex:/^\d{10,20}$/')->unique(includeTrashed: true),
            Field::text('pt_establishment_id', 30),
            Field::flag('lwf_eligible', false),
            Field::choice('salary_payment_mode', ['bank', 'cash', 'cheque']),
            Field::choice('salary_structure_type', ['statutory_limit', 'above_statutory_limit']),
            Field::choice('shift_type', ['flexible', 'fixed']),
            Field::text('shift_name', 50),
            Field::integer('late_arrival_window')->rules('max:1440'),
            Field::integer('early_going_window')->rules('max:1440'),
            Field::text('leave_rule', 50),
            Field::text('week_off', 30),
            Field::flag('wo_work_compensation', false),
            Field::flag('comp_off_applicable', false),
        ];
    }

    /** Next free BMPL-#### code. */
    public function nextCode(): string
    {
        $max = Employee::withTrashed()->where('code', 'like', 'BMPL-%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) as max_num")
            ->value('max_num') ?? 0;

        return 'BMPL-'.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function derive(array $data, array $input): array
    {
        if (array_key_exists('designation_code', $data)) {
            $data['desig_code'] = $data['designation_code'];
        }

        return $data;
    }

    protected function beforeCreate(array &$data): void
    {
        $data['code'] ??= $this->nextCode();
    }
}
