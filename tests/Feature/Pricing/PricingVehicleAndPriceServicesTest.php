<?php

namespace Tests\Feature\Pricing;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-058: price-list stubs, Vehicle Info rows and prices are written through the entity services
 * (VehicleService delegates to Segment/SubSegment/VehicleModel/Variant/Keyvalue services).
 */
class PricingVehicleAndPriceServicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_price_list_stub_uses_the_canonical_model_code_and_is_found_again(): void
    {
        $vehicles = app(VehicleService::class);
        $code = 'ZQX'.strtoupper(substr(uniqid(), -5)).'RD';

        $first = $vehicles->createStubFromPriceList($code, 'zeta roxx max', 'ax7 l', 'Price List PV');
        $again = $vehicles->createStubFromPriceList($code.'2', 'ZETA ROXX MAX', 'AX7 L', 'Price List PV');

        $this->assertTrue($first['created']);
        $this->assertSame('ZETA-ROXX-MAX', $first['model']->code);
        $this->assertSame($first['model']->id, $again['model']->id, 'second stub reuses the model');
        $this->assertSame(1, VehicleModel::where('code', 'ZETA-ROXX-MAX')->count());
        // DEC-073: a FRESH stub is INCOMPLETE — only what the price list knows; taxi flag / colour name come later
        $this->assertSame(['RD', false, null, null], [$first['variant']->color_code, (bool) $first['variant']->is_active, $first['variant']->taxi_price, $first['variant']->color]);
        $this->assertSame('INCOMPLETE', Keyvalue::whereKey($first['variant']->status_id)->value('code'));
    }

    public function test_vehicle_info_is_applied_through_the_variant_rules(): void
    {
        $vehicles = app(VehicleService::class);
        $stub = $vehicles->createStubFromPriceList('ZQY'.strtoupper(substr(uniqid(), -5)).'WH', 'Zeta Info', 'Base', 'Price List PV');

        $result = $vehicles->applyVehicleInfo($stub['variant'], [
            'custom_variant' => 'base plus',
            'gst_percent' => '28%',
            'seating' => '7',
            'status' => 'INACTIVE',
        ]);

        $variant = $result['variant'];
        $this->assertSame(['Base Plus', '28.00', 7, false], [$variant->custom_name, (string) $variant->gst_percent, (int) $variant->seating_capacity, (bool) $variant->is_active]);
        $this->assertFalse($result['complete']);

        $this->expectException(ValidationException::class);
        $vehicles->applyVehicleInfo($variant, ['seating' => 'seven']);
    }

    public function test_prices_are_keyed_by_code_channel_and_wef_and_expire_into_history(): void
    {
        $prices = app(PriceService::class);
        $old = $prices->create(['model_code' => 'zqzprice01', 'wef_date' => '2026-09-01', 'ex_showroom_price' => '₹8,45,000', 'gst_percent' => '28%']);
        $this->assertSame(['ZQZPRICE01', 'normal', '845000.00', '28.00', '0.00'], [$old->model_code, $old->channel, $old->ex_showroom_price, $old->gst_percent, $old->dealer_margin]);

        $prices->expire($old, '2026-10-01');
        $new = $prices->create(['model_code' => 'ZQZPRICE01', 'wef_date' => '2026-10-01', 'ex_showroom_price' => 850000]);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('2026-10-01', $old->fresh()->expired_on->format('Y-m-d'));
        $this->assertTrue($new->is_active);
        $this->assertSame(2, Pricing::where('model_code', 'ZQZPRICE01')->count());

        $this->expectException(ValidationException::class);
        $prices->create(['model_code' => 'ZQZPRICE01', 'wef_date' => '2026-10-01', 'ex_showroom_price' => 1]);
    }
}
