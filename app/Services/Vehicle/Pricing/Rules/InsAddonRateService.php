<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Insurance add-on rates (xlr8_vehicle_pricing_ins_addon_rates) — their only write path
 * (DEC-050/056). The workbook import only expires them today (no rate importer yet: spec GAP-01).
 *
 * @extends EntityService<InsAddonRate>
 */
final class InsAddonRateService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return InsAddonRate::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::text('insurance_company', 40)->label('Insurance Co')->required(),
            Field::scope('permit', 30, 'Permit')->label('Permit')->default('Private'),
            Field::code('addon_slug', 40)->label('Add-on')->required(),
            Field::text('addon_name', 80)->label('Add-on Name'),
            Field::text('rate_type', 20)->label('Rate Type')->default('fixed'),
            Field::number('rate_value')->label('Rate')->default(0),
            Field::text('applies_on', 20)->label('Applies On')->default('od'),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
        ];
    }
}
