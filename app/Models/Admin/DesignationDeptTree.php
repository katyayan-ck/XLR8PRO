<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Table: xlr8_admin_desig_dept_tree
 * All relations are Eloquent-only (no DB FKs).
 * desig_code → xlr8_admin_designation.code  (NOT desig_code — that was dropped from designation)
 * dept_code  → xlr8_admin_department.code
 * div_code   → xlr8_admin_division.code
 */
class DesigDeptTree extends Model
{
    use SoftDeletes;

    protected $table = 'xlr8_admin_desig_dept_tree';

    protected $fillable = [
        'tree_code', 'desig_code', 'dept_code', 'div_code',
        'reports_to_code', 'display_name', 'level', 'is_active',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level'     => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────
    /**
     * FIXED: second arg must be 'code' (owner key on Designation),
     * because desig_code column was DROPPED from xlr8_admin_designation.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'desig_code', 'code');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_code', 'code');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'div_code', 'code');
    }

    /** Self-ref via tree_code */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(static::class, 'reports_to_code', 'tree_code');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(static::class, 'reports_to_code', 'tree_code');
    }

    /** Posts anchored to this tree node */
    public function posts(): HasMany
    {
        return $this->hasMany(\App\Models\Iam\Post::class, 'tree_code', 'tree_code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($q)               { return $q->where('is_active', true); }
    public function scopeByDept($q, string $d)    { return $q->where('dept_code', $d); }
    public function scopeByDesig($q, string $d)   { return $q->where('desig_code', $d); }
    public function scopeRoots($q)                { return $q->whereNull('reports_to_code'); }
}