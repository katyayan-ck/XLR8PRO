<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Services\Vehicle\Content\ModelSpecService;

/**
 * A model's value for one specification item (DEC-092).
 */
class ModelSpec extends BaseModel
{
    protected $table = 'xlr8_vehicle_model_spec';

    protected $fillable = [
        'model_code',
        'spec_item_code',
        'value',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = ModelSpecService::class;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
        ]);
    }

    public function item()
    {
        return $this->belongsTo(SpecItem::class, 'spec_item_code', 'code');
    }
}
