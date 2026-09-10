<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryScope extends BaseModel
{
    protected $table = 'xlr8_vehicle_accessory_scopes';

    protected $fillable = [
        'part_no',
        'segment_code',
        'model_code',
        'variant_code',
        'permit',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class, 'part_no', 'part_no');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_code', 'code');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_code', 'code');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNull('deleted_at');
    }
}
