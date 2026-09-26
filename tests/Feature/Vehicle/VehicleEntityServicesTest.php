<?php

namespace Tests\Feature\Vehicle;

use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\SegmentService;
use App\Services\Vehicle\VariantService;
use App\Services\Vehicle\VehicleModelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-050: one field-rule set per entity, enforced by its service for every entry point
 * (the CRUD controllers and the vehicle import call exactly these methods).
 */
class VehicleEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    private function aSubSegment(): object
    {
        return DB::table('xlr8_vehicle_subsegment')->where('is_active', 1)->whereNull('deleted_at')->first(['code', 'segment_code']);
    }

    public function test_codes_are_normalised_the_same_way_for_every_caller(): void
    {
        $sub = $this->aSubSegment();

        $model = app(VehicleModelService::class)->create([
            'segment_code' => strtolower($sub->segment_code),
            'sub_segment_code' => ' '.strtolower($sub->code).' ',
            'code' => '  zeta roxx + ',
            'name' => 'zeta roxx',
            'is_active' => 'Yes',
        ]);

        $this->assertSame('ZETA-ROXX-PLUS', $model->code);
        $this->assertSame('Zeta Roxx', $model->name);
        $this->assertSame($sub->code, $model->sub_segment_code);
        $this->assertTrue((bool) $model->is_active);
    }

    public function test_invalid_input_is_rejected_with_field_errors(): void
    {
        try {
            app(VehicleModelService::class)->create(['segment_code' => 'NOPE', 'code' => '', 'name' => '']);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['segment_code', 'code', 'name'], array_keys($e->errors()));
        }
    }

    public function test_code_is_immutable_and_absent_fields_keep_their_value(): void
    {
        $sub = $this->aSubSegment();
        $service = app(VehicleModelService::class);
        $model = $service->create(['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'code' => 'ZT-IMM', 'name' => 'Zeta Imm']);

        $service->update($model, ['code' => 'ZT-CHANGED', 'name' => 'Zeta Renamed']);

        $this->assertDatabaseHas('xlr8_vehicle_model', ['id' => $model->id, 'code' => 'ZT-IMM', 'name' => 'Zeta Renamed', 'segment_code' => $sub->segment_code]);
    }

    public function test_business_rules_run_for_every_caller(): void
    {
        $sub = $this->aSubSegment();
        $other = DB::table('xlr8_vehicle_segment')->whereNull('deleted_at')->where('code', '!=', $sub->segment_code)->value('code');
        $models = app(VehicleModelService::class);

        $this->expectException(ValidationException::class);
        $models->create(['segment_code' => $other, 'sub_segment_code' => $sub->code, 'code' => 'ZT-MISMATCH', 'name' => 'Mismatch']);
    }

    public function test_variant_upsert_is_keyed_on_code_and_colour(): void
    {
        $sub = $this->aSubSegment();
        app(VehicleModelService::class)->create(['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'code' => 'ZT-UPS', 'name' => 'Zeta Ups']);
        $variants = app(VariantService::class);
        $row = ['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'model_code' => 'ZT-UPS', 'code' => '1ZT2UPS0000WR', 'oem_name' => 'ZETA UPS', 'taxi_price' => 'no'];

        $variants->upsert($row + ['color' => 'warm red', 'color_code' => 'wr']);
        $variants->upsert($row + ['color' => 'white', 'color_code' => 'ws']);
        $variants->upsert($row + ['color' => 'warm red metallic', 'color_code' => 'WR']);

        $this->assertSame(2, DB::table('xlr8_vehicle_variant')->where('code', '1ZT2UPS0000WR')->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('xlr8_vehicle_variant', ['code' => '1ZT2UPS0000WR', 'color_code' => 'WR', 'color' => 'WARM RED METALLIC', 'taxi_price' => 'NO']);
    }

    public function test_taxi_price_accepts_only_yes_or_no(): void
    {
        $sub = $this->aSubSegment();
        $model = VehicleModel::where('segment_code', $sub->segment_code)->where('sub_segment_code', $sub->code)->value('code');
        if (! $model) {
            $this->markTestSkipped('No model under the chosen sub segment.');
        }

        $this->expectException(ValidationException::class);
        app(VariantService::class)->create(['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'model_code' => $model, 'code' => '1ZTTAXI', 'oem_name' => 'X', 'taxi_price' => 'maybe']);
    }

    public function test_the_model_backstop_uses_the_service_definition(): void
    {
        $this->assertSame(app(SegmentService::class)->transformations()['code'], ['trim', 'uppercase_alphanumeric_dash_underscore']);

        $model = new VehicleModel(['code' => 'thar roxx', 'name' => 'thar roxx', 'segment_code' => 'pv']);
        $model->applyColumnTransformations();
        $this->assertSame('THAR-ROXX', $model->code);
    }
}
