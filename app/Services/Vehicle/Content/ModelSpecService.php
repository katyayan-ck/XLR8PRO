<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\ModelSpec;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * A model's value for a specification item (DEC-092) — the only write path. One row per (model, item); `upsert()`
 * matches on that pair. A value of "-" / "-NA-" / "NA" is stored as "N/A" (not applicable).
 *
 * @extends EntityService<ModelSpec>
 */
final class ModelSpecService extends EntityService
{
    public const NOT_APPLICABLE = 'N/A';

    protected function model(): string
    {
        return ModelSpec::class;
    }

    protected function naturalKey(): array
    {
        return ['model_code', 'spec_item_code'];
    }

    public function fields(): array
    {
        return [
            Field::reference('model_code', 'xlr8_vehicle_model', 50)->label(__('vehicle.fields.model'))->required()->immutable(),
            Field::reference('spec_item_code', 'xlr8_vehicle_spec_item', 60)->label(__('vehicle.fields.spec_item'))->required()
                ->unique(['model_code'], includeTrashed: true)->immutable(),
            Field::text('value', 500)->label(__('vehicle.fields.value')),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $data['value'] = self::normaliseValue($data['value'] ?? null);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        if (array_key_exists('value', $data)) {
            $data['value'] = self::normaliseValue($data['value']);
        }
    }

    /** "-", "-NA-", "NA", "N.A." → "N/A"; blank → null. */
    public static function normaliseValue(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return preg_match('/^[-_\s]*(n\.?\s*\/?\s*a\.?)?[-_\s]*$/i', $text) ? self::NOT_APPLICABLE : $text;
    }
}
