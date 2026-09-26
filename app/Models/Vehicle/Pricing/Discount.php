<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Addons\DiscountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_discounts';

    /** Columns = the entity service's fields (DEC-050/057); the service owns their rules. */
    protected string $entityService = DiscountService::class;

    protected $fillable = [
        'import_session_id',
        'discount_type',
        'model_code',
        'variant_code',
        'scheme_name',
        'category',
        'discount_category',
        'name',
        'oem_share',
        'dealer_share',
        'amount',
        'total_discount',
        'allocation_type',
        'is_conditional',
        'linked_to',
        'extra_json',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'oem_share' => 'decimal:2',
            'dealer_share' => 'decimal:2',
            'amount' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'extra_json' => 'array',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
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
