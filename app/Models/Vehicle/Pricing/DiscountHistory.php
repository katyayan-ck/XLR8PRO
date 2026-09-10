<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;

class DiscountHistory extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_discount_history';

    public $timestamps = false;

    protected $fillable = [
        'discount_id',
        'old_data',
        'new_data',
        'change_type',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}