<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A published price (DEC-073 / DEC-080): one vehicle × channel × VIN type × permit × WEF; payload = the fixed-key
 * contract v2 (Engine\PricingContract). Engine-written; expired (never deleted) when a newer WEF is published.
 *
 * @property int $id
 * @property int|null $import_session_id
 * @property string $model_code
 * @property string|null $variant_code
 * @property string $channel
 * @property string $vin_type
 * @property string $permit
 * @property string|null $rto_permit
 * @property string|null $insu_permit
 * @property Carbon|null $wef_date
 * @property array<string, mixed>|null $payload
 * @property bool $is_active
 * @property Carbon|null $expired_on
 */
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
        'permit',
        'rto_permit',
        'insu_permit',
        'wef_date',
        'payload',
        'is_active',
        'expired_on',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'payload' => 'array',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
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
