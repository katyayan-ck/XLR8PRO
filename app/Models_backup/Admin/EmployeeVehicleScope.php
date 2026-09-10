<?php
namespace App\Models\Admin;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeVehicleScope extends BaseModel
{
    protected $table = 'xlr8_admin_emp_vehicle_scope';

    const LEVELS = ['brand','segment','sub_segment','model','variant'];

    protected $fillable = [
        'emp_code','scope_level','scope_code',
        'assignment_type','from_date','to_date','is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'from_date'  => 'date',
        'to_date'    => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_code', 'code');
    }

    /**
     * Resolve the actual model for the scope_code at scope_level
     */
    public function resolveScopeEntity(): mixed
    {
        return match($this->scope_level) {
            'brand'       => \App\Models\Vehicle\Brand::where('code', $this->scope_code)->first(),
            'segment'     => \App\Models\Vehicle\Segment::where('code', $this->scope_code)->first(),
            'sub_segment' => \App\Models\Vehicle\SubSegment::where('code', $this->scope_code)->first(),
            'model'       => \App\Models\Vehicle\VehicleModel::where('oem_code', $this->scope_code)->first(),
            'variant'     => \App\Models\Vehicle\Variant::where('oem_code', $this->scope_code)->first(),
            default       => null,
        };
    }

    public function scopeCurrent($q)            { return $q->where('is_current', true); }
    public function scopeExplicit($q)           { return $q->where('assignment_type','explicit'); }
    public function scopeByLevel($q, string $l) { return $q->where('scope_level', $l); }
}