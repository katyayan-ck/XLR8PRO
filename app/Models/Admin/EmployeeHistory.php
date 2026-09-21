<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/**
 * Does NOT extend BaseModel — BaseModel forces the SoftDeletes trait, but
 * xlr8_admin_employee_history has no `deleted_at` column (confirmed via
 * Schema::getColumnListing), which made every query on this model fatal with
 * "Unknown column 'deleted_at'" the instant it was actually used (found while
 * building the Employment History feature — this model had 0 rows and had
 * evidently never been queried before).
 */
class EmployeeHistory extends Model
{
    protected $table = 'xlr8_admin_employee_history';

    protected $fillable = [
        'emp_code',
        'person_code',
        'designation_code',
        'primary_branch_code',
        'primary_loc_code',
        'primary_dept_code',
        'primary_div_code',
        'vertical_code',
        'segment_code',
        'sub_segment_code',
        'reporting_manager_code',
        'scopes',
        'effective_from',
        'effective_to',
        'change_reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'scopes' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'emp_code', 'code');
    }

    public function scopeActiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }
}
