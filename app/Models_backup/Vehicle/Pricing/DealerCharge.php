<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerCharge extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_dealer_charges';

    protected $fillable = [
        'import_session_id',
        'segment',
        'permit',
        'model_code',
        'charge_code',
        'charge_name',
        'amount',
        'incidental',
        'fastag',
        'trc',
        'rto_tape',
        'cod',
        'kazam',
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
            'amount'      => 'decimal:2',
            'incidental'  => 'decimal:2',
            'fastag'      => 'decimal:2',
            'trc'         => 'decimal:2',
            'rto_tape'    => 'decimal:2',
            'cod'         => 'decimal:2',
            'kazam'       => 'decimal:2',
            'extra_json'  => 'array',
            'is_active'   => 'boolean',
            'wef_date'    => 'date',
            'expired_on'  => 'date',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
