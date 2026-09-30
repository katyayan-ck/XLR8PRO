<?php

namespace Tests\Feature\Vehicle;

use App\Models\IAM\DeviceSession;
use App\Models\User;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Content\CompareService;
use App\Services\Vehicle\Content\FeatureItemService;
use App\Services\Vehicle\Content\ModelSpecService;
use App\Services\Vehicle\Content\SpecItemService;
use App\Services\Vehicle\Content\TrimFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-092 Phase 4: compare — trims of one model by features and models of one segment by specifications (other
 * segments refused), differences flagged / filtered, 2–6 vehicles; admin screen and app API behind VEH_CMPR_VIEW.
 */
class VehicleCompareTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: string, 1: string, 2: string} model code + two trim codes */
    private function twoTrims(): array
    {
        $model = DB::table('xlr8_vehicle_variant')->whereNull('deleted_at')->whereNotNull('model_code')
            ->select('model_code')->groupBy('model_code')->havingRaw('COUNT(DISTINCT code) >= 2')->value('model_code')
            ?? $this->markTestSkipped('No model with two trims in the test copy.');
        $codes = Variant::query()->where('model_code', $model)->distinct()->limit(2)->pluck('code')->all();

        return [$model, $codes[0], $codes[1]];
    }

    /** @return array{0: string, 1: string} two models of the same segment */
    private function twoModels(): array
    {
        $segment = VehicleModel::query()->select('segment_code')->groupBy('segment_code')->havingRaw('COUNT(*) >= 2')->value('segment_code')
            ?? $this->markTestSkipped('No segment with two models.');
        $codes = VehicleModel::query()->where('segment_code', $segment)->limit(2)->pluck('code')->all();

        return [$codes[0], $codes[1]];
    }

    public function test_trims_compare_by_features_with_differences_flagged_and_filtered(): void
    {
        [$model, $a, $b] = $this->twoTrims();
        $same = app(FeatureItemService::class)->create(['feature_group' => 'Safety Zq', 'name' => 'ABS Zq']);
        $diff = app(FeatureItemService::class)->create(['feature_group' => 'Safety Zq', 'name' => 'Airbags Zq']);
        $features = app(TrimFeatureService::class);
        foreach ([$a, $b] as $code) {
            $features->upsert(['variant_code' => $code, 'feature_item_code' => $same->code, 'value' => 'Yes']);
        }
        $features->upsert(['variant_code' => $a, 'feature_item_code' => $diff->code, 'value' => '2']);
        $features->upsert(['variant_code' => $b, 'feature_item_code' => $diff->code, 'value' => '6']);

        $all = app(CompareService::class)->variants($model, [$a, $b]);
        $rows = collect($all->data['groups']['Safety Zq'])->keyBy('label');
        $this->assertFalse($rows['ABS Zq']['differs']);
        $this->assertTrue($rows['Airbags Zq']['differs']);

        $onlyDiff = app(CompareService::class)->variants($model, [$a, $b], true);
        $this->assertSame(['Airbags Zq'], collect($onlyDiff->data['groups']['Safety Zq'])->pluck('label')->all());
    }

    public function test_models_compare_only_within_one_segment_and_need_two_to_six(): void
    {
        [$m1, $m2] = $this->twoModels();
        $item = app(SpecItemService::class)->create(['category' => 'Engine Zq', 'name' => 'Power Zq', 'unit' => 'hp']);
        app(ModelSpecService::class)->upsert(['model_code' => $m1, 'spec_item_code' => $item->code, 'value' => '100']);

        $ok = app(CompareService::class)->models([$m1, $m2]);
        $this->assertTrue($ok->ok);
        $this->assertSame('Power Zq (hp)', $ok->data['groups']['Engine Zq'][0]['label']);

        $other = VehicleModel::query()->where('segment_code', '!=', VehicleModel::query()->where('code', $m1)->value('segment_code'))->value('code');
        if ($other) {
            $this->assertSame('VEHICLE_COMPARE_SEGMENT', app(CompareService::class)->models([$m1, $other])->code);
        }
        $this->assertSame('VEHICLE_COMPARE_SELECTION', app(CompareService::class)->models([$m1])->code);
    }

    public function test_the_admin_screen_compares_and_needs_the_permission(): void
    {
        [$model, $a, $b] = $this->twoTrims();
        $user = User::create(['username' => 'cmp_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->actingAs($user, 'backpack');
        $this->get(route('vehicle.compare'))->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('VEH_CMPR_VIEW', 'web'));
        $this->actingAs($user->fresh(), 'backpack');
        $this->get(route('vehicle.compare', ['mode' => 'variants', 'model' => $model, 'v' => [$a, $b]]))->assertOk()->assertSee('Compare vehicles');
    }

    public function test_the_app_api_returns_the_envelope_and_refuses_mixed_segments(): void
    {
        [$m1, $m2] = $this->twoModels();
        $user = User::create(['username' => 'cmpapi_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];

        $this->getJson('/api/v1/vehicles/compare/models?'.http_build_query(['models' => [$m1, $m2]]), $headers)->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('VEH_CMPR_VIEW', 'web'));
        $this->app['auth']->forgetGuards();   // the guard keeps the user (and its permissions) from the first call
        $this->getJson('/api/v1/vehicles/compare/models?'.http_build_query(['models' => [$m1, $m2]]), $headers)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.kind', 'models')->assertJsonCount(2, 'data.columns');

        $other = VehicleModel::query()->where('segment_code', '!=', VehicleModel::query()->where('code', $m1)->value('segment_code'))->value('code');
        if ($other) {
            $this->getJson('/api/v1/vehicles/compare/models?'.http_build_query(['models' => [$m1, $other]]), $headers)
                ->assertStatus(422)->assertJsonPath('code', 'VEHICLE_COMPARE_SEGMENT');
        }
    }
}
