<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class InsAddonRate extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_addon_rates';

    protected $fillable = [
        'insurance_company',
        'permit',
        'addon_slug',
        'addon_name',
        'rate_type',
        'rate_value',
        'applies_on',
        'conditions',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'rate_value' => 'decimal:4',
            'conditions' => 'array',
            'is_active'  => 'boolean',
            'wef_date'   => 'date',
            'expired_on' => 'date',
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

    public function scopeForCompanyPermit(Builder $query, string $company, string $permit): Builder
    {
        return $query->where('insurance_company', $company)
                     ->where('permit', $permit);
    }
}