<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class ChangeFlag extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_change_flags';

    protected $fillable = [
        'import_session_id',
        'segment',
        'change_type',
        'model_code',
        'variant_code',
        'field_name',
        'old_value',
        'new_value',
        'is_processed',
    ];

    protected function casts(): array
{
    return array_merge(parent::casts(), [
        'is_processed' => 'boolean',
    ]);
}

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('is_processed', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('change_type', $type);
    }
}