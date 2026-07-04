<?php

namespace App\Imports\Concerns;

use App\Models\Admin\Employee;
use App\Imports\ValueObjects\EmployeeRowDTO;

trait EmployeeBuilder
{
    use MasterDataSeeder;

    public function buildEmployee(EmployeeRowDTO $dto, int $personId): Employee
    {
        $desigCode = $this->designationCode($dto->designation);
        $deptCode  = $this->deptCode($dto->department);
        $primaryBranch = $dto->branches[0] ?? 'ANY';
        $branchCode = $primaryBranch === 'ANY'
            ? ($this->allBranchCodes()[0] ?? 'BKN')
            : $this->branchCode($primaryBranch);

        $data = [
            'person_id'           => $personId,
            'desig_code'          => $desigCode,
            'primary_branch_code' => $branchCode,
            'primary_dept_code'   => $deptCode,
            'employment_type'     => $dto->employmentType,
            'employment_status'   => strtolower($dto->status) === 'active' ? 'active' : 'inactive',
            'joining_date'        => $dto->joiningDate?->toDateString(),
            'confirmation_date'   => $dto->confirmationDate?->toDateString(),
            'separation_date'     => $dto->separationDate?->toDateString(),
            'oem_id'              => $dto->oemId,
            'blood_group'         => $dto->bloodGroup,
            'nationality'         => $dto->nationality,
            'father_name'         => $dto->fatherName,
            'pf_eligible'         => $dto->pfEligible,
            'pf_reg_type'         => $dto->pfRegType,
            'pf_number'           => $dto->pfNumber,
            'uan_number'          => $dto->uanNumber,
            'pf_joining_date'     => $dto->pfJoiningDate?->toDateString(),
            'eps_membership'      => $dto->epsEligible,
            'abry_eligible'       => $dto->abryEligible,
            'esi_eligible'        => $dto->esiEligible,
            'esi_number'          => $dto->esiNumber,
            'pt_establishment_id' => $dto->ptEstablishmentId,
            'lwf_eligible'        => $dto->lwfEligible,
            'biometric_id'        => $dto->biometricId,
            'shift_type'          => $dto->shiftType,
            'shift_name'          => $dto->shiftName,
            'late_arrival_window' => $dto->lateArrivalWindow,
            'early_going_window'  => $dto->earlyGoingWindow,
            'leave_rule'          => $dto->leaveRule,
            'week_off'            => $dto->weekOff,
            'wo_work_compensation'=> $dto->woWorkCompensation,
            'comp_off_applicable' => $dto->compOffApplicable,
            'salary_payment_mode' => $dto->salaryPaymentMode,
            'salary_structure_type' => $dto->salaryStructureType,
            'official_mobile'     => $dto->officialMobile,
            'official_email'      => $dto->officialEmail,
            'created_by'          => 1,
            'updated_by'          => 1,
        ];

        return Employee::withTrashed()
            ->where('code', $dto->empCode)
            ->first()
            ? tap(
                Employee::where('code', $dto->empCode)->first(),
                fn($e) => $e->update($data)
              )
            : Employee::create(array_merge($data, ['code' => $dto->empCode]));
    }
}
