<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeVerticalAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'xlr8_admin_emp_vertical_pivot';

    protected $fillable = [
        'employee_code',    // FK → xlr8_admin_employee.code  (FIX: was employee_id int)
        'vertical_code',    // FK → xlr8_admin_vertical.code  (FIX: was vertical_id int)
        'segment_code',     // optional — scope within vertical (new)
        'sub_segment_code', // optional — scope within segment (new)
        'is_current',
        'from_date',
        'to_date',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'from_date'  => 'date',
        'to_date'    => 'date',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────
    // FIX: employee via employee_code (string), vertical via vertical_code (string)
    // NEW: segment and sub_segment relations added

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    public function vertical(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\Vertical::class, 'vertical_code', 'code');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\Segment::class, 'segment_code', 'code');
    }

    public function subSegment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\SubSegment::class, 'sub_segment_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCurrent($q) { return $q->where('is_current', true)->whereNull('deleted_at'); }
}
