<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;

class PricingHistory extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_history';

    public $timestamps = false;

    protected $fillable = [
        'pricing_id',
        'model_code',
        'variant_code',
        'wef_date',
        'expired_on',
        'pricing_snapshot',
        'changed_by',
        'change_reason',
    ];

    protected $casts = [
        'wef_date'         => 'date',
        'expired_on'       => 'date',
        'pricing_snapshot' => 'array',
    ];

    public function pricing()
    {
        return $this->belongsTo(Pricing::class, 'pricing_id');
    }
}