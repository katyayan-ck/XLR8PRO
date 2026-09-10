<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;

class AddonHistory extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_addon_history';

    public $timestamps = false;

    protected $fillable = [
        'addon_id',
        'model_code',
        'variant_code',
        'addon_type',
        'old_data',
        'new_data',
        'changed_by',
        'change_reason',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function addon()
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }
}