<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_discounts';

    protected $fillable = [
        'import_session_id',
        'discount_type',
        'scheme_name',
        'category',
        'discount_category',
        'name',
        'segment',
        'model_code',
        'variant_code',
        'oem_share',
        'dealer_share',
        'amount',
        'total_discount',
        'allocation_type',
        'extra_json',
        'is_active',
        'wef_date',
        'expired_on',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'oem_share'      => 'decimal:2',
            'dealer_share'   => 'decimal:2',
            'amount'         => 'decimal:2',
            'total_discount' => 'decimal:2',
            'extra_json'     => 'array',
            'is_active'      => 'boolean',
            'wef_date'       => 'date',
            'expired_on'     => 'date',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('discount_type', strtoupper($type));
    }
}
