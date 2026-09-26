<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Prices;

use App\Models\Vehicle\Pricing\Pricing;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Price-list prices per variant, channel and WEF (xlr8_vehicle_pricing) — their only write path
 * (DEC-050/058). One row per (OEM code, channel, WEF): the key is fixed once created. Amounts are
 * NOT NULL with default 0. WEF history (Machine Spec): same WEF → the live row is updated; a new
 * WEF with a material change → the previous row is expired (expire()) and a new row created.
 *
 * @extends EntityService<Pricing>
 */
final class PriceService extends EntityService
{
    public const AMOUNTS = [
        'ex_showroom_price', 'assessable_value_with_freight', 'gst_amount', 'mm_invoice_amount', 'dealer_margin',
        'curr_oem_scheme', 'curr_dealer_cont', 'curr_cash_discount', 'curr_acc_discount', 'curr_shield_discount',
        'old_oem_scheme', 'old_dealer_cont', 'old_cash_discount', 'old_acc_discount', 'old_shield_discount',
    ];

    protected function model(): string
    {
        return Pricing::class;
    }

    protected function naturalKey(): array
    {
        return ['model_code', 'channel', 'wef_date'];
    }

    public function fields(): array
    {
        $fields = [
            Field::make('import_session_id')->rules('integer'),
            Field::code('model_code', 40)->label('OEM Code')->required()->immutable()
                ->unique(['channel', 'wef_date'], includeTrashed: true),
            Field::text('channel', 20)->label('Channel')->transform('lowercase')->default('normal')->immutable(),
            Field::date('wef_date')->label('WEF')->required()->immutable(),
            Field::date('expired_on')->label('Expired On'),
            Field::flag('is_active', true),
            Field::percent('gst_percent')->label('GST %')->rules('max:100')->default(0),
        ];
        foreach (self::AMOUNTS as $column) {
            $fields[] = Field::number($column)->label(ucwords(str_replace('_', ' ', $column)))->default(0);
        }

        return $fields;
    }

    /** Close a live price at the new WEF (history is never deleted). */
    public function expire(Pricing $price, string $wefDate): Pricing
    {
        return $this->update($price, ['is_active' => false, 'expired_on' => $wefDate]);
    }
}
