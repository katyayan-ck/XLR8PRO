<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsBaseRule extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_base_rules';

    protected $fillable = [
        'code',
        'model_code',
        'variant_code',
        'company',
        'plan',
        'od_years',
        'tp_years',
        'permit',
        'fuel_type',
        'wheels',
        'seating',
        'cc_range',
        'gvw_range',
        'od_factor',
        'od_surcharge',
        'od_discount_rate',
        'imt_23_rate',
        'tp_basic',
        'tp_per_passenger',
        'tp_legal_driver',
        'tp_non_fare_passenger',
        'tp_bi_fuel_kit',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'od_years' => 'integer',
            'tp_years' => 'integer',
            'wheels' => 'integer',
            'od_factor' => 'decimal:6',
            'od_surcharge' => 'decimal:6',
            'od_discount_rate' => 'decimal:6',
            'imt_23_rate' => 'decimal:6',
            'tp_basic' => 'decimal:2',
            'tp_per_passenger' => 'decimal:2',
            'tp_legal_driver' => 'decimal:2',
            'tp_non_fare_passenger' => 'decimal:2',
            'tp_bi_fuel_kit' => 'decimal:2',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
        ]);
    }

    public function idvSlots(): HasMany
    {
        return $this->hasMany(InsIdvSlot::class, 'base_rule_id', 'id')->orderBy('year_no');
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

    /**
     * Most-specific match wins
     */
    public static function findBestMatch(array $criteria): ?self
    {
        $query = self::query()
            ->active()
            ->where('permit', $criteria['permit'] ?? null);

        foreach (['fuel_type', 'wheels', 'seating', 'cc_range', 'gvw_range', 'plan'] as $field) {
            if (! empty($criteria[$field])) {
                $query->where(function ($q) use ($field, $criteria) {
                    $q->where($field, $criteria[$field])->orWhereNull($field);
                });
            }
        }

        return $query->orderByRaw('
            (CASE WHEN fuel_type IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN wheels IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN seating IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN cc_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN gvw_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN plan IS NOT NULL THEN 1 ELSE 0 END) DESC
        ')->first();
    }
}
