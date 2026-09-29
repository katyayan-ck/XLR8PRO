<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsAddon;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Insurance add-on master (xlr8_vehicle_pricing_ins_addons) — its only write path (DEC-050 / DEC-083). `is_default` marks
 * the add-ons of the default (frozen) insurance combo the engine prices.
 *
 * @extends EntityService<InsAddon>
 */
final class InsAddonService extends EntityService
{
    protected function model(): string
    {
        return InsAddon::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 40)->label('Code')->format('Upper case slug, as the insurance rules rate it (e.g. NIL_DEP)')->required()->unique()->immutable(),
            Field::name('name', 120)->label('Name')->required(),
            Field::flag('is_default', false)->label('In the default combo'),
            Field::integer('sort_order')->label('Order')->default(0),
            Field::flag('is_active', true)->label('Active'),
        ];
    }
}
