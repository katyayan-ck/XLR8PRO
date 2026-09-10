<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBranchAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'xlr8_admin_emp_branch_pivot';

    protected $fillable = [
        'employee_code',    // FK → xlr8_admin_employee.code  (FIX: was employee_id int)
        'branch_code',      // FK → xlr8_admin_branch.code    (FIX: was branch_id int)
        'assignment_type',  // primary | additional | inherited
        'is_primary',       // boolean shortcut for assignment_type='primary'
        'is_current',
        'from_date',
        'to_date',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'is_primary'  => 'boolean',
        'is_current'  => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    // FIX: both use string code FK, not integer id

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCurrent($q)  { return $q->where('is_current', true)->whereNull('deleted_at'); }
    public function scopePrimary($q)  { return $q->where('assignment_type', 'primary'); }
}
