<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Insurance premium base rules (xlr8_vehicle_pricing_ins_base_rules) — their only write path
 * (DEC-050/056). Plan "OD+TP" (e.g. "1+3") gives od_years / tp_years; an unrecognised plan leaves
 * them empty (never guessed). Rates are NOT NULL with default 0.
 *
 * @extends EntityService<InsBaseRule>
 */
final class InsBaseRuleService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return InsBaseRule::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::text('company', 40)->label('Insurance Co'),
            Field::text('plan', 20)->label('Plan')->format('OD+TP years, e.g. 1+3'),
            Field::integer('od_years')->rules('max:10'),
            Field::integer('tp_years')->rules('max:10'),
            Field::scope('permit', 30, 'Permit')->label('Permit')->required(),
            Field::scope('fuel_type', 30, 'Fuel')->label('Fuel'),
            RuleFields::wheels(),
            Field::scope('seating', 30)->label('Seating'),
            Field::scope('cc_range', 30)->label('CC'),
            Field::scope('gvw_range', 30)->label('GVW'),
            Field::number('od_factor')->label('OD Factor')->default(0),
            Field::number('od_surcharge')->label('OD Surcharge')->default(0),
            Field::number('od_discount_rate')->label('OD Discount %')->default(0),
            Field::number('tp_basic')->label('TP Basic')->default(0),
            Field::number('tp_per_passenger')->label('TP per Passenger')->default(0),
            Field::number('tp_legal_driver')->label('TP Legal Driver')->default(0),
            Field::number('tp_non_fare_passenger')->label('TP Non-fare Passenger')->default(0),
            Field::number('tp_bi_fuel_kit')->label('TP Bi-fuel Kit')->default(0),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
        ];
    }

    protected function derive(array $data, array $input): array
    {
        if (($data['plan'] ?? null) !== null && ! isset($data['od_years'], $data['tp_years'])
            && preg_match('/^\s*(\d+)\s*\+\s*(\d+)\s*$/', (string) $data['plan'], $m)) {
            $data['od_years'] ??= (int) $m[1];
            $data['tp_years'] ??= (int) $m[2];
        }

        return $data;
    }
}
