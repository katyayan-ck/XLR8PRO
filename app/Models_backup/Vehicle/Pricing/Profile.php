<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Profile extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_profile';

    protected $fillable = [
        'import_session_id',
        'model_code',
        'variant_code',
        'segment',
        'permit',
        'taxi_price',
        'fuel_type',
        'wheels',
        'seating',
        'cc_or_power',
        'gvw',
        'body_type',
        'transmission',
        'is_vehicle_master_complete',
        'is_pricing_template_complete',
        'is_rule_profile_complete',
        'is_publishable',
        'is_disabled',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'taxi_price'                   => 'boolean',
            'wheels'                       => 'integer',
            'seating'                      => 'integer',
            'is_vehicle_master_complete'   => 'boolean',
            'is_pricing_template_complete' => 'boolean',
            'is_rule_profile_complete'     => 'boolean',
            'is_publishable'               => 'boolean',
            'is_disabled'                  => 'boolean',
        ]);
    }

    public function scopeForModel(Builder $query, string $modelCode): Builder
    {
        return $query->where('model_code', $modelCode);
    }

    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('is_publishable', true)->where('is_disabled', false);
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_pricing_template_complete', false)
              ->orWhere('is_vehicle_master_complete', false);
        });
    }

    public function scopeOfSegment(Builder $query, string $segment): Builder
    {
        return $query->where('segment', $segment);
    }

    /**
     * Active for sale = master complete, not disabled, publishable when pricing ready.
     */
    public function canBeMarkedActive(): bool
    {
        return (bool) $this->is_vehicle_master_complete && !(bool) $this->is_disabled;
    }
}
