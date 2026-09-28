<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\Addon;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-057: add-ons, discounts and dealer charges are written only through their entity services
 * (the Addon-N-Discounts workbook import uses them).
 */
class PricingAddonEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_scope_is_stored_the_way_each_table_expects(): void
    {
        $charge = app(DealerChargeService::class)->create(['segment' => 'ANY', 'model_code' => 'all', 'incidental' => '₹2,500']);
        $this->assertSame(['ANY', null, '2500.00', '0.00'], [$charge->segment, $charge->model_code, $charge->incidental, $charge->fastag]);

        $addon = app(AddonService::class)->create(['addon_type' => 'rsa', 'model_code' => 'Any', 'amount' => '999']);
        $this->assertSame(['RSA', 'ANY'], [$addon->addon_type, $addon->model_code]);
    }

    public function test_an_all_zero_dealer_charge_row_is_refused_at_any_but_allowed_for_a_segment(): void
    {
        $zero = app(DealerChargeService::class)->create(['segment' => 'SUV', 'incidental' => '0', 'fastag' => '-']);
        $this->assertSame(['SUV', '0.00'], [$zero->segment, $zero->incidental], 'an explicit "no charges" rule for one segment (DEC-077)');

        $this->expectException(ValidationException::class);
        app(DealerChargeService::class)->create(['segment' => 'ANY', 'incidental' => '0', 'fastag' => '-']);
    }
}
