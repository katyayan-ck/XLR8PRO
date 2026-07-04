<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends BaseModel
{
    use CrudTrait, HasFactory;

    protected $table = 'xlr8_admin_employee';

    protected $fillable = [
        'code',
        'person_code',
        'desig_code',           // Legacy - kept for backward compatibility
        'designation_code',     // New clean column (preferred going forward)
        'primary_branch_code',
        'primary_loc_code',
        'primary_dept_code',
        'primary_div_code',
        'vertical_code',
        'segment_code',
        'sub_segment_code',
        'mile_id',
        'father_name',
        'employment_type',
        'joining_date',
        'confirmation_date',
        'separation_date',
        'reporting_manager_code',
        'week_off',
        'shift_type',
        'shift_name',
        'biometric_id',
        'pf_eligible',
        'pf_number',
        'uan_number',
        'esi_eligible',
        'esi_number',
        'lwf_eligible',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'pf_eligible'       => 'boolean',
        'esi_eligible'      => 'boolean',
        'lwf_eligible'      => 'boolean',
        'joining_date'      => 'date',
        'confirmation_date' => 'date',
        'separation_date'   => 'date',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    /**
     * New clean relation to Designation (which now acts as Spatie Role)
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_code', 'code');
    }

    /**
     * Legacy relation (still works)
     */
    public function designationLegacy(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'desig_code', 'code');
    }

    // ── Pivot Relations (Organization Structure) ─────────────────

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'xlr8_admin_emp_branch_pivot', 'employee_code', 'branch_code')
            ->withPivot(['from_date', 'to_date'])
            ->wherePivotNull('to_date')
            ->whereNull('xlr8_admin_emp_branch_pivot.deleted_at');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'xlr8_admin_emp_location_pivot', 'employee_code', 'location_code')
            ->withPivot(['from_date', 'to_date'])
            ->wherePivotNull('to_date')
            ->whereNull('xlr8_admin_emp_location_pivot.deleted_at');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'xlr8_admin_emp_department_pivot', 'employee_code', 'department_code')
            ->withPivot(['from_date', 'to_date'])
            ->wherePivotNull('to_date')
            ->whereNull('xlr8_admin_emp_department_pivot.deleted_at');
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'xlr8_admin_emp_division_pivot', 'employee_code', 'div_code')
            ->withPivot(['from_date', 'to_date'])
            ->wherePivotNull('to_date')
            ->whereNull('xlr8_admin_emp_division_pivot.deleted_at');
    }

    // Add more pivots here if needed (vertical, segment, etc.)

    // ─────────────────────────────────────────────────────────────
    // ACCESSORS / HELPERS
    // ─────────────────────────────────────────────────────────────

   public function getDesignationCodeAttribute(): ?string
    {
        return $this->attributes['designation_code ?? $this->desig_code'] ?? null;
    }

    public function getDesignationNameAttribute(): ?string
    {
        return $this->attributes['designation?->name ?? $this->designationLegacy?->name'] ?? null;
    }

    public function getPrimaryBranchCodeAttribute(): ?string
    {
        return $this->attributes['primary_branch_code'] ?? null;
    }

    public function getPrimaryLocationCodeAttribute(): ?string
    {
        return $this->attributes['primary_loc_code'] ?? null;
    }

    public function getPrimaryDepartmentCodeAttribute(): ?string
    {
        return $this->attributes['primary_dept_code'] ?? null;
    }

    public function getPrimaryDivisionCodeAttribute(): ?string
    {
        return $this->attributes['primary_div_code'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    // public function scopeActive($query)
    // {
    //     return $query->where('is_active', true);
    // }

    // ─────────────────────────────────────────────────────────────
    // BOOT (Auto-sync desig_code ↔ designation_code during transition)
    // ─────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $employee) {
            // Keep legacy and new column in sync during transition period
            if (empty($employee->designation_code) && !empty($employee->desig_code)) {
                $employee->designation_code = strtoupper(trim($employee->desig_code));
            }

            if (empty($employee->desig_code) && !empty($employee->designation_code)) {
                $employee->desig_code = strtoupper(trim($employee->designation_code));
            }

            // Always store codes in UPPERCASE
            if ($employee->designation_code) {
                $employee->designation_code = strtoupper(trim($employee->designation_code));
            }
            if ($employee->desig_code) {
                $employee->desig_code = strtoupper(trim($employee->desig_code));
            }
        });
    }
}