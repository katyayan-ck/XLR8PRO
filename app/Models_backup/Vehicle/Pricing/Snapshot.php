<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Snapshot extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_snapshots';

    protected $fillable = [
        'import_session_id',
        'model_code',
        'variant_code',
        'channel',
        'vin_type',
        'wef_date',
        'payload',
        'is_active',
        'expired_on',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->casts = array_merge($this->casts ?? [], [
            'payload'    => 'array',
            'is_active'  => 'boolean',
            'wef_date'   => 'date',
            'expired_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeForCode(Builder $query, string $oemCode): Builder
    {
        return $query->where('model_code', strtoupper(trim($oemCode)));
    }
}
