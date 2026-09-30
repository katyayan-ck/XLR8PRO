<?php

namespace Tests\Feature\Vehicle;

use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleTrim;
use App\Services\Vehicle\Content\FeatureItemService;
use App\Services\Vehicle\Content\ModelSpecService;
use App\Services\Vehicle\Content\SpecItemService;
use App\Services\Vehicle\Content\TrimFeatureService;
use App\Services\Vehicle\Content\VehicleTrimService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-092 Phase 1: the vehicle-content entities — item codes derived from category + name, one value per (model, item)
 * and per (trim, feature) with the value spellings normalised, a trim record per variant code, and the media
 * collections for model images / brochure and trim / colour galleries.
 */
class VehicleContentEntitiesTest extends TestCase
{
    use DatabaseTransactions;

    private function variant(): Variant
    {
        return Variant::query()->whereNotNull('model_code')->first() ?? $this->markTestSkipped('No variant in the test copy.');
    }

    public function test_items_get_a_code_from_category_and_name_and_duplicates_a_suffix(): void
    {
        $specs = app(SpecItemService::class);

        $first = $specs->create(['category' => 'Engine Zq', 'name' => 'Displacement Zq']);
        $second = $specs->create(['category' => 'Engine Zq', 'name' => 'Displacement Zq']);

        $this->assertSame('ENGINE_ZQ_DISPLACEMENT_ZQ', $first->code);
        $this->assertSame('ENGINE_ZQ_DISPLACEMENT_ZQ_2', $second->code);
        $this->assertStringStartsWith('COMFORT', app(FeatureItemService::class)->create(['feature_group' => 'Comfort', 'name' => 'Central locking zq'])->code);
    }

    public function test_one_spec_value_per_model_and_item_with_not_applicable_normalised(): void
    {
        $model = $this->variant()->getAttribute('model_code');
        $item = app(SpecItemService::class)->create(['category' => 'Brakes Zq', 'name' => 'Front Zq']);
        $values = app(ModelSpecService::class);

        $values->upsert(['model_code' => $model, 'spec_item_code' => $item->code, 'value' => 'DISC']);
        $row = $values->upsert(['model_code' => $model, 'spec_item_code' => $item->code, 'value' => '-NA-']);

        $this->assertSame('N/A', $row->fresh()->value);
        $this->assertSame(1, VehicleModel::query()->where('code', $model)->first()->specs()->where('spec_item_code', $item->code)->count());
    }

    public function test_trim_features_normalise_yes_and_no_and_need_a_real_variant_code(): void
    {
        $variant = $this->variant();
        $item = app(FeatureItemService::class)->create(['feature_group' => 'Safety Zq', 'name' => 'Airbags Zq']);
        $features = app(TrimFeatureService::class);

        $this->assertSame('Yes', $features->upsert(['variant_code' => $variant->code, 'feature_item_code' => $item->code, 'value' => 'YES'])->value);
        $this->assertSame('No', $features->upsert(['variant_code' => $variant->code, 'feature_item_code' => $item->code, 'value' => '---'])->fresh()->value);
        $this->assertSame('2 airbags', TrimFeatureService::normaliseValue('2 airbags'));

        $this->expectException(ValidationException::class);
        $features->create(['variant_code' => 'NO-SUCH-VARIANT', 'feature_item_code' => $item->code, 'value' => 'Yes']);
    }

    public function test_a_trim_is_created_once_per_variant_code(): void
    {
        $variant = $this->variant();
        $trims = app(VehicleTrimService::class);

        $trim = $trims->forVariant(strtolower($variant->code));

        $this->assertSame($variant->model_code, $trim->model_code);
        $this->assertSame($trim->id, $trims->forVariant($variant->code)->id);
        $this->assertNull($trims->forVariant('NO-SUCH-VARIANT'));
    }

    public function test_media_collections_exist_for_model_trim_and_colour(): void
    {
        $model = new VehicleModel;
        $model->registerMediaCollections();
        $trim = new VehicleTrim;
        $trim->registerMediaCollections();
        $variant = new Variant;
        $variant->registerMediaCollections();

        $names = fn ($m) => collect($m->mediaCollections)->pluck('name')->all();
        $this->assertContains('images', $names($model));
        $this->assertContains('brochure', $names($model));
        $this->assertContains('gallery', $names($trim));
        $this->assertContains('gallery', $names($variant));
    }
}
