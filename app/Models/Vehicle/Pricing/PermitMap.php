<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\PermitMapService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vehicle permit (+ wheels) → the RTO rule permit and the insurance permit a snapshot uses (DEC-073 / DEC-078):
 * e.g. a 4-wheel taxi's Passenger snapshot → RTO "Taxi", insurance "Passenger"; MISC → "Ambulance" / "Misc".
 *
 * @property int $id
 * @property string $vehicle_permit
 * @property int|null $wheels
 * @property string $rto_permit
 * @property string $insu_permit
 * @property string|null $label
 * @property bool $is_active
 */
class PermitMap extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_permit_map';

    protected string $entityService = PermitMapService::class;

    protected $fillable = ['vehicle_permit', 'wheels', 'rto_permit', 'insu_permit', 'label', 'is_active'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['wheels' => 'integer', 'is_active' => 'boolean']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
