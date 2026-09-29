<?php

namespace Tests\Feature\Pricing;

use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Services\Vehicle\Pricing\Rules\InsAddonRateService;
use App\Services\Vehicle\Pricing\Rules\InsBaseRuleService;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Services\Vehicle\Pricing\Rules\InsIdvSlotService;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\VehicleService;

/**
 * A complete, priced PV vehicle ("Zeta Z8", taxi-priced) with its RTO / insurance / dealer-charge / RSA rules, WEF
 * 2026-10-01 — the fixture of the calculation and recalculation tests (DEC-080 / DEC-083).
 */
trait BuildsPricedVehicle
{
    protected string $code;

    protected function vehicleAndRules(bool $taxi = true): void
    {
        $vehicles = app(VehicleService::class);
        $vehicles->createStubFromPriceList($this->code, 'ZETA CALC', 'Z8', 'Price List PV');
        $vehicles->applyVehicleInfo($vehicles->findByOemCode($this->code), ['segment' => 'PV', 'sub_segment' => 'PV', 'fuel' => 'DIESEL', 'seating' => '7', 'wheels' => '4',
            'transmission' => 'Manual', 'drivetrain' => 'RWD', 'body_make' => 'SUV', 'body_type' => 'COMPLETE', 'cc' => '2184', 'gst_percent' => '40', 'permit' => 'PRIVATE',
            'taxi_price' => $taxi ? 'Y' : 'N', 'custom_model' => 'Zeta', 'custom_variant' => 'Z8', 'display_name' => 'Zeta Z8', 'colour_name' => 'WHITE', 'status' => 'ACTIVE']);
        app(PriceService::class)->create(['model_code' => $this->code, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-10-01',
            'ex_showroom_price' => 2276500, 'assessable_value_with_freight' => 1557827, 'dealer_margin' => 68245,
            'curr_oem_scheme' => 75000, 'curr_dealer_cont' => 25000, 'curr_cash_discount' => 70000, 'curr_acc_discount' => 30000, 'old_cash_discount' => 10000]);

        $rto = app(RtoRuleService::class);
        $rto->create(['permit' => 'Private', 'wheels' => 4, 'reg_type' => 'Regular', 'fuel_type' => 'Diesel', 'cc_range' => '>1200', 'tax_basis' => '% of Rounded Up ESR',
            'tax_slab' => '0.12', 'tax_factor' => 0.12, 'surcharge' => '12.5% of Tax', 'surcharge_formula' => '12.5% of Tax', 'hypothecation' => 1500, 'green_tax' => 7500,
            'registration_fee' => 600, 'duplicate_tax_card' => 100, 'rto_tape' => 1000, 'wef_date' => '2026-10-01']);
        $rto->create(['permit' => 'Taxi', 'wheels' => 4, 'reg_type' => 'Regular', 'seater' => '0-13', 'tax_basis' => '% of Rounded Up ESR', 'tax_slab' => '0.1', 'tax_factor' => 0.1,
            'surcharge' => '12.5% of Tax', 'surcharge_formula' => '12.5% of Tax', 'hypothecation' => 1500, 'green_tax' => 1500, 'registration_fee' => 600, 'duplicate_tax_card' => 100, 'wef_date' => '2026-10-01']);

        $base = app(InsBaseRuleService::class);
        $private = $base->create(['company' => 'ZQINS', 'plan' => '1+3', 'permit' => 'Private', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500', 'od_factor' => 0.03343,
            'tp_basic' => 24596, 'tp_pa_owner' => 632, 'heads' => ['od_factor' => 0.03343, 'tp_basic' => 24596, 'tp_pa_owner' => 632], 'wef_date' => '2026-10-01']);
        $passenger = $base->create(['company' => 'ZQINS', 'plan' => '1+1', 'permit' => 'Passenger', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500', 'seating' => '1 to 7',
            'od_factor' => 0.0351, 'heads' => ['od_factor' => 0.0351, 'tp_basic' => 10523, 'tp_per_pass' => '1117 x (Seat -1)', 'tp_pa_owner' => 225], 'wef_date' => '2026-10-01']);
        foreach ([$private, $passenger] as $rule) {
            app(InsIdvSlotService::class)->create(['base_rule_id' => $rule->id, 'year_no' => 1, 'idv_basis' => '95% of Invoice']);
        }
        foreach ([['NIL_DEP', '0.0035'], ['CONSUMABLES', '0.001'], ['KEY', '300']] as [$slug, $rate]) {
            app(InsAddonRateService::class)->create(['base_rule_id' => $private->id, 'insurance_company' => 'ZQINS', 'permit' => 'Private', 'addon_slug' => $slug,
                'rate_type' => (float) $rate < 1 ? 'idv_rate' : 'flat', 'rate_value' => $rate, 'rate_text' => $rate, 'wef_date' => '2026-10-01']);
        }
        app(InsDefaultService::class)->create(['model_code' => 'ANY', 'permit' => 'Private', 'insurance_company' => 'ZQINS', 'priority' => 1, 'is_default' => true]);
        app(DealerChargeService::class)->create(['segment' => 'PV', 'permit' => 'Private', 'model_code' => $vehicles->findByOemCode($this->code)->model_code,
            'fastag' => 600, 'trc' => 1000, 'rto_tape' => 1299, 'cod' => 42000, 'wef_date' => '2026-10-01']);
        app(AddonService::class)->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => $vehicles->findByOemCode($this->code)->model_code, 'tenure_years' => 1, 'amount' => 2021, 'wef_date' => '2026-10-01']);
        app(AddonService::class)->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => $vehicles->findByOemCode($this->code)->model_code, 'tenure_years' => 2, 'amount' => 3019, 'wef_date' => '2026-10-01']);
    }
}
