<?php

namespace App\Models\Admin;

use App\Models\Traits\HasColumnTransformations;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\BaseModel;

/**
 * Table: xlr8_admin_department
 * POST-MIGRATION: dept_code dropped. Only `code` is authoritative.
 * cross-table refs: employee.primary_dept_code, division.dept_code → department.code
 */
class Department extends BaseModel
{
    use SoftDeletes, CrudTrait, HasColumnTransformations, InteractsWithMedia;

    protected $table = 'xlr8_admin_department';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected array $columnTransformations = [
        'code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name' => ['trim_spaces', 'title_case'],
    ];

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('department_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp'
            ])
            ->useDisk('public');
    }

    protected $casts = ['is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // public function branch(): BelongsTo
    // {
    //     return $this->belongsTo(
    //         Branch::class,
    //         'branch_code',   // department column
    //         'branch_code'    // branch table column
    //     );
    // }

    // ── Relations ─────────────────────────────────────────────────────────────
    /** Head employee: head_code → employee.code */
    // public function head(): BelongsTo
    // {
    //     return $this->belongsTo(Employee::class, 'head_code', 'code');
    // }

    /** Parent department (id-based — schema uses integer FK) */
    // public function parent(): BelongsTo
    // {
    //     return $this->belongsTo(static::class, 'parent_department_code', 'code');
    // }

    // public function children(): HasMany
    // {
    //     return $this->hasMany(static::class, 'parent_department_code', 'code');
    // }

    /** Divisions: division.dept_code → department.code */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class, 'dept_code', 'code');
    }

    /** DesigDeptTree: desig_dept_tree.dept_code → department.code */
    public function designationTree(): HasMany
    {
        return $this->hasMany(DesigDeptTree::class, 'dept_code', 'code');
    }

    /** Posts: xlr8_iam_roles.dept_code → department.code */
    public function posts(): HasMany
    {
        return $this->hasMany(\App\Models\Iam\Post::class, 'dept_code', 'code');
    }

    /**
     * Employees via pivot.
     * Pivot table: xlr8_admin_emp_department_pivot
     * Pivot FK cols: dept_code (string), employee_code (string)
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'xlr8_admin_emp_department_pivot',
            'dept_code',     
            'employee_code', 
            'code',          
            'code'         
        )->withPivot('division_code', 'assignment_type', 'is_current', 'from_date', 'to_date')
            ->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }
    public function scopeTopLevel($q)
    {
        return $q->whereNull('parent_department_code');
    }

    public function setCodeAttribute(string $v): void
    {
        $this->attributes['code'] = strtoupper(trim($v));
    }
}
