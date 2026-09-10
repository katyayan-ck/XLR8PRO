<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLocationAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'xlr8_admin_emp_location_pivot';

    protected $fillable = [
        'employee_code',        // FK → xlr8_admin_employee.code   (FIX: was employee_id int)
        'location_code',        // FK → xlr8_admin_location.code   (FIX: was location_id int)
        'branch_code',          // FK → xlr8_admin_branch.code     (FIX: was branch_id int)
        'is_primary_work',      // is this the primary physical work location?
        'assignment_type',      // explicit | inherited | excluded
        'is_current',
        'from_date',
        'to_date',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'from_date'       => 'date',
        'to_date'         => 'date',
        'is_primary_work' => 'boolean',
        'is_current'      => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    // FIX: all three relations now use string code FKs, not integer IDs

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCurrent($q)        { return $q->where('is_current', true)->whereNull('deleted_at'); }
    public function scopePrimaryWork($q)    { return $q->where('is_primary_work', true); }
    public function scopeExplicit($q)       { return $q->where('assignment_type', 'explicit'); }
    public function scopeNotExcluded($q)    { return $q->where('assignment_type', '!=', 'excluded'); }
}
