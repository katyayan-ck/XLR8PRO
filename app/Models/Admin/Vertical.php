<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
// use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * Table: xlr8_admin_vertical
 * Schema has BOTH `code` (varchar 255 unique) AND `vert_code` (varchar 10).
 * Employee pivot uses `vertical_code` → vertical's `code` column.
 * `vert_code` is a legacy duplicate — import writes both same value.
 */

use Spatie\MediaLibrary\InteractsWithMedia;

class Vertical extends BaseModel
{
    use CrudTrait, HasColumnTransformations, InteractsWithMedia, SoftDeletes;

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
                'image/webp',
            ])
            ->useDisk('public');
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    // employees()/employeeAssignments() removed: xlr8_admin_emp_vertical_pivot does not exist
    // (BUG-081, DEC-044). Employees carry vertical_code directly.

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }

    // ── Mutators ──────────────────────────────────────────────────────────────
    public function setCodeAttribute(string $v): void
    {
        $this->attributes['code'] = strtoupper(trim($v));
        $this->attributes['vert_code'] = substr(strtoupper(trim($v)), 0, 10);
    }
}
