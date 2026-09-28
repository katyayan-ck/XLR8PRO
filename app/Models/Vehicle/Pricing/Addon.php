<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $import_session_id
 * @property string $addon_type
 * @property string|null $segment
 * @property string $model_code
 * @property string|null $variant_code
 * @property string|null $permit
 * @property string|null $shield_pack
 * @property string|null $transmission
 * @property string|null $fuel
 * @property string|null $scheme_name
 * @property string|null $name
 * @property int|null $tenure_years
 * @property string $amount
 * @property string $oem_share
 * @property string $dealer_share
 * @property string|null $default_allocation
 * @property bool $is_default
 * @property Carbon|null $wef_date
 * @property Carbon|null $expired_on
 * @property bool $is_active
 */
class Addon extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_addons';

    /** Columns = the entity service's fields (DEC-050/057); the service owns their rules. */
    protected string $entityService = AddonService::class;

    protected $fillable = [
        'import_session_id',
        'addon_type',
        'segment',
        'model_code',
        'variant_code',
        'permit',
        'shield_pack',
        'transmission',
        'fuel',
        'scheme_name',
        'name',
        'tenure_years',
        'amount',
        'oem_share',
        'dealer_share',
        'default_allocation',
        'is_default',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'tenure_years' => 'integer',
            'amount' => 'decimal:2',
            'oem_share' => 'decimal:2',
            'dealer_share' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
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
