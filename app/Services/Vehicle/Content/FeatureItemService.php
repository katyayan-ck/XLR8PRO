<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\FeatureItem;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Feature item master (DEC-092) — the only write path. A missing code is derived from group + name.
 *
 * @extends EntityService<FeatureItem>
 */
final class FeatureItemService extends EntityService
{
    use DerivesItemCode;

    protected function model(): string
    {
        return FeatureItem::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 60)->label(__('vehicle.fields.code'))->unique(includeTrashed: true)->immutable(),
            Field::name('feature_group', 100)->label(__('vehicle.fields.feature_group'))->required(),
            Field::text('name', 255)->label(__('vehicle.fields.feature'))->required(),
            Field::integer('sort')->label(__('vehicle.fields.sort')),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        if (($data['code'] ?? '') === '') {
            $data['code'] = $this->deriveCode(FeatureItem::class, (string) $data['feature_group'], (string) $data['name']);
        }
    }
}
