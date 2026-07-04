<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Facades\DB;
use App\Models\Admin\Employee;
use App\Imports\ValueObjects\EmployeeRowDTO;

trait PivotWriter
{
    use MasterDataSeeder;

    public function writePivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $this->writeBranchPivots($emp, $dto);
        $this->writeLocationPivots($emp, $dto);
        $this->writeDeptPivot($emp, $dto);
        $this->writeVerticalPivots($emp, $dto);
        $this->writeSegmentPivots($emp, $dto);
        $this->writeSubSegmentPivots($emp, $dto);
        $this->writePostPivot($emp, $dto);
    }

    // ── Branch ────────────────────────────────────────────────────

    private function writeBranchPivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $branches = $this->expandAny($dto->branches, fn() => $this->allBranchCodes());

        foreach ($branches as $idx => $branchName) {
            $code = $this->branchCode($branchName);
            DB::table('xlr8_admin_emp_branch_pivot')->updateOrInsert(
                ['employee_code' => $emp->code, 'branch_code' => $code],
                [
                    'assignment_type' => $idx === 0 ? 'primary' : 'additional',
                    'from_date'       => $emp->joining_date ?? now()->toDateString(),
                    'is_current'      => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
    }

    // ── Location ──────────────────────────────────────────────────

    private function writeLocationPivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $locations = $this->expandAny($dto->workLocations, fn() => []);

        $primaryBranch     = $dto->branches[0] ?? 'ANY';
        $primaryBranchCode = $this->branchCode($primaryBranch);

        foreach ($locations as $idx => $locName) {
            if (strtoupper($locName) === 'ANY') continue;
            $loc = $this->upsertLocation($locName, $primaryBranchCode);
            DB::table('xlr8_admin_emp_location_pivot')->updateOrInsert(
                ['employee_code' => $emp->code, 'location_code' => $loc->code],
                [
                    'branch_code'     => $primaryBranchCode,
                    'is_primary_work' => $idx === 0 ? 1 : 0,
                    'assignment_type' => 'explicit',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
    }

    // ── Department ────────────────────────────────────────────────

    private function writeDeptPivot(Employee $emp, EmployeeRowDTO $dto): void
    {
        if (empty($dto->department)) return;

        $deptCode = $this->deptCode($dto->department);
        $divCode  = $dto->division ? $this->divisionCode($deptCode, $dto->division) : null;

        DB::table('xlr8_admin_emp_department_pivot')->updateOrInsert(
            ['employee_code' => $emp->code, 'dept_code' => $deptCode],
            [
                'division_code'   => $divCode,
                'assignment_type' => 'primary',
                'is_current'      => 1,
                'from_date'       => $emp->joining_date ?? now()->toDateString(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );
    }

    // ── Vertical ──────────────────────────────────────────────────

    private function writeVerticalPivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $verticals = $this->expandAny($dto->verticals, fn() => $this->allVerticalCodes());

        foreach ($verticals as $vName) {
            if (strtoupper($vName) === 'ANY') continue;
            $code = $this->verticalCode($vName);
            DB::table('xlr8_admin_emp_vertical_pivot')->updateOrInsert(
                ['employee_code' => $emp->code, 'vertical_code' => $code],
                ['is_current' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    // ── Segment ───────────────────────────────────────────────────

    private function writeSegmentPivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $segments = $this->expandAny($dto->segments, fn() => $this->allSegmentCodes());

        foreach ($segments as $sName) {
            if (strtoupper($sName) === 'ANY') continue;
            $code = $this->segmentCode($sName);
            DB::table('xlr8_admin_emp_segment_pivot')->updateOrInsert(
                ['employee_code' => $emp->code, 'segment_code' => $code],
                ['is_current' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    // ── Sub-Segment ───────────────────────────────────────────────

    private function writeSubSegmentPivots(Employee $emp, EmployeeRowDTO $dto): void
    {
        $subs = $this->expandAny($dto->subSegments, fn() => $this->allSubSegmentCodes());

        foreach ($subs as $ssName) {
            if (strtoupper($ssName) === 'ANY') continue;
            $code = $this->subSegmentCode($ssName);
            DB::table('xlr8_admin_emp_sub_segment_pivot')->updateOrInsert(
                ['employee_code' => $emp->code, 'sub_segment_code' => $code],
                ['is_current' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    // ── Post ──────────────────────────────────────────────────────

    private function writePostPivot(Employee $emp, EmployeeRowDTO $dto): void
    {
        if (empty($dto->designation) || empty($dto->department)) return;

        $desigCode = $this->designationCode($dto->designation);
        $deptCode  = $this->deptCode($dto->department);
        $divCode   = $dto->division ? $this->divisionCode($deptCode, $dto->division) : null;

        $branches      = $this->expandAny($dto->branches, fn() => $this->allBranchCodes());
        $primaryBranch = $this->branchCode($branches[0] ?? 'BKN');

        $post = $this->upsertPost($desigCode, $primaryBranch, $deptCode, $divCode);

        DB::table('xlr8_iam_emp_post_pivot')->updateOrInsert(
            ['employee_code' => $emp->code, 'post_code' => $post->code],
            [
                'is_current' => 1,
                'from_date'  => $emp->joining_date ?? now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    // ── ANY sentinel expansion ────────────────────────────────────

    /**
     * If the array contains 'ANY' or 'ALL', call $allResolver to get
     * every available code of that type. Otherwise return as-is.
     */
    private function expandAny(array $values, callable $allResolver): array
    {
        $upper = array_map('strtoupper', $values);

        if (empty($values) || in_array('ANY', $upper, true) || in_array('ALL', $upper, true)) {
            return $allResolver();
        }

        return $values;
    }
}
