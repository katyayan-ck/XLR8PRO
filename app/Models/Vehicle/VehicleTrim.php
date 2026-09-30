<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Services\Vehicle\Content\VehicleTrimService;

/**
 * One trim = one variant code shared by its colour rows (DEC-092): owns the trim-level gallery; features hang off its variant code.
 */
class VehicleTrim extends BaseModel
{
    protected $table = 'xlr8_vehicle_trim';

    protected $fillable = [
        'variant_code',
        'model_code',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = VehicleTrimService::class;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
        ]);
    }

    /** Trim-level gallery (all colours); colour-level images live on the Variant row (collection `gallery`). */
    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();
        $this->addMediaCollection('gallery')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('preview')->width(250)->height(250)->quality(75);
                $this->addMediaConversion('thumb')->width(100)->height(100)->quality(70);
            });
    }

    public function features()
    {
        return $this->hasMany(TrimFeature::class, 'variant_code', 'variant_code');
    }
}
