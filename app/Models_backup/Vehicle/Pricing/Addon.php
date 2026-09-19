<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addon extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_addons';

    protected $fillable = [
        'import_session_id',
        'segment',
        'model_code',
        'variant_code',
        'addon_type',
        'scheme_name',
        'tenure_years',
        'name',
        'amount',
        'oem_share',
        'dealer_share',
        'shield_pack',
        'transmission',
        'fuel',
        'permit',
        'is_default',
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
            'tenure_years' => 'integer',
            'amount'       => 'decimal:2',
            'oem_share'    => 'decimal:2',
            'dealer_share' => 'decimal:2',
            'is_default'   => 'boolean',
            'is_active'    => 'boolean',
            'wef_date'     => 'date',
            'expired_on'   => 'date',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expired_on')->orWhere('expired_on', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('wef_date')->orWhere('wef_date', '<=', now()->toDateString());
            });
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('addon_type', strtoupper($type));
    }
}
