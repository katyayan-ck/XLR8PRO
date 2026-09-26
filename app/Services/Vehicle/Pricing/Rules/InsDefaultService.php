<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsDefault;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Default insurance companies per model and permit (xlr8_vehicle_pricing_ins_defaults) — their
 * only write path (DEC-050/056). One row per company; priority 1 is the default.
 *
 * @extends EntityService<InsDefault>
 */
final class InsDefaultService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return InsDefault::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::scope('model_code', 40)->label('Model')->required(),
            Field::scope('permit', 30, 'Permit')->label('Permit')->default('Private'),
            Field::text('insurance_company', 40)->label('Insurance Co')->required(),
            Field::integer('priority', 1)->label('Priority')->rules('max:65535')->default(1),
            Field::flag('is_default', false),
            Field::flag('is_active', true),
        ];
    }
}
