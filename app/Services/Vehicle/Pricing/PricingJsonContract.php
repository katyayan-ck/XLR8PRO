<?php

/**
 * Path: app/Services/Vehicle/Pricing/PricingJsonContract.php
 *
 * Fixed key set. Every vehicle snapshot uses the same keys.
 * Unused numeric keys are 0; unused strings/arrays are null / [].
 */

namespace App\Services\Vehicle\Pricing;

class PricingJsonContract
{
    public static function empty(string $oemCode = '', string $channel = 'normal', string $vinType = 'nv'): array
    {
        return [
            'oem_code'     => $oemCode,
            'channel'      => $channel,
            'vin_type'     => $vinType,
            'wef_date'     => null,
            'segment'      => null,
            'model_code'   => null,
            'display_name' => null,
            'permit'       => null,
            'fuel'         => null,
            'taxi_price'   => 'NO',

            'ex_showroom'      => 0.0,
            'assessable_value' => 0.0,
            'gst_percent'      => 0.0,
            'gst_amount'       => 0.0,
            'mm_invoice'       => 0.0,
            'dealer_margin'    => 0.0,

            'dealer_charges' => [
                'incidental' => 0.0,
                'fasttag'    => 0.0,
                'trc'        => 0.0,
                'rto_tape'   => 0.0,
                'cod'        => 0.0,
                'other'      => 0.0,
                'total'      => 0.0,
                'lines'      => [],
            ],

            'rsa' => [
                'selected_years' => 1,
                'selected_amount'=> 0.0,
                'options'        => [],
            ],

            'shield' => [
                'selected_scheme' => null,
                'selected_amount' => 0.0,
                'options'         => [],
            ],

            'discounts' => [
                'oem_scheme'    => 0.0,
                'dealer_cont'   => 0.0,
                'cash'          => 0.0,
                'accessory'     => 0.0,
                'shield'        => 0.0,
                'rsa'           => 0.0,
                'cash_portion'  => 0.0,
                'credit_note'   => 0.0,
                'corporate'     => [
                    'category' => null,
                    'oem'      => 0.0,
                    'dealer'   => 0.0,
                    'total'    => 0.0,
                    'options'  => [],
                ],
                'exchange'      => [
                    'scheme'  => null,
                    'oem'     => 0.0,
                    'dealer'  => 0.0,
                    'total'   => 0.0,
                    'options' => [],
                ],
            ],

            'insurance' => [
                'default_company' => null,
                'default_plan'    => null,
                'standard_combo'  => ['OD', 'TP', 'NILDEP', 'CONSUMABLES'],
                'selected_total'  => 0.0,
                'companies'       => [],
            ],

            'rto' => [
                'selected_permit' => null,
                'tax'             => 0.0,
                'trc'             => 0.0,
                'hypo'            => 0.0,
                'other'           => 0.0,
                'total'           => 0.0,
                'permit_options'  => [],
                'bifurcation'     => [],
            ],

            'accessories' => [
                'packs'    => [],
                'total'    => 0.0,
                'discount' => 0.0,
            ],

            'tcs' => [
                'limit'      => 1000000.0,
                'rate'       => 1.0,
                'applicable' => false,
                'amount'     => 0.0,
            ],

            'invoice_value' => 0.0,
            'on_road'       => 0.0,
            'on_road_nv'    => 0.0,
            'on_road_ov'    => 0.0,

            'withheld' => [
                'rsa'          => 0.0,
                'shield'       => 0.0,
                'accessories'  => 0.0,
                'total'        => 0.0,
            ],

            'incomplete' => false,
            'hold'       => false,
            'errors'     => [],
        ];
    }
}
