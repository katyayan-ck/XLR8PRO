<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * RTO rules (xlr8_vehicle_pricing_rto_rules) — their only write path (DEC-050/056): the RTO Rules
 * screen and the Insurance + RTO rules workbook.
 *
 * Scope columns are matched per the Machine Spec (ANY/blank = all; Permit and Fuel through
 * synonyms first); money columns are NOT NULL with default 0, so a blank amount is 0.
 *
 * @extends EntityService<RtoRule>
 */
final class RtoRuleService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return RtoRule::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::text('code', 50)->label('Code'),
            Field::scope('permit', 30, 'Permit')->label('Permit')->required(),
            RuleFields::wheels(),
            Field::scope('reg_type', 20)->label('Reg Type'),
            Field::scope('body_type', 30)->label('Body Type'),
            Field::scope('gvw_range', 30)->label('GVW'),
            Field::scope('seater', 20)->label('Seater'),
            Field::scope('fuel_type', 30, 'Fuel')->label('Fuel'),
            Field::scope('cc_range', 30)->label('CC'),
            Field::scope('assessable_range', 40)->label('Assessable Value'),
            Field::number('tax_factor')->label('Tax Factor')->default(0),
            Field::text('tax_basis', 120)->label('Tax Basis'),
            Field::text('tax_slab', 50)->label('Tax Slab'),
            Field::percent('surcharge')->label('Surcharge')->default(0),
            Field::text('surcharge_formula', 120)->label('Surcharge Formula'),
            Field::number('hypothecation')->label('Hypothecation')->default(0),
            Field::number('green_tax')->label('Green Tax')->default(0),
            Field::number('registration_fee')->label('Registration Fee')->default(0),
            Field::number('duplicate_tax_card')->label('Duplicate Tax Card')->default(0),
            Field::number('fitness')->label('Fitness')->default(0),
            Field::number('penalty')->label('Penalty')->default(0),
            Field::number('rto_tape')->label('Outside State - TRC')->default(0),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
            Field::json('extra_json'),
        ];
    }
}
