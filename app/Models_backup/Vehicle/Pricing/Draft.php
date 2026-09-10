<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Draft extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_draft';

    protected $fillable = [
        'model_code',
        'permit',
        'vin_type',
        'channel',
        'payload',
        'ex_showroom',
        'on_road_price',
        'import_session_id',
        'generated_at',
    ];

    protected function casts(): array
{
    return array_merge(parent::casts(), [
        'payload'       => 'array',
        'ex_showroom'   => 'decimal:2',
        'on_road_price' => 'decimal:2',
        'generated_at'  => 'datetime',
    ]);
}

    public function scopeForModel(Builder $query, string $modelCode): Builder
    {
        return $query->where('model_code', $modelCode);
    }
}