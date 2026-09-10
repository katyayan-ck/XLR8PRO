<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;

class TcsConfig extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_tcs_config';

    protected $fillable = [
        'limit_amount',
        'rate_pct',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'limit_amount' => 'decimal:2',
            'rate_pct'     => 'decimal:2',
            'is_active'    => 'boolean',
        ]);
    }

    public static function current(): self
    {
        return self::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first()
            ?? new self([
                'limit_amount' => 1000000.00,
                'rate_pct'     => 1.00,
                'is_active'    => true,
            ]);
    }
}