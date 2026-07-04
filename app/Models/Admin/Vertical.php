<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Table: xlr8_admin_vertical
 * Schema has BOTH `code` (varchar 255 unique) AND `vert_code` (varchar 10).
 * Employee pivot uses `vertical_code` → vertical's `code` column.
 * `vert_code` is a legacy duplicate — import writes both same value.
 */

use App\Models\BaseModel;

class Vertical extends BaseModel
{
    use SoftDeletes, CrudTrait, HasColumnTransformations, InteractsWithMedia;

    protected $table = 'xlr8_admin_vertical';

    protected $fillable = ['code', 'vert_code', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected array $columnTransformations = [
        'code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name' => ['trim_spaces', 'title_case'],
    ];

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('vertical_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp'
            ])
            ->useDisk('public');
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    /** Employee pivot: emp_vertical_pivot.vertical_code → vertical.code */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeVerticalAssignment::class, 'vertical_code', 'code');
    }

    public function employees()
    {
        return $this->hasManyThrough(
            Employee::class,
            EmployeeVerticalAssignment::class,
            'vertical_code',
            'code',
            'code',
            'employee_code'
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }

    // ── Mutators ──────────────────────────────────────────────────────────────
    public function setCodeAttribute(string $v): void
    {
        $this->attributes['code']      = strtoupper(trim($v));
        $this->attributes['vert_code'] = substr(strtoupper(trim($v)), 0, 10);
    }
}
