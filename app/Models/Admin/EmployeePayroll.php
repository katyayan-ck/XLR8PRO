<?php
namespace App\Models\Admin;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayroll extends BaseModel
{
    protected $table = 'xlr8_admin_employee_payroll';

    protected $fillable = [
        'emp_code',
        'pf_eligible','pf_reg_type','pf_number','uan_number',
        'pf_joining_date','eps_membership','abry_eligible',
        'esi_eligible','esi_number',
        'pt_establishment_id','lwf_eligible',
        'salary_payment_mode','salary_structure_type','salary_bank_account',
        'biometric_id','shift_type','shift_name',
        'late_arrival_window','early_going_window',
        'leave_rule','week_off','wo_work_compensation','comp_off_applicable',
    ];

    protected $casts = [
        'pf_eligible'          => 'boolean',
        'eps_membership'       => 'boolean',
        'abry_eligible'        => 'boolean',
        'esi_eligible'         => 'boolean',
        'lwf_eligible'         => 'boolean',
        'wo_work_compensation' => 'boolean',
        'comp_off_applicable'  => 'boolean',
        'pf_joining_date'      => 'date',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_code', 'code');
    }
}