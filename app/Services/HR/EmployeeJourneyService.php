<?php

namespace App\Services\HR;

use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeJourneyService
{
    /**
     * Log a change in employee state (designation, scopes, reporting manager, etc.)
     */
    public function logChange(string $empCode, array $stateData, string $changeReason = 'import', ?string $notes = null): EmployeeHistory
    {
        $data = [
            'emp_code'                => $empCode,
            'person_code'             => $stateData['person_code'] ?? null,
            'designation_code'        => $stateData['designation_code'] ?? null,
            'primary_branch_code'     => $stateData['primary_branch_code'] ?? null,
            'primary_loc_code'        => $stateData['primary_loc_code'] ?? null,
            'primary_dept_code'       => $stateData['primary_dept_code'] ?? null,
            'primary_div_code'        => $stateData['primary_div_code'] ?? null,
            'vertical_code'           => $stateData['vertical_code'] ?? null,
            'segment_code'            => $stateData['segment_code'] ?? null,
            'sub_segment_code'        => $stateData['sub_segment_code'] ?? null,
            'reporting_manager_code'  => $stateData['reporting_manager_code'] ?? null,
            'scopes'                  => $stateData['scopes'] ?? null,           // JSON of all scopes
            'effective_from'          => $stateData['effective_from'] ?? now()->toDateString(),
            'effective_to'            => null,
            'change_reason'           => $changeReason,
            'notes'                   => $notes,
            'created_by'              => auth()->id() ?? 1,
        ];

        return EmployeeHistory::create($data);
    }

    /**
     * Get employee's state on a specific date
     */
    public function getStateOnDate(string $empCode, Carbon|string $date): ?EmployeeHistory
    {
        $date = Carbon::parse($date)->toDateString();

        return EmployeeHistory::where('emp_code', $empCode)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();
    }

    /**
     * Who was working as a specific designation + location on a date
     */
    public function whoWas(string $designationCode, ?string $branchCode = null, ?string $locationCode = null, Carbon|string $date = null): Collection
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $query = EmployeeHistory::where('designation_code', $designationCode)
            ->activeOn($date);

        if ($branchCode) {
            $query->where('primary_branch_code', $branchCode);
        }
        if ($locationCode) {
            $query->where('primary_loc_code', $locationCode);
        }

        return $query->get();
    }

    /**
     * Full chronological history of an employee
     */
    public function getFullHistory(string $empCode): Collection
    {
        return EmployeeHistory::where('emp_code', $empCode)
            ->orderBy('effective_from')
            ->get();
    }
}