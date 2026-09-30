<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\SpecItem;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Specification item master (DEC-092) — the only write path. A missing code is derived from category + name.
 *
 * @extends EntityService<SpecItem>
 */
final class SpecItemService extends EntityService
{
    use DerivesItemCode;

    protected function model(): string
    {
        return SpecItem::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 60)->label(__('vehicle.fields.code'))->unique(includeTrashed: true)->immutable(),
            Field::name('category', 100)->label(__('vehicle.fields.spec_category'))->required(),
            Field::name('name', 150)->label(__('vehicle.fields.spec_item'))->required(),
            Field::text('unit', 30)->label(__('vehicle.fields.unit')),
            Field::integer('sort')->label(__('vehicle.fields.sort')),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        if (($data['code'] ?? '') === '') {
            $data['code'] = $this->deriveCode(SpecItem::class, (string) $data['category'], (string) $data['name']);
        }
    }
}
