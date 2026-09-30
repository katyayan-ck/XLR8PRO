<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Services\Vehicle\Content\TrimFeatureService;

/**
 * A trim's (variant code's) value for one feature item (DEC-092): Yes, No or a short text.
 */
class TrimFeature extends BaseModel
{
    protected $table = 'xlr8_vehicle_trim_feature';

    protected $fillable = [
        'variant_code',
        'feature_item_code',
        'value',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = TrimFeatureService::class;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
        ]);
    }

    public function item()
    {
        return $this->belongsTo(FeatureItem::class, 'feature_item_code', 'code');
    }
}
