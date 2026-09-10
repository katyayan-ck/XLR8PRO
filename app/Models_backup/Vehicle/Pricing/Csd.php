<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Csd extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_csd';

    protected $fillable = [
        'model_code',
        'oem_model',
        'oem_variant',
        'display_name',
        'assessable_value',
        'retail_dealer_margin',
        'basic_price_incl_csd',
        'csd_discount',
        'csd_final_price',
        'gst_rate',
        'gst_amount',
        'csd_profit_050',
        'wef_date',
        'expired_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'assessable_value'     => 'decimal:2',
            'retail_dealer_margin' => 'decimal:2',
            'basic_price_incl_csd' => 'decimal:2',
            'csd_discount'         => 'decimal:2',
            'csd_final_price'      => 'decimal:2',
            'gst_rate'             => 'decimal:2',
            'gst_amount'           => 'decimal:2',
            'csd_profit_050'       => 'decimal:2',
            'wef_date'             => 'date',
            'expired_on'           => 'date',
            'is_active'            => 'boolean',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expired_on')
                  ->orWhere('expired_on', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('wef_date')
                  ->orWhere('wef_date', '<=', now()->toDateString());
            });
    }

    public static function getActive(string $modelCode): ?self
    {
        return self::query()
            ->active()
            ->where('model_code', $modelCode)
            ->orderByDesc('wef_date')
            ->first();
    }
}