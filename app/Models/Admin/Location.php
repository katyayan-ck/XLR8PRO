<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Org\LocationService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends BaseModel
{
    use CrudTrait, HasColumnTransformations, SoftDeletes;

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

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = LocationService::class;

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('location_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ])
            ->useDisk('public');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_sales_location' => 'boolean',
        'is_workshop' => 'boolean',
        'is_parts_location' => 'boolean',
        'is_stock_location' => 'boolean',
        'is_office_only' => 'boolean',
        'is_mwh' => 'boolean',
        'is_lmmws' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    /** branch_code → xlr8_admin_branch.branch_code */
    public function branch(): BelongsTo
    {
        // Keyed on Branch.code: Branch.branch_code is never populated (BUG-084, DEC-044).
        return $this->belongsTo(Branch::class, 'branch_code', 'code');
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
            'Sales' => $this->is_sales_location,
            'Workshop' => $this->is_workshop,
            'Parts' => $this->is_parts_location,
            'Stock' => $this->is_stock_location,
            'Office' => $this->is_office_only,
            'MWH' => $this->is_mwh,
            'LMMWS' => $this->is_lmmws,
        ]));
    }
}
