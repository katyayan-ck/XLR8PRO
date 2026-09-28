<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

/**
 * The fixed-key pricing JSON, version 2 (DEC-073 / DEC-080). Every snapshot and every getPricing() answer has exactly
 * these keys: unused amounts are 0, unused lists [], unused text null. normalize() fills whatever a producer left out and
 * drops nothing, so consumers never check for a key.
 *
 *   PricingContract::normalize(['ex_showroom' => 1000000])['rto']['total'];   // 0.0
 */
final class PricingContract
{
    public const VERSION = 2;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'contract_version' => self::VERSION, 'source' => 'snapshot', 'published_at' => null,
            'oem_code' => null, 'display_name' => null, 'custom_model' => null, 'custom_variant' => null, 'colour' => null,
            'segment' => null, 'sub_segment' => null, 'model_code' => null, 'price_list' => null,
            'vehicle_permit' => null, 'permit' => null, 'rto_permit' => null, 'insu_permit' => null,
            'fuel' => null, 'taxi_price' => 'NO', 'channel' => 'normal', 'vin_type' => 'NV', 'wef_date' => null,

            'ex_showroom' => 0.0, 'assessable_value' => 0.0, 'gst_percent' => 0.0, 'gst_amount' => 0.0,
            'mm_invoice' => 0.0, 'dealer_margin' => 0.0,

            'dealer_charges' => ['incidental' => 0.0, 'fastag' => 0.0, 'trc' => 0.0, 'rto_tape' => 0.0, 'cod' => 0.0, 'kazam' => 0.0, 'cod_in_total' => false, 'total' => 0.0, 'rule_id' => null],
            'rsa' => ['selected_years' => 0, 'selected_amount' => 0.0, 'standard_coverage' => null, 'options' => []],
            'shield' => ['selected_scheme' => null, 'selected_amount' => 0.0, 'standard_warranty' => null, 'options' => []],
            'accessories' => ['amount' => 0.0, 'discount' => 0.0, 'items' => []],
            'discounts' => [
                'oem_scheme' => 0.0, 'dealer_cont' => 0.0, 'consumer_scheme' => 0.0, 'cash' => 0.0, 'accessory' => 0.0,
                'shield' => 0.0, 'rsa' => 0.0, 'accessory_eligibility' => 0.0, 'shield_eligibility' => 0.0, 'total' => 0.0,
                'exchange' => ['selected' => null, 'amount' => 0.0, 'options' => []],
                'corporate' => ['selected' => null, 'amount' => 0.0, 'options' => []],
            ],
            'insurance' => [
                'insu_permit' => null,
                'default' => ['company' => null, 'plan' => null, 'addons' => [], 'od' => 0.0, 'tp' => 0.0, 'addons_total' => 0.0, 'gst' => 0.0, 'total' => 0.0, 'frozen' => true],
                'companies' => [],
            ],
            'rto' => [
                'rto_permit' => null, 'reg_type' => null, 'rule_id' => null, 'base' => 0.0, 'tax' => 0.0, 'surcharge' => 0.0,
                'hypothecation' => 0.0, 'green_tax' => 0.0, 'registration_fee' => 0.0, 'duplicate_tax_card' => 0.0,
                'fitness' => 0.0, 'penalty' => 0.0, 'outside_state_trc' => 0.0, 'total' => 0.0, 'options' => [],
            ],
            'tcs' => ['limit' => 0.0, 'rate' => 0.0, 'base' => 0.0, 'applicable' => false, 'amount' => 0.0],
            'gross' => 0.0, 'invoice_value' => 0.0, 'on_road' => 0.0,
            'withheld' => ['rsa' => 0.0, 'shield' => 0.0, 'accessories' => 0.0, 'total' => 0.0],
            'hold' => false, 'errors' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        return self::fill(self::defaults(), $payload);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    private static function fill(array $defaults, array $given): array
    {
        $out = $defaults;
        foreach ($given as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($defaults[$key])) {
                $out[$key] = self::fill($defaults[$key], $value);
            } elseif (is_float($defaults[$key] ?? null) && is_numeric($value)) {
                $out[$key] = (float) $value;   // MySQL JSON returns 1000000.0 as 1000000 — amounts stay floats
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
