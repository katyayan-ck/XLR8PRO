<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\PermitMap;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * The permit map (xlr8_vehicle_pricing_permit_map) — its only write path (DEC-078). The Insurance workbook's
 * "Permit Map" sheet replaces the live rows (expireActive() then create()).
 *
 *   resolve('PASSENGER', 4)  → the row for Passenger 4W (taxi): RTO "Taxi", insurance "Passenger"
 *
 * @extends EntityService<PermitMap>
 */
final class PermitMapService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return PermitMap::class;
    }

    public function fields(): array
    {
        return [
            Field::code('vehicle_permit', 30)->label('Vehicle Permit')->required(),
            RuleFields::wheels(),
            Field::text('rto_permit', 40)->label('RTO Permit')->required(),
            Field::text('insu_permit', 40)->label('Insurance Permit')->required(),
            Field::text('label', 80)->label('Label'),
            Field::flag('is_active', true),
        ];
    }

    /** The live row for a vehicle permit and wheel count (a row without wheels matches any count). */
    public function resolve(string $vehiclePermit, ?int $wheels): ?PermitMap
    {
        return PermitMap::query()->active()
            ->where('vehicle_permit', strtoupper(trim($vehiclePermit)))
            ->where(fn ($q) => $q->whereNull('wheels')->when($wheels !== null, fn ($q) => $q->orWhere('wheels', $wheels)))
            ->orderByRaw('wheels IS NULL')
            ->first();
    }
}
