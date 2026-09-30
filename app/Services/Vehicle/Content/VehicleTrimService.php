<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleTrim;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Trim record (DEC-092): one per variant code (shared by its colour rows) — the only write path. `forVariant()` returns
 * it, creating it on first use.
 *
 * @extends EntityService<VehicleTrim>
 */
final class VehicleTrimService extends EntityService
{
    protected function model(): string
    {
        return VehicleTrim::class;
    }

    protected function naturalKey(): array
    {
        return ['variant_code'];
    }

    public function fields(): array
    {
        return [
            Field::reference('variant_code', 'xlr8_vehicle_variant', 50)->label(__('vehicle.fields.variant'))->required()
                ->unique(includeTrashed: true)->immutable(),
            Field::reference('model_code', 'xlr8_vehicle_model', 50)->label(__('vehicle.fields.model'))->required(),
            Field::text('notes', 2000)->label(__('vehicle.fields.notes')),
        ];
    }

    /** The trim of a variant code (created from its first colour row when missing); null for an unknown code. */
    public function forVariant(string $variantCode): ?VehicleTrim
    {
        $variantCode = strtoupper(trim($variantCode));
        $trim = VehicleTrim::query()->where('variant_code', $variantCode)->first();
        if ($trim !== null) {
            return $trim;
        }
        $variant = Variant::query()->where('code', $variantCode)->first(['code', 'model_code']);

        return $variant ? $this->create(['variant_code' => $variantCode, 'model_code' => $variant->getAttribute('model_code')]) : null;
    }
}
