<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasColumnTransformations;
use App\Models\BaseModel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Location extends BaseModel
{
    use SoftDeletes, CrudTrait, HasColumnTransformations;

    protected $table = 'xlr8_admin_location';

    protected $fillable = [
        'branch_code',
        'code',
        'name',
        'description',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'is_active',
        // 7 location-type flags (added by migration):
        'is_sales_location',
        'is_workshop',
        'is_parts_location',
        'is_stock_location',
        'is_office_only',
        'is_mwh',
        'is_lmmws',
    ];

    protected array $columnTransformations = [
        'branch_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'code'        => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'        => ['trim_spaces', 'title_case'],
        'city'        => ['trim_spaces', 'title_case'],
        'state'       => ['trim_spaces', 'title_case'],
        'email'       => ['trim', 'lowercase'],
        'phone'       => 'numeric',
        'pincode'     => 'numeric',
    ];

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('location_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp'
            ])
            ->useDisk('public');
    }

    protected $casts = [
        'is_active'         => 'boolean',
        'is_sales_location' => 'boolean',
        'is_workshop'       => 'boolean',
        'is_parts_location' => 'boolean',
        'is_stock_location' => 'boolean',
        'is_office_only'    => 'boolean',
        'is_mwh'            => 'boolean',
        'is_lmmws'          => 'boolean',
        'latitude'          => 'float',
        'longitude'         => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    /** branch_code → xlr8_admin_branch.branch_code */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_code', 'branch_code');
    }

    /** Posts anchored to this location: post.loc_code → location.code */
    public function posts(): HasMany
    {
        return $this->hasMany(\App\Models\Iam\Post::class, 'loc_code', 'code');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeLocationAssignment::class, 'location_code', 'code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }
    public function scopeForBranch($q, $code)
    {
        return $q->where('branch_code', $code);
    }
    public function scopeSalesLocations($q)
    {
        return $q->where('is_sales_location', true);
    }
    public function scopeWorkshops($q)
    {
        return $q->where('is_workshop', true);
    }
    public function scopePartsLocations($q)
    {
        return $q->where('is_parts_location', true);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getTypeTagsAttribute(): array
    {
        return array_keys(array_filter([
            'Sales'    => $this->is_sales_location,
            'Workshop' => $this->is_workshop,
            'Parts'    => $this->is_parts_location,
            'Stock'    => $this->is_stock_location,
            'Office'   => $this->is_office_only,
            'MWH'      => $this->is_mwh,
            'LMMWS'    => $this->is_lmmws,
        ]));
    }
}
