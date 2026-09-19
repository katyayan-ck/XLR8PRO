<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class RtoRule extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_rto_rules';

    protected $fillable = [
        'code',
        'permit',
        'wheels',
        'reg_type',
        'body_type',
        'gvw_range',
        'seater',
        'fuel_type',
        'cc_range',
        'tax_factor',
        'tax_slab',
        'surcharge',
        'hypothecation',
        'green_tax',
        'registration_fee',
        'duplicate_tax_card',
        'fitness',
        'penalty',
        'rto_tape',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'wheels'             => 'integer',
            'tax_factor'         => 'decimal:6',
            'surcharge'          => 'decimal:2',
            'hypothecation'      => 'decimal:2',
            'green_tax'          => 'decimal:2',
            'registration_fee'   => 'decimal:2',
            'duplicate_tax_card' => 'decimal:2',
            'fitness'            => 'decimal:2',
            'penalty'            => 'decimal:2',
            'rto_tape'           => 'decimal:2',
            'is_active'          => 'boolean',
            'wef_date'           => 'date',
            'expired_on'         => 'date',
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

    public static function findBestMatch(array $criteria): ?self
    {
        $query = self::query()
            ->active()
            ->where('permit', $criteria['permit'] ?? null);

        foreach (['wheels', 'fuel_type', 'gvw_range', 'seater', 'cc_range', 'reg_type', 'body_type'] as $field) {
            if (!empty($criteria[$field])) {
                $query->where(function ($q) use ($field, $criteria) {
                    $q->where($field, $criteria[$field])->orWhereNull($field);
                });
            }
        }

        return $query->orderByRaw('
            (CASE WHEN wheels IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN fuel_type IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN gvw_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN seater IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN cc_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN body_type IS NOT NULL THEN 1 ELSE 0 END) DESC
        ')->first();
    }
}