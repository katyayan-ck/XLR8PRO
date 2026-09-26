<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vehicle master write paths (UAT scope): segment → sub-segment → model → variant → colour
 * create / update / delete, plus the BUG-170 regressions (validated create, code-based segment).
 */
class VehicleMasterWriteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
    }

    public function test_segment_can_be_created_updated_and_deleted(): void
    {
        $this->post('/admin/vehicle/segment', ['code' => 'ZTS', 'name' => 'Zeta Test', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('xlr8_vehicle_segment', ['code' => 'ZTS', 'name' => 'Zeta Test', 'deleted_at' => null]);

        $id = DB::table('xlr8_vehicle_segment')->where('code', 'ZTS')->value('id');

        $this->put("/admin/vehicle/segment/{$id}", ['code' => 'ZTS', 'name' => 'Zeta Renamed', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('xlr8_vehicle_segment', ['id' => $id, 'name' => 'Zeta Renamed']);

        $this->delete("/admin/vehicle/segment/{$id}")->assertSuccessful();
        $this->assertSoftDeleted('xlr8_vehicle_segment', ['id' => $id]);
    }

    public function test_sub_segment_can_be_created_updated_and_deleted(): void
    {
        $segment = DB::table('xlr8_vehicle_segment')->where('is_active', 1)->whereNull('deleted_at')->value('code');

        $this->post('/admin/vehicle/sub-segment', ['segment_code' => $segment, 'code' => 'ZTSUB', 'name' => 'Zeta Sub', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('xlr8_vehicle_subsegment', ['code' => 'ZTSUB', 'segment_code' => $segment, 'name' => 'Zeta Sub']);

        $id = DB::table('xlr8_vehicle_subsegment')->where('code', 'ZTSUB')->value('id');

        $this->put("/admin/vehicle/sub-segment/{$id}", ['segment_code' => $segment, 'code' => 'ZTSUB', 'name' => 'Zeta Sub Two', 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('xlr8_vehicle_subsegment', ['id' => $id, 'name' => 'Zeta Sub Two']);

        $this->delete("/admin/vehicle/sub-segment/{$id}")->assertSuccessful();
        $this->assertSoftDeleted('xlr8_vehicle_subsegment', ['id' => $id]);
    }

    public function test_duplicate_segment_code_is_a_validation_error_not_a_500(): void
    {
        $existing = DB::table('xlr8_vehicle_segment')->whereNull('deleted_at')->value('code');

        $this->from('/admin/vehicle/segment/create')
            ->post('/admin/vehicle/segment', ['code' => $existing, 'name' => 'Duplicate', 'is_active' => 1])
            ->assertRedirect('/admin/vehicle/segment/create')
            ->assertSessionHasErrors('code');
    }

    public function test_sub_segment_edit_saves_a_new_segment_when_no_models_use_it(): void
    {
        [$from, $to] = DB::table('xlr8_vehicle_segment')->where('is_active', 1)->whereNull('deleted_at')->limit(2)->pluck('code')->all();
        $this->post('/admin/vehicle/sub-segment', ['segment_code' => $from, 'code' => 'ZTMOV', 'name' => 'Zeta Move', 'is_active' => 1]);
        $id = DB::table('xlr8_vehicle_subsegment')->where('code', 'ZTMOV')->value('id');

        $this->put("/admin/vehicle/sub-segment/{$id}", ['segment_code' => $to, 'code' => 'ZTMOV', 'name' => 'Zeta Move', 'is_active' => 1])
            ->assertRedirect('/admin/vehicle/sub-segment');
        $this->assertDatabaseHas('xlr8_vehicle_subsegment', ['id' => $id, 'segment_code' => $to]);
    }

    public function test_sub_segment_cannot_move_segment_while_models_use_it(): void
    {
        $used = DB::table('xlr8_vehicle_subsegment as s')->join('xlr8_vehicle_model as m', 'm.sub_segment_code', '=', 's.code')
            ->whereNull('s.deleted_at')->select('s.id', 's.code', 's.name', 's.segment_code')->first();
        $other = DB::table('xlr8_vehicle_segment')->whereNull('deleted_at')->where('code', '!=', $used?->segment_code)->value('code');
        if (! $used || ! $other) {
            $this->markTestSkipped('Needs a sub-segment used by a model and a second segment.');
        }

        $this->put("/admin/vehicle/sub-segment/{$used->id}", ['segment_code' => $other, 'code' => $used->code, 'name' => $used->name, 'is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('xlr8_vehicle_subsegment', ['id' => $used->id, 'segment_code' => $used->segment_code]);
    }

    public function test_model_and_variant_colour_rows_can_be_created_updated_and_deleted(): void
    {
        $sub = DB::table('xlr8_vehicle_subsegment')->where('is_active', 1)->whereNull('deleted_at')->first(['id', 'code', 'segment_code']);

        $this->post('/admin/vehicle/model', ['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'code' => 'ZTMODEL', 'name' => 'Zeta Model', 'is_active' => 1])
            ->assertRedirect('/admin/vehicle/model');
        $this->assertDatabaseHas('xlr8_vehicle_model', ['code' => 'ZTMODEL', 'sub_segment_code' => $sub->code]);
        $modelId = DB::table('xlr8_vehicle_model')->where('code', 'ZTMODEL')->value('id');

        // Code is immutable on edit (DEC-048): a changed code in the request is ignored.
        $this->put("/admin/vehicle/model/{$modelId}", ['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'code' => 'ZTOTHER', 'name' => 'Zeta Model Two', 'is_active' => 1])
            ->assertRedirect('/admin/vehicle/model');
        $this->assertDatabaseHas('xlr8_vehicle_model', ['id' => $modelId, 'code' => 'ZTMODEL', 'name' => 'Zeta Model Two']);

        // One variant = one row per colour, sharing the full OEM code.
        $row = ['segment_code' => $sub->segment_code, 'sub_segment_code' => $sub->code, 'model_code' => 'ZTMODEL', 'code' => '1ZT2NR1T9LVB1', 'oem_name' => 'ZETA VX', 'taxi_price' => 'No', 'is_active' => 1];
        $this->post('/admin/vehicle/variant', $row + ['color' => 'WARM RED', 'color_code' => 'WR'])->assertRedirect('/admin/vehicle/variant');
        $this->post('/admin/vehicle/variant', $row + ['color' => 'WHITE', 'color_code' => 'WS'])->assertRedirect('/admin/vehicle/variant');
        $this->assertSame(2, DB::table('xlr8_vehicle_variant')->where('code', '1ZT2NR1T9LVB1')->whereNull('deleted_at')->count());

        $this->from('/admin/vehicle/variant/create')
            ->post('/admin/vehicle/variant', $row + ['color' => 'WHITE', 'color_code' => 'WS'])
            ->assertSessionHasErrors('code');

        $whiteId = DB::table('xlr8_vehicle_variant')->where('code', '1ZT2NR1T9LVB1')->where('color_code', 'WS')->value('id');
        $this->put("/admin/vehicle/variant/{$whiteId}", $row + ['color' => 'PEARL WHITE', 'color_code' => 'WS', 'oem_name' => 'ZETA VX PLUS'])
            ->assertRedirect('/admin/vehicle/variant');
        $this->assertDatabaseHas('xlr8_vehicle_variant', ['id' => $whiteId, 'code' => '1ZT2NR1T9LVB1', 'color' => 'PEARL WHITE']);

        $this->delete("/admin/vehicle/variant/{$whiteId}")->assertSuccessful();
        $this->assertSoftDeleted('xlr8_vehicle_variant', ['id' => $whiteId]);
    }

    /** Before DEC-048 every multi-colour variant failed validation on save ("code already taken"). */
    public function test_an_existing_multi_colour_variant_row_can_be_edited(): void
    {
        $v = DB::table('xlr8_vehicle_variant as v')->whereNull('v.deleted_at')->whereNotNull('v.color_code')
            ->whereExists(fn ($q) => $q->from('xlr8_vehicle_variant as s')->whereColumn('s.code', 'v.code')->whereColumn('s.id', '!=', 'v.id')->whereNull('s.deleted_at'))
            ->whereExists(fn ($q) => $q->from('xlr8_vehicle_model as m')->whereColumn('m.code', 'v.model_code'))
            ->whereExists(fn ($q) => $q->from('xlr8_vehicle_subsegment as ss')->whereColumn('ss.code', 'v.sub_segment_code'))
            ->first();
        if (! $v) {
            $this->markTestSkipped('No multi-colour variant with resolvable model/sub-segment.');
        }

        $this->put("/admin/vehicle/variant/{$v->id}", [
            'segment_code' => $v->segment_code, 'sub_segment_code' => $v->sub_segment_code, 'model_code' => $v->model_code,
            'code' => $v->code, 'color' => $v->color, 'color_code' => $v->color_code,
            'oem_name' => $v->oem_name, 'display_name' => 'Edited In Test', 'taxi_price' => $v->taxi_price ?: 'No', 'is_active' => 1,
        ])->assertRedirect('/admin/vehicle/variant')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('xlr8_vehicle_variant', ['id' => $v->id, 'code' => $v->code, 'display_name' => 'Edited In Test']);
    }
}
