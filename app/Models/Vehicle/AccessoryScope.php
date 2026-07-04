<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AccessoryScope extends BaseModel
{
    protected $table = 'xlr8_vehicle_accessory_scopes';

    protected $fillable = [
        'part_no', 'segment_code', 'model_code', 'variant_code',
        'status', 'created_by', 'updated_by', 'deleted_by',
    ];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class, 'part_no', 'part_no');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\Segment::class, 'segment_code', 'code');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\VehicleModel::class, 'model_code', 'code');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vehicle\Variant::class, 'variant_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNull('deleted_at');
    }
}
