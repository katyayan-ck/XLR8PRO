<?php

namespace Tests\Unit\Services\Vehicle;

use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\VariantService;
use App\Services\Vehicle\VehicleCompleteness;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-073: one completeness rule — the 16 always-required fields, then Private + ICE → CC, Private + EV → Motor,
 * Goods → GVW, Passenger / Misc → none. Only a complete vehicle may be Active.
 */
class VehicleCompletenessTest extends TestCase
{
    use DatabaseTransactions;

    private function kv(string $keyword, string $code): int
    {
        $id = app(VehicleService::class)->kkvId($keyword, $code);
        if ($id === null) {
            $this->markTestSkipped("Needs key value {$keyword}/{$code} in xlrm_testing.");
        }

        return $id;
    }

    /** A variant with every always-required field filled, for the given permit and fuel. */
    private function variant(string $permit, string $fuel, array $extra = []): Variant
    {
        $model = VehicleModel::query()->whereNotNull('name')->where('name', '!=', '')->first();
        if (! $model) {
            $this->markTestSkipped('Needs a vehicle model in xlrm_testing.');
        }

        return (new Variant)->forceFill(array_merge([
            'segment_code' => $model->segment_code, 'sub_segment_code' => $model->sub_segment_code ?: $model->segment_code,
            'model_code' => $model->code, 'fuel_type_id' => $this->kv('FUEL_TYPE', $fuel), 'seating_capacity' => 5,
            'wheels' => 4, 'transmission' => 'MANUAL', 'drivetrain' => 'FWD', 'body_make_id' => $this->kv('BODY_MAKE', 'SUV'),
            'body_type_id' => $this->kv('BODY_TYPE', 'COMPLETE'), 'gst_percent' => 28, 'permit_id' => $this->kv('PERMIT', $permit),
            'taxi_price' => 'NO', 'custom_name' => 'Base', 'display_name' => 'Zeta Base', 'color' => 'WHITE',
        ], $extra));
    }

    public function test_private_ice_needs_cc_and_private_ev_needs_motor(): void
    {
        $rule = app(VehicleCompleteness::class);

        $this->assertSame(['cc_capacity'], $rule->missing($this->variant('PRIVATE', 'PETROL')));
        $this->assertSame([], $rule->missing($this->variant('PRIVATE', 'PETROL', ['cc_capacity' => '1497'])));
        $this->assertSame(['motor'], $rule->missing($this->variant('PRIVATE', 'ELECTRIC')));
        $this->assertSame([], $rule->missing($this->variant('PRIVATE', 'ELECTRIC', ['motor' => '>65KW'])));
    }

    public function test_goods_needs_gvw_and_passenger_and_misc_need_none_of_the_three(): void
    {
        $rule = app(VehicleCompleteness::class);

        $this->assertSame(['gvw'], $rule->missing($this->variant('GOODS', 'DIESEL')));
        $this->assertSame([], $rule->missing($this->variant('GOODS', 'DIESEL', ['gvw' => 2950])));
        $this->assertSame([], $rule->missing($this->variant('PASSENGER', 'DIESEL')), 'Passenger 4W needs no CC (user decision 28-09)');
        $this->assertSame([], $rule->missing($this->variant('MISC', 'DIESEL')));
    }

    public function test_every_always_required_field_is_checked(): void
    {
        $rule = app(VehicleCompleteness::class);
        $blank = $this->variant('PASSENGER', 'DIESEL', ['taxi_price' => null, 'color' => '', 'display_name' => null, 'wheels' => null]);

        $this->assertSame(['Wheels', 'Taxi Price', 'Display Name', 'Colour Name'], $rule->missingLabels($blank));
    }

    public function test_an_incomplete_vehicle_cannot_be_activated_through_the_variant_service(): void
    {
        $model = VehicleModel::query()->whereNotNull('name')->first();
        if (! $model) {
            $this->markTestSkipped('Needs a vehicle model.');
        }

        try {
            app(VariantService::class)->create([
                'segment_code' => $model->segment_code, 'sub_segment_code' => $model->sub_segment_code, 'model_code' => $model->code,
                'code' => 'ZQCOMPL'.strtoupper(substr(uniqid(), -4)).'WH', 'oem_name' => 'ZETA', 'color_code' => 'WH', 'is_active' => true,
            ]);
            $this->fail('An incomplete vehicle was activated.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
            $this->assertStringContainsString('Missing:', $e->errors()['is_active'][0]);
        }
    }
}
