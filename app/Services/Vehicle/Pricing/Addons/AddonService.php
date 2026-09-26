<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Addons;

use App\Models\Vehicle\Pricing\Addon;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Add-ons — RSA and Shield (xlr8_vehicle_pricing_addons) — their only write path (DEC-050/057).
 * Scope: ANY / blank = all; `model_code` is NOT NULL, so "all" is stored as ANY. A group import
 * expires only its own type (`expireActive($wef, ['addon_type' => 'RSA'])`).
 *
 * @extends EntityService<Addon>
 */
final class AddonService extends EntityService
{
    use ExpiresActiveRows;

    public const TYPES = ['RSA', 'SHIELD'];

    protected function model(): string
    {
        return Addon::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::choice('addon_type', self::TYPES)->label('Add-on Type')->required(),
            Field::scope('segment', 20, 'Segment', anyIsBlank: true)->label('Segment'),
            Field::scope('model_code', 40, anyIsBlank: true)->label('Model')->default('ANY'),
            Field::scope('variant_code', 40, anyIsBlank: true)->label('Variant'),
            Field::scope('permit', 30, 'Permit', anyIsBlank: true)->label('Permit'),
            Field::scope('shield_pack', 40, anyIsBlank: true)->label('Shield Pack'),
            Field::scope('transmission', 30, anyIsBlank: true)->label('Transmission'),
            Field::scope('fuel', 30, 'Fuel', anyIsBlank: true)->label('Fuel'),
            Field::text('scheme_name', 100)->label('Scheme'),
            Field::text('name', 100)->label('Name'),
            Field::integer('tenure_years')->label('Years')->rules('max:255'),
            Field::number('amount')->label('Amount')->default(0),
            Field::number('oem_share')->label('OEM Share')->default(0),
            Field::number('dealer_share')->label('Dealer Share')->default(0),
            Field::text('default_allocation', 5)->default('B'),
            Field::flag('is_default', false),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
        ];
    }
}
