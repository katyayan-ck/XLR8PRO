<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\FeatureItem;
use App\Models\Vehicle\ModelSpec;
use App\Models\Vehicle\SpecItem;
use App\Models\Vehicle\TrimFeature;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleTrim;
use App\Support\Result;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Vehicle content screens (DEC-092 Phase 2): what the model page (images, brochure, specifications, trims) and the trim
 * page (features, gallery) show, and their saves — values through the entity services, files through the media library
 * (a colour shows its own gallery + the trim's). Removing a file checks it belongs to that model / trim.
 */
final class VehicleContentService
{
    public function __construct(
        private readonly ModelSpecService $specs,
        private readonly TrimFeatureService $features,
        private readonly SpecItemService $specItems,
        private readonly FeatureItemService $featureItems,
        private readonly VehicleTrimService $trims,
    ) {}

    /**
     * Models with what they already have, grouped by segment.
     *
     * @return array<string, list<array{code: string, name: string, specs: int, images: int, brochure: bool, trims: int}>>
     */
    public function overview(): array
    {
        $models = VehicleModel::query()->where('is_active', true)->orderBy('segment_code')->orderBy('name')->get(['id', 'code', 'name', 'segment_code']);
        $specs = ModelSpec::query()->whereNotNull('value')->select('model_code', DB::raw('COUNT(*) as n'))->groupBy('model_code')->pluck('n', 'model_code');
        $trims = Variant::query()->whereNotNull('model_code')->select('model_code', DB::raw('COUNT(DISTINCT code) as n'))->groupBy('model_code')->pluck('n', 'model_code');
        $media = Media::query()->where('model_type', VehicleModel::class)->whereIn('collection_name', ['images', 'brochure'])
            ->get(['model_id', 'collection_name'])->groupBy('model_id');

        $out = [];
        foreach ($models as $m) {
            $own = $media->get($m->id, collect());
            $out[(string) $m->segment_code][] = [
                'code' => (string) $m->code, 'name' => (string) $m->name, 'specs' => (int) ($specs[$m->code] ?? 0),
                'images' => $own->where('collection_name', 'images')->count(), 'brochure' => $own->where('collection_name', 'brochure')->isNotEmpty(),
                'trims' => (int) ($trims[$m->code] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * The model page.
     *
     * @return array{categories: array<string, list<array{code: string, name: string, unit: ?string, value: ?string}>>, images: list<array<string, mixed>>, brochure: ?array<string, mixed>, trims: list<array{code: string, name: string, colours: int, features: int}>}
     */
    public function modelSheet(VehicleModel $model): array
    {
        $values = ModelSpec::query()->where('model_code', $model->code)->pluck('value', 'spec_item_code');
        $categories = [];
        foreach (SpecItem::query()->where('is_active', true)->orderBy('category')->orderBy('sort')->orderBy('name')->get() as $item) {
            $categories[(string) $item->category][] = ['code' => (string) $item->code, 'name' => (string) $item->name,
                'unit' => $item->unit, 'value' => $values[$item->code] ?? null];
        }

        $featureCounts = TrimFeature::query()->whereIn('variant_code', Variant::query()->where('model_code', $model->code)->select('code'))
            ->whereNotNull('value')->select('variant_code', DB::raw('COUNT(*) as n'))->groupBy('variant_code')->pluck('n', 'variant_code');
        $trims = Variant::query()->where('model_code', $model->code)
            ->select('code', DB::raw('MAX(display_name) as name'), DB::raw('COUNT(*) as colours'))->groupBy('code')->orderBy('name')->get()
            ->map(fn ($v) => ['code' => (string) $v->code, 'name' => (string) $v->getAttribute('name'), 'colours' => (int) $v->getAttribute('colours'),
                'features' => (int) ($featureCounts[$v->code] ?? 0)])->all();

        $brochure = $model->getFirstMedia('brochure');

        return ['categories' => $categories, 'images' => $this->mediaList($model->getMedia('images')),
            'brochure' => $brochure ? $this->mediaItem($brochure) : null, 'trims' => $trims];
    }

    /**
     * Save the specification values of a model (blank clears). Only changed values are written.
     *
     * @param  array<string, mixed>  $values  item code => value
     */
    public function saveModelSpecs(VehicleModel $model, array $values): int
    {
        $current = ModelSpec::query()->where('model_code', $model->code)->pluck('value', 'spec_item_code');
        $changed = 0;
        foreach ($values as $itemCode => $value) {
            $new = ModelSpecService::normaliseValue($value);
            if (($current[$itemCode] ?? null) === $new || ($new === null && ! $current->has($itemCode))) {
                continue;
            }
            $this->specs->upsert(['model_code' => $model->code, 'spec_item_code' => (string) $itemCode, 'value' => $new]);
            $changed++;
        }

        return $changed;
    }

    public function addSpecItem(string $category, string $name, ?string $unit = null): SpecItem
    {
        /** @var SpecItem $item */
        $item = $this->specItems->create(['category' => $category, 'name' => $name, 'unit' => $unit]);

        return $item;
    }

    public function addFeatureItem(string $group, string $name): FeatureItem
    {
        /** @var FeatureItem $item */
        $item = $this->featureItems->create(['feature_group' => $group, 'name' => $name]);

        return $item;
    }

    /** @param  list<UploadedFile>  $files */
    public function addModelImages(VehicleModel $model, array $files): int
    {
        foreach ($files as $file) {
            $this->attach($model, $file, 'images', 'images');
        }

        return count($files);
    }

    public function setBrochure(VehicleModel $model, UploadedFile $file): void
    {
        $this->attach($model, $file, 'brochure', 'brochure');   // single file: replaces the old one
    }

    public function removeModelMedia(VehicleModel $model, int $mediaId): Result
    {
        $media = $model->media()->whereKey($mediaId)->whereIn('collection_name', ['images', 'brochure'])->first();
        if ($media === null) {
            return Result::fail('RESOURCE_NOT_FOUND', __('errors.RESOURCE_NOT_FOUND'));
        }
        $media->delete();

        return Result::ok();
    }

    /**
     * The trim page: feature groups with values, colours and the gallery by level.
     *
     * @return array{name: string, model: ?VehicleModel, groups: array<string, list<array{code: string, name: string, value: ?string}>>, colours: list<array{code: string, name: string}>, gallery: array{trim: list<array<string, mixed>>, colours: array<string, list<array<string, mixed>>>}}
     */
    public function trimSheet(VehicleTrim $trim): array
    {
        $values = TrimFeature::query()->where('variant_code', $trim->variant_code)->pluck('value', 'feature_item_code');
        $groups = [];
        foreach (FeatureItem::query()->where('is_active', true)->orderBy('feature_group')->orderBy('sort')->orderBy('name')->get() as $item) {
            $groups[(string) $item->feature_group][] = ['code' => (string) $item->code, 'name' => (string) $item->name, 'value' => $values[$item->code] ?? null];
        }

        $rows = Variant::query()->where('code', $trim->variant_code)->orderBy('color')->get();
        $colours = [];
        $gallery = ['trim' => $this->mediaList($trim->getMedia('gallery')), 'colours' => []];
        foreach ($rows as $row) {
            $code = (string) ($row->getAttribute('color_code') ?: $row->getKey());
            $colours[] = ['code' => $code, 'name' => (string) ($row->getAttribute('color') ?: $code)];
            $gallery['colours'][$code] = $this->mediaList($row->getMedia('gallery'));
        }

        return ['name' => (string) ($rows->first()?->getAttribute('display_name') ?? $trim->variant_code),
            'model' => VehicleModel::query()->where('code', $trim->model_code)->first(),
            'groups' => $groups, 'colours' => $colours, 'gallery' => $gallery];
    }

    /**
     * Save a trim's feature values (blank clears). Only changed values are written.
     *
     * @param  array<string, mixed>  $values  item code => value
     */
    public function saveTrimFeatures(VehicleTrim $trim, array $values): int
    {
        $current = TrimFeature::query()->where('variant_code', $trim->variant_code)->pluck('value', 'feature_item_code');
        $changed = 0;
        foreach ($values as $itemCode => $value) {
            $new = TrimFeatureService::normaliseValue($value);
            if (($current[$itemCode] ?? null) === $new || ($new === null && ! $current->has($itemCode))) {
                continue;
            }
            $this->features->upsert(['variant_code' => $trim->variant_code, 'feature_item_code' => (string) $itemCode, 'value' => $new]);
            $changed++;
        }

        return $changed;
    }

    /**
     * Add gallery images at a level: `trim` (all colours) or a colour code of this trim (owner decision DEC-092 #2).
     *
     * @param  list<UploadedFile>  $files
     */
    public function addGalleryImages(VehicleTrim $trim, string $level, array $files): Result
    {
        $owner = $level === 'trim' ? $trim : $this->colourRow($trim, $level);
        if ($owner === null) {
            return Result::fail('RESOURCE_NOT_FOUND', __('errors.RESOURCE_NOT_FOUND'));
        }
        foreach ($files as $file) {
            $this->attach($owner, $file, 'gallery', 'images');
        }

        return Result::ok(['added' => count($files)]);
    }

    public function removeGalleryImage(VehicleTrim $trim, int $mediaId): Result
    {
        $media = Media::query()->whereKey($mediaId)->where('collection_name', 'gallery')->first();
        $owned = $media !== null && (
            ($media->model_type === VehicleTrim::class && (int) $media->model_id === (int) $trim->getKey())
            || ($media->model_type === Variant::class && Variant::query()->whereKey($media->model_id)->where('code', $trim->variant_code)->exists())
        );
        if (! $owned) {
            return Result::fail('RESOURCE_NOT_FOUND', __('errors.RESOURCE_NOT_FOUND'));
        }
        $media->delete();

        return Result::ok();
    }

    public function trim(string $variantCode): ?VehicleTrim
    {
        return $this->trims->forVariant($variantCode);
    }

    /**
     * Add a file to a collection; a file the collection refuses (type / content) is a validation error on $field, not a 500.
     *
     * @throws ValidationException
     */
    private function attach(HasMedia $owner, UploadedFile $file, string $collection, string $field): void
    {
        try {
            $owner->addMedia($file)->toMediaCollection($collection);
        } catch (FileCannotBeAdded $e) {
            throw ValidationException::withMessages([$field => $file->getClientOriginalName().': '.$e->getMessage()]);
        }
    }

    private function colourRow(VehicleTrim $trim, string $colourCode): ?Variant
    {
        return Variant::query()->where('code', $trim->variant_code)
            ->where(fn ($q) => $q->where('color_code', $colourCode)->orWhere('id', ctype_digit($colourCode) ? (int) $colourCode : 0))->first();
    }

    /**
     * @param  iterable<Media>  $items
     * @return list<array<string, mixed>>
     */
    private function mediaList(iterable $items): array
    {
        $out = [];
        foreach ($items as $media) {
            $out[] = $this->mediaItem($media);
        }

        return $out;
    }

    /** @return array{id: int, name: string, url: string, thumb: string, mime: ?string} */
    private function mediaItem(Media $media): array
    {
        return ['id' => (int) $media->getKey(), 'name' => (string) $media->file_name, 'url' => $media->getUrl(),
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(), 'mime' => $media->mime_type];
    }
}
