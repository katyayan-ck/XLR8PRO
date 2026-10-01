<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Vertical;
use App\Services\Org\BranchService;
use App\Services\Org\DepartmentService;
use App\Services\Org\EmployeeService;
use App\Services\Person\PersonRecordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-089 (owner rule 30-09): every employee has a primary branch, location, department, division and a vertical; the
 * location belongs to the branch and the division to the department; a blank location / division takes the parent's
 * same-code child. Legacy rows that never had them stay editable (DEC-054: only changed values are checked).
 */
class EmployeePrimariesRuleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, string> */
    private array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $tag = strtoupper(substr(uniqid(), -4));
        app(BranchService::class)->create(['code' => "RB{$tag}", 'name' => 'Rule Branch', 'is_active' => true]);
        app(BranchService::class)->create(['code' => "RO{$tag}", 'name' => 'Other Branch', 'is_active' => true]);
        app(DepartmentService::class)->create(['code' => "RD{$tag}", 'name' => 'Rule Dept', 'is_active' => true]);
        $vertical = Vertical::query()->value('code') ?? $this->markTestSkipped('no vertical');
        $this->org = ['branch' => "RB{$tag}", 'other' => "RO{$tag}", 'department' => "RD{$tag}", 'vertical' => $vertical];
    }

    private function person(): string
    {
        return app(PersonRecordService::class)->create(['display_name' => 'Rule Test'])->person_code;
    }

    public function test_a_new_employee_needs_a_vertical(): void
    {
        try {
            app(EmployeeService::class)->create(['person_code' => $this->person(), 'primary_branch_code' => $this->org['branch'], 'primary_dept_code' => $this->org['department']]);
            $this->fail('expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vertical_code', $e->errors());
        }
    }

    public function test_blank_location_and_division_take_the_same_code_children(): void
    {
        $employee = app(EmployeeService::class)->create(['person_code' => $this->person(), 'primary_branch_code' => $this->org['branch'],
            'primary_dept_code' => $this->org['department'], 'vertical_code' => $this->org['vertical']]);

        $this->assertSame([$this->org['branch'], $this->org['department']], [$employee->primary_loc_code, $employee->primary_div_code]);
    }

    public function test_a_location_of_another_branch_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(EmployeeService::class)->create(['person_code' => $this->person(), 'primary_branch_code' => $this->org['branch'],
            'primary_loc_code' => $this->org['other'], 'primary_dept_code' => $this->org['department'], 'vertical_code' => $this->org['vertical']]);
    }

    public function test_a_legacy_employee_without_primaries_stays_editable_but_a_set_primary_cannot_be_cleared(): void
    {
        $employee = app(EmployeeService::class)->create(['person_code' => $this->person(), 'primary_branch_code' => $this->org['branch'],
            'primary_dept_code' => $this->org['department'], 'vertical_code' => $this->org['vertical']]);
        Employee::query()->toBase()->where('id', $employee->id)->update(['vertical_code' => null]); // a legacy row, past the entity rules

        $updated = app(EmployeeService::class)->update(Employee::find($employee->id), ['vertical_code' => null, 'mile_id' => 'M-1']);
        $this->assertSame('M-1', $updated->mile_id, 'an unchanged missing vertical does not block other edits');

        $this->expectException(ValidationException::class);
        app(EmployeeService::class)->update($updated, ['primary_branch_code' => null]);
    }
}
