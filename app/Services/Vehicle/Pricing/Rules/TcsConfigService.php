<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\TcsConfig;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * TCS configuration (xlr8_vehicle_pricing_tcs_config) — its only write path (DEC-050/056).
 * At most one row is active: saving an active row deactivates the others.
 *
 * @extends EntityService<TcsConfig>
 */
final class TcsConfigService extends EntityService
{
    protected function model(): string
    {
        return TcsConfig::class;
    }

    public function fields(): array
    {
        return [
            Field::number('limit_amount')->label('TCS Limit (₹)')->required(),
            Field::percent('rate_pct')->label('TCS Rate %')->rules('max:100')->required(),
            Field::flag('is_active', true),
        ];
    }

    /**
     * Save the current configuration (updates the active row, or creates the first one).
     *
     * @param  array<string, mixed>  $input
     */
    public function saveCurrent(array $input): TcsConfig
    {
        $current = TcsConfig::current();

        return $current->exists ? $this->update($current, $input) : $this->create($input);
    }

    /** @param  TcsConfig  $model */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        if ($model->is_active) {
            TcsConfig::query()->where('is_active', true)->whereKeyNot($model->getKey())->update(['is_active' => false]);
        }
    }
}
