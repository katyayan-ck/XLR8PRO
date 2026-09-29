<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Accessories;

use App\Models\Vehicle\AccessoryScope;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Where an accessory applies (xlr8_vehicle_accessory_scopes: part no × segment / model / variant / permit, blank = all) —
 * the write path of the Accessory Scopes master screen (DEC-050 / DEC-083).
 *
 * @extends EntityService<AccessoryScope>
 */
final class AccessoryScopeService extends EntityService
{
    protected function model(): string
    {
        return AccessoryScope::class;
    }

    protected function naturalKey(): array
    {
        return ['part_no', 'segment_code', 'model_code', 'variant_code', 'permit'];
    }

    public function fields(): array
    {
        return [
            Field::reference('part_no', 'xlr8_vehicle_accessories', 25, 'part_no')->label('Part No.')->required(),
            Field::scope('segment_code', 25, 'Segment', anyIsBlank: true)->label('Segment'),
            Field::scope('model_code', 25, anyIsBlank: true)->label('Model'),
            Field::scope('variant_code', 50, anyIsBlank: true)->label('Variant'),
            Field::scope('permit', 30, 'Permit', anyIsBlank: true)->label('Permit'),
            Field::choice('status', ['1', '0'])->label('Status (1 = active)')->default('1'),
        ];
    }
}
