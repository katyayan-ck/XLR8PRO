<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Services\Vehicle\Content\SpecItemService;

/**
 * Specification item master (DEC-092): a category (Engine, Brakes …) and an item (Displacement …), optional unit.
 */
class SpecItem extends BaseModel
{
    protected $table = 'xlr8_vehicle_spec_item';

    protected $fillable = [
        'code',
        'category',
        'name',
        'unit',
        'sort',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = SpecItemService::class;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ]);
    }
}
