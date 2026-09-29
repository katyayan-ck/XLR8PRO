<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Accessories;

use App\Models\Vehicle\Accessory;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Accessory catalogue rows (xlr8_vehicle_accessories) — the write path of the Accessories master screen (DEC-050 /
 * DEC-083). The typed-sheet import stays in AccessoryService (the authoritative importer, BUG-179).
 *
 * @extends EntityService<Accessory>
 */
final class AccessoryItemService extends EntityService
{
    protected function model(): string
    {
        return Accessory::class;
    }

    protected function naturalKey(): array
    {
        return ['part_no'];
    }

    public function fields(): array
    {
        return [
            Field::code('part_no', 25)->label('Part No.')->required()->unique()->immutable(),
            Field::choice('type', Accessory::ALL_TYPES)->label('Type')->required(),
            Field::text('display_name', 150)->label('Display Name'),
            Field::text('item', 150)->label('Item Name')->required(),
            Field::number('ndp')->label('NDP'),
            Field::number('mrp')->label('MRP'),
            Field::integer('set_qty', 1)->label('Set Qty')->default(1),
            Field::number('discount')->label('Discount'),
            Field::text('details', 250)->label('Details'),
            Field::flag('bundle', false)->label('Bundle'),
            Field::choice('status', ['1', '0'])->label('Status (1 = active)')->default('1'),
        ];
    }
}
