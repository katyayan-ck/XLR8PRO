<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Services\Vehicle\Content\FeatureItemService;

/**
 * Feature item master (DEC-092): a feature group (Comfort & Convenience …) and a feature.
 */
class FeatureItem extends BaseModel
{
    protected $table = 'xlr8_vehicle_feature_item';

    protected $fillable = [
        'code',
        'feature_group',
        'name',
        'sort',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = FeatureItemService::class;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ]);
    }
}
