<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDepartmentAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'xlr8_admin_emp_dept_pivot';

    protected $fillable = [
        'employee_code',    // FK → xlr8_admin_employee.code  (FIX: was employee_id int)
        'dept_code',        // FK → xlr8_admin_department.code (FIX: was department_id int)
        'div_code',         // FK → xlr8_admin_division.code  (nullable — new)
        'is_primary',
        'is_current',
        'from_date',
        'to_date',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'from_date'  => 'date',
        'to_date'    => 'date',
        'is_primary' => 'boolean',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    // FIX: employee via employee_code, department via dept_code (string codes)
    // NEW: division relation added

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_code', 'code');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'div_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCurrent($q)  { return $q->where('is_current', true)->whereNull('deleted_at'); }
    public function scopePrimary($q)  { return $q->where('is_primary', true); }
}
