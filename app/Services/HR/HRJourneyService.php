<?php

namespace App\Services\HR;

use App\Models\Admin\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HRJourneyService
{
    /**
     * Onboard employee to a Designation (primary)
     */
    public function onboard(string $empCode, string $designationCode, Carbon|string $fromDate, string $remarks = ''): void
    {
        DB::transaction(function () use ($empCode, $designationCode, $fromDate, $remarks) {
            $employee = Employee::where('code', $empCode)->firstOrFail();

            // Update designation
            $employee->update([
                'designation_code' => $designationCode,
                'desig_code'       => $designationCode, // keep legacy in sync
            ]);

            // Create initial UserScope records if needed (optional)
            // You can call OrgScopeService here if you want to auto-create scopes on onboarding
        });
    }

    /**
     * Transfer employee to new Designation
     */
    public function transfer(string $empCode, string $newDesignationCode, Carbon|string $effectiveDate, string $remarks = ''): void
    {
        DB::transaction(function () use ($empCode, $newDesignationCode, $effectiveDate, $remarks) {
            $employee = Employee::where('code', $empCode)->firstOrFail();

            $employee->update([
                'designation_code' => $newDesignationCode,
                'desig_code'       => $newDesignationCode,
            ]);
        });
    }

    /**
     * Get current Designation of employee
     */
    public function getCurrentDesignation(string $empCode): ?string
    {
        return Employee::where('code', $empCode)->value('designation_code');
    }

    /**
     * Get full journey (history) of an employee
     */
    public function getJourney(string $empCode): Collection
    {
        // For now we return basic history. You can enhance this later with audit logs.
        return collect([
            'current_designation' => $this->getCurrentDesignation($empCode),
            'message' => 'Journey history will be expanded using audit logs in future.'
        ]);
    }
}