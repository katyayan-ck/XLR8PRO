<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasColumnTransformations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\BaseModel;

/**
 * Table: xlr8_admin_division
 * POST-MIGRATION: div_code and department_id DROPPED.
 * Uses dept_code (varchar 10) as code-based soft ref to xlr8_admin_department.code.
 */
class Division extends BaseModel implements HasMedia
{
    use SoftDeletes,
        CrudTrait,
        HasColumnTransformations,
        InteractsWithMedia;

    protected $table = 'xlr8_admin_division';

    protected $fillable = [
        'dept_code',
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

        $this->addMediaCollection('division_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ])
            ->useDisk('public');
    }

    protected $casts = ['is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    /** dept_code → xlr8_admin_department.code (Eloquent-only, no DB FK) */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_code', 'code');
    }

    /** Employees with this as primary division: employee.primary_div_code → division.code */
    public function primaryEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'primary_div_code', 'code');
    }

    /** DesigDeptTree nodes: desig_dept_tree.div_code → division.code */
    public function designationTree(): HasMany
    {
        return $this->hasMany(DesigDeptTree::class, 'div_code', 'code');
    }

    /** Posts: xlr8_iam_roles.div_code → division.code */
    public function posts(): HasMany
    {
        return $this->hasMany(\App\Models\Iam\Post::class, 'div_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }
    public function scopeForDept($q, string $d)
    {
        return $q->where('dept_code', strtoupper(trim($d)));
    }

    // ── Mutators ──────────────────────────────────────────────────────────────
    public function setCodeAttribute(string $v): void
    {
        $this->attributes['code'] = strtoupper(trim($v));
    }
    public function setDeptCodeAttribute(?string $v): void
    {
        $this->attributes['dept_code'] = $v ? strtoupper(trim($v)) : null;
    }
}
