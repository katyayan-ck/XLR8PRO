<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\TrimFeature;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * A trim's (variant code's) value for a feature item (DEC-092) — the only write path. One row per (variant code,
 * item); `upsert()` matches on that pair. Yes / Y / ✓ → "Yes"; No / N / --- / - → "No"; anything else is kept as text
 * (e.g. "Optional", "2 airbags").
 *
 * @extends EntityService<TrimFeature>
 */
final class TrimFeatureService extends EntityService
{
    protected function model(): string
    {
        return TrimFeature::class;
    }

    protected function naturalKey(): array
    {
        return ['variant_code', 'feature_item_code'];
    }

    public function fields(): array
    {
        return [
            Field::reference('variant_code', 'xlr8_vehicle_variant', 50)->label(__('vehicle.fields.variant'))->required()->immutable(),
            Field::reference('feature_item_code', 'xlr8_vehicle_feature_item', 60)->label(__('vehicle.fields.feature'))->required()
                ->unique(['variant_code'], includeTrashed: true)->immutable(),
            Field::text('value', 255)->label(__('vehicle.fields.value')),
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

    public static function normaliseValue(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        $lower = strtolower($text);
        if (in_array($lower, ['yes', 'y', '✓', '✔', 'true', '1', 'std', 'standard'], true)) {
            return 'Yes';
        }
        if (in_array($lower, ['no', 'n', 'false', '0'], true) || preg_match('/^[-_\s]+$/', $text)) {
            return 'No';
        }

        return $text;
    }
}
