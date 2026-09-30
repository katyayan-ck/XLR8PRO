<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle\ModelSpec;
use App\Models\Vehicle\SpecItem;
use App\Models\Vehicle\TrimFeature;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleTrim;
use App\Services\Vehicle\Content\FeatureItemService;
use App\Services\Vehicle\Content\SpecItemService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-092 Phase 2: the vehicle content screens — permissions, specification and feature saves (blank clears), new
 * master items, model images / brochure, and the gallery bound to the trim or to one colour (removal checks ownership).
 */
class VehicleContentScreensTest extends TestCase
{
    use DatabaseTransactions;

    private Variant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->variant = Variant::query()->whereNotNull('model_code')->whereNotNull('color_code')->first() ?? $this->markTestSkipped('No variant in the test copy.');
    }

    private function editor(string ...$permissions): User
    {
        $user = User::create(['username' => 'vc_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach ($permissions ?: ['VEH_CONT_VIEW', 'VEH_CONT_EDIT'] as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $user->fresh();
    }

    public function test_viewing_needs_view_and_saving_needs_edit(): void
    {
        $model = $this->variant->model_code;
        $this->actingAs($this->editor('VEH_CONT_VIEW'), 'backpack');

        $this->get(route('vehicle.content.index'))->assertOk()->assertSee($model);
        $this->get(route('vehicle.content.model', $model))->assertOk()->assertDontSee('Save specifications');
        $this->get(route('vehicle.content.trim', $this->variant->code))->assertOk();
        $this->put(route('vehicle.content.model.specs', $model), ['specs' => []])->assertForbidden();
    }

    public function test_others_cannot_open_the_screens(): void
    {
        $this->actingAs($this->editor('VEH_MDL_VIEW'), 'backpack');

        $this->get(route('vehicle.content.index'))->assertForbidden();
    }

    public function test_specifications_save_and_a_blank_clears(): void
    {
        $model = $this->variant->model_code;
        $item = app(SpecItemService::class)->create(['category' => 'Engine Zq', 'name' => 'Power Zq', 'unit' => 'hp']);
        $this->actingAs($this->editor(), 'backpack');

        $this->put(route('vehicle.content.model.specs', $model), ['specs' => [$item->code => '130']])->assertRedirect();
        $this->assertSame('130', ModelSpec::query()->where('model_code', $model)->where('spec_item_code', $item->code)->value('value'));

        $this->put(route('vehicle.content.model.specs', $model), ['specs' => [$item->code => '']]);
        $this->assertNull(ModelSpec::query()->where('model_code', $model)->where('spec_item_code', $item->code)->value('value'));

        $this->post(route('vehicle.content.model.spec-item', $model), ['category' => 'Tyres Zq', 'name' => 'Size Zq']);
        $this->assertTrue(SpecItem::query()->where('category', 'Tyres Zq')->exists());
    }

    public function test_trim_features_save_for_every_colour(): void
    {
        $item = app(FeatureItemService::class)->create(['feature_group' => 'Safety Zq', 'name' => 'ABS Zq']);
        $this->actingAs($this->editor(), 'backpack');

        $this->put(route('vehicle.content.trim.features', $this->variant->code), ['features' => [$item->code => 'yes']])->assertRedirect();

        $this->assertSame('Yes', TrimFeature::query()->where('variant_code', $this->variant->code)->where('feature_item_code', $item->code)->value('value'));
    }

    public function test_model_images_and_brochure_upload_and_remove(): void
    {
        $model = $this->variant->model_code;
        $this->actingAs($this->editor(), 'backpack');

        $this->post(route('vehicle.content.model.images', $model), ['images' => [UploadedFile::fake()->image('front.jpg')]])->assertSessionHasNoErrors();
        $this->post(route('vehicle.content.model.brochure', $model), ['brochure' => UploadedFile::fake()->createWithContent('b.pdf', '%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF')])->assertSessionHasNoErrors();
        $record = VehicleModel::query()->where('code', $model)->first();
        $this->assertCount(1, $record->getMedia('images'));
        $this->assertNotNull($record->getFirstMedia('brochure'));

        $this->delete(route('vehicle.content.model.media.remove', [$model, $record->getFirstMedia('images')->id]));
        $this->assertCount(0, $record->fresh()->getMedia('images'));

        // a file the collection refuses (an empty "PDF") is a form error, not a crash
        $this->from(route('vehicle.content.model', $model))
            ->post(route('vehicle.content.model.brochure', $model), ['brochure' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf')])
            ->assertSessionHasErrors('brochure');
    }

    public function test_gallery_images_bind_to_the_trim_or_one_colour_and_removal_checks_the_owner(): void
    {
        $code = $this->variant->code;
        $this->actingAs($this->editor(), 'backpack');

        $this->post(route('vehicle.content.trim.gallery', $code), ['level' => 'trim', 'images' => [UploadedFile::fake()->image('all.jpg')]])->assertSessionHasNoErrors();
        $this->post(route('vehicle.content.trim.gallery', $code), ['level' => $this->variant->color_code, 'images' => [UploadedFile::fake()->image('red.jpg')]])->assertSessionHasNoErrors();

        $trim = VehicleTrim::query()->where('variant_code', $code)->firstOrFail();
        $colour = Variant::query()->where('code', $code)->where('color_code', $this->variant->color_code)->first();
        $this->assertCount(1, $trim->getMedia('gallery'));
        $this->assertCount(1, $colour->getMedia('gallery'));

        $other = Variant::query()->where('code', '!=', $code)->whereNotNull('model_code')->value('code');
        if ($other) {
            $this->delete(route('vehicle.content.trim.gallery.remove', [$other, $trim->getFirstMedia('gallery')->id]));
            $this->assertCount(1, $trim->fresh()->getMedia('gallery'), 'another trim cannot remove this image');
        }
        $this->delete(route('vehicle.content.trim.gallery.remove', [$code, $colour->getFirstMedia('gallery')->id]));
        $this->assertCount(0, $colour->fresh()->getMedia('gallery'));
    }
}
