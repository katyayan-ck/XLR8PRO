<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Addons;

use App\Models\Vehicle\Pricing\Discount;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Discounts — Exchange, Corporate… (xlr8_vehicle_pricing_discounts) — their only write path
 * (DEC-050/057). Scope: ANY / blank = all (`model_code` NOT NULL → ANY). The total is the sheet's
 * Total when given, else OEM share + dealer share. A group import expires only its own type.
 *
 * @extends EntityService<Discount>
 */
final class DiscountService extends EntityService
{
    use ExpiresActiveRows;

    protected function model(): string
    {
        return Discount::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::code('discount_type', 40)->label('Discount Type')->required(),
            Field::scope('model_code', 40, anyIsBlank: true)->label('Model')->default('ANY'),
            Field::scope('variant_code', 40, anyIsBlank: true)->label('Variant'),
            Field::text('scheme_name', 120)->label('Scheme'),
            Field::text('category', 80)->label('Category'),
            Field::text('discount_category', 40),
            Field::text('name', 120)->label('Name')->required(),
            Field::number('oem_share')->label('OEM Share')->default(0),
            Field::number('dealer_share')->label('Dealer Share')->default(0),
            Field::number('amount')->label('Amount'),
            Field::number('total_discount')->label('Total')->format('Blank = OEM share + dealer share'),
            Field::text('allocation_type', 5)->default('B'),
            Field::flag('is_conditional', false),
            Field::text('linked_to', 60),
            Field::json('extra_json'),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
        ];
    }

    protected function derive(array $data, array $input): array
    {
        // A blank total is the OEM share + dealer share (as the sheet's Total column means).
        if (($data['total_discount'] ?? null) === null && is_numeric($data['oem_share'] ?? null) && is_numeric($data['dealer_share'] ?? null)) {
            $data['total_discount'] = (string) round((float) $data['oem_share'] + (float) $data['dealer_share'], 2);
        }
        $data['amount'] ??= $data['total_discount'] ?? null;

        return $data;
    }
}
