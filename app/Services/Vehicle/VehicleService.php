<?php

/**
 * Path: app/Services/Vehicle/VehicleService.php
 *
 * Single source of truth for Segment → SubSegment → Model → Variant.
 *
 * Price List stub (new only):
 *   OEM Code    → variant.code (full)
 *                 last 2 chars → color, color_code, color_name
 *   OEM Model   → model.code, model.name, model.oem_name
 *                 variant.model_code
 *   OEM Variant → variant.oem_name, variant.custom_name
 *   All strings trim + UPPERCASE.
 *
 * Completeness (Vehicle Info only) — see isComplete().
 *
 * Every write goes through the entity services (Segment/SubSegment/VehicleModel/Variant/Keyvalue,
 * DEC-050/058), so a price-list stub or a Vehicle Info row follows the same field rules as the screens.
 */

namespace App\Services\Vehicle;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use App\Support\Entity\EntityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VehicleService
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUS_DISCONTINUED = 'DISCONTINUED';

    public const STATUS_ALL = 'ALL';

    public function norm(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    public function colorFromOemCode(string $oemCode): string
    {
        $code = $this->norm($oemCode);

        return strlen($code) >= 2 ? substr($code, -2) : $code;
    }

    /**
     * First token after "PRICE LIST" in the sheet title.
     * "Price List PV" → PV, "Price List LMM TZU" → LMM
     */
    public function segmentFromSheetTitle(string $sheetTitle): string
    {
        $title = $this->norm($sheetTitle);
        $title = preg_replace('/^PRICE\s*LIST\s*/', '', $title) ?? $title;
        $parts = preg_split('/\s+/', trim($title)) ?: [];
        $token = $parts[0] ?? 'PV';

        $map = config('pricing.sheet_segment_map', []);

        return $this->norm($map[$token] ?? $token);
    }

    public function findOrCreateSegment(string $code, ?string $name = null, ?int $userId = null): Segment
    {
        $segments = app(SegmentService::class);
        $code = (string) $segments->normalise(['code' => $code])['code'];

        return Segment::query()->where('code', $code)->first()
            ?? $segments->create(['code' => $code, 'name' => $name ?: $code, 'is_active' => true]);
    }

    public function findOrCreateSubSegment(string $segmentCode, ?string $code = null, ?string $name = null, ?int $userId = null): SubSegment
    {
        $segment = $this->findOrCreateSegment($segmentCode, null, $userId);
        $code = $this->norm($code ?: $segment->code);

        $candidates = $this->codeCandidates(SubSegmentService::class, $code);
        $row = SubSegment::query()
            ->where('segment_code', $segment->code)
            ->where(function ($q) use ($candidates, $code) {
                $q->whereIn('code', $candidates)
                    ->orWhereRaw('UPPER(REPLACE(name, " ", "")) = ?', [preg_replace('/[^A-Z0-9]/', '', $code)]);
            })
            ->first();

        return $row ?? app(SubSegmentService::class)->create([
            'segment_code' => $segment->code,
            'code' => $candidates[0],
            'name' => $name ?: $code,
            'is_active' => true,
        ]);
    }

    /**
     * OEM Model → model.code / name / oem_name. Creates only if missing (through VehicleModelService).
     */
    public function findOrCreateModel(
        string $oemModel,
        string $segmentCode,
        ?string $subSegmentCode = null,
        ?int $userId = null
    ): VehicleModel {
        $oemModel = $this->norm($oemModel);
        $subSegment = $this->findOrCreateSubSegment($segmentCode, $subSegmentCode ?: $segmentCode, $subSegmentCode ?: $segmentCode, $userId);

        $candidates = $this->modelCodeCandidates($oemModel);
        $model = VehicleModel::query()
            ->where(function ($q) use ($candidates, $oemModel) {
                $q->whereIn('code', $candidates)
                    ->orWhereRaw('UPPER(TRIM(name)) = ?', [$oemModel])
                    ->orWhereRaw('UPPER(TRIM(oem_name)) = ?', [$oemModel]);
            })
            ->first();

        return $model ?? app(VehicleModelService::class)->create([
            'segment_code' => $subSegment->segment_code,
            'sub_segment_code' => $subSegment->code,
            'code' => $candidates[0],
            'name' => $oemModel,
            'oem_name' => $oemModel,
            'is_active' => true,
        ]);
    }

    /**
     * Codes a model may be stored under: the canonical code first (THAR-ROXX, DEC-049), then the
     * legacy spellings (THAR ROXX, THARROXX) still found in older rows.
     *
     * @return list<string>
     */
    public function modelCodeCandidates(string $oemModel): array
    {
        return $this->codeCandidates(VehicleModelService::class, $oemModel);
    }

    /**
     * @param  class-string<EntityService>  $service
     * @return list<string>
     */
    private function codeCandidates(string $service, string $value): array
    {
        $upper = $this->norm($value);
        $canonical = (string) (app($service)->normalise(['code' => $upper])['code'] ?? $upper);
        $compact = preg_replace('/[^A-Z0-9]/', '', $upper) ?: $upper;

        return array_values(array_unique(array_filter([$canonical, $upper, $compact])));
    }

    /**
     * Price List detect: create stub variant only if OEM Code does not exist.
     *
     * @return array{variant: Variant, created: bool, model: VehicleModel}
     */
    public function createStubFromPriceList(
        string $oemCode,
        string $oemModel,
        string $oemVariant,
        string $sheetTitle,
        ?int $userId = null
    ): array {
        $oemCode = $this->norm($oemCode);
        $oemModel = $this->norm($oemModel);
        $oemVariant = $this->norm($oemVariant);
        $color = $this->colorFromOemCode($oemCode);
        $segmentCode = $this->segmentFromSheetTitle($sheetTitle);

        $existing = Variant::query()->where('code', $oemCode)->first();
        if ($existing) {
            return [
                'variant' => $existing,
                'created' => false,
                'model' => $existing->vehicleModel ?? $this->findOrCreateModel(
                    $existing->model_code,
                    $existing->segment_code,
                    $existing->sub_segment_code,
                    $userId
                ),
            ];
        }

        $model = $this->findOrCreateModel($oemModel, $segmentCode, $segmentCode, $userId);

        // Written through VariantService (DEC-050): same field rules as the screen and the vehicle import.
        $variant = app(VariantService::class)->create([
            'segment_code' => $model->segment_code,
            'sub_segment_code' => $model->sub_segment_code,
            'model_code' => $model->code,
            'code' => $oemCode,
            'oem_name' => $oemVariant !== '' ? $oemVariant : $oemModel,
            'custom_name' => $oemVariant !== '' ? $oemVariant : null,
            'color' => $color,
            'color_code' => $color,
            'taxi_price' => 'NO',
            'is_csd' => $segmentCode === 'CSD',
            'is_active' => false,
        ]);

        Log::info('[VehicleService] stub created', [
            'oem_code' => $oemCode,
            'oem_model' => $oemModel,
            'segment' => $segmentCode,
        ]);

        return ['variant' => $variant, 'created' => true, 'model' => $model];
    }

    /**
     * Apply Vehicle Info row onto an existing variant (matched by OEM Code). The model and the
     * variant are updated through their entity services; a value that breaks a field rule rejects
     * the row (the importer reports it).
     *
     * @return array{complete:bool,missing:array,active:bool,variant:Variant}
     */
    public function applyVehicleInfo(Variant $variant, array $row, ?int $userId = null): array
    {
        $segmentCode = $this->norm($row['segment'] ?? $variant->segment_code);
        $subCode = $this->norm($row['sub_segment'] ?? $variant->sub_segment_code ?: $segmentCode);
        $changes = [];

        if ($segmentCode !== '') {
            $subSegment = $this->findOrCreateSubSegment($segmentCode, $subCode, $subCode, $userId);
            $changes['segment_code'] = $subSegment->segment_code;
            $changes['sub_segment_code'] = $subSegment->code;
        }

        if (! empty($row['custom_model'])) {
            $model = null;
            if ($variant->model_code) {
                $model = VehicleModel::query()->whereIn('code', $this->modelCodeCandidates($variant->model_code))->first();
            }
            if (! $model && ! empty($row['oem_model'])) {
                $model = $this->findOrCreateModel(
                    (string) $row['oem_model'],
                    $segmentCode ?: ($variant->segment_code ?? 'PV'),
                    $subCode ?: $variant->sub_segment_code,
                    $userId
                );
                $changes['model_code'] = $model->code;
            }
            if ($model) {
                $modelChanges = ['name' => $row['custom_model']];
                if (empty($model->oem_name) && ! empty($row['oem_model'])) {
                    $modelChanges['oem_name'] = $row['oem_model'];
                }
                if (isset($changes['segment_code'])) {
                    $modelChanges['segment_code'] = $changes['segment_code'];
                    $modelChanges['sub_segment_code'] = $changes['sub_segment_code'];
                }
                $model = app(VehicleModelService::class)->update($model, $modelChanges);
                $variant->setRelation('vehicleModel', $model);
            }
        }

        foreach ([
            'custom_variant' => 'custom_name',
            'display_name' => 'display_name',
            'colour_name' => 'color',
            'taxi_price' => 'taxi_price',
            'seating' => 'seating_capacity',
            'wheels' => 'wheels',
            'transmission' => 'transmission',
            'drivetrain' => 'drivetrain',
            'cc' => 'cc_capacity',
            'motor' => 'motor',
            'gvw' => 'gvw',
            'gst_percent' => 'gst_percent',
            'shield_pack' => 'shield_pack',
        ] as $sheetField => $column) {
            if (isset($row[$sheetField]) && $row[$sheetField] !== '') {
                $changes[$column] = $row[$sheetField];
            }
        }

        foreach (['fuel_type_id' => ['FUEL_TYPE', 'fuel'], 'permit_id' => ['PERMIT', 'permit'], 'body_make_id' => ['BODY_MAKE', 'body_make'], 'body_type_id' => ['BODY_TYPE', 'body_type']] as $column => [$keyword, $sheetField]) {
            $id = $this->kkvId($keyword, $row[$sheetField] ?? null, true);
            if ($id !== null) {
                $changes[$column] = $id;
            }
        }

        // Completeness is judged on the values as they will be stored (the service's formats).
        $variants = app(VariantService::class);
        $probe = clone $variant;
        $probe->forceFill(array_intersect_key($variants->normalise($changes), $changes));

        $status = $this->norm($row['status'] ?? '');
        $missing = $this->missingFields($probe);
        $complete = $missing === [];

        if (in_array($status, [self::STATUS_ACTIVE, '1', 'Y', 'YES'], true)) {
            $changes['is_active'] = $complete;
            if ($complete) {
                $changes['status_id'] = $this->kkvId('VEHICLE_STATUS', 'ACTIVE') ?? $variant->status_id;
            }
        } elseif (in_array($status, [self::STATUS_INACTIVE, '0', 'N', 'NO'], true)) {
            $changes['is_active'] = false;
            $changes['status_id'] = $this->kkvId('VEHICLE_STATUS', 'INACTIVE') ?? $variant->status_id;
        } elseif ($status === self::STATUS_DISCONTINUED) {
            $changes['is_active'] = false;
            $changes['status_id'] = $this->kkvId('VEHICLE_STATUS', 'DISCONTINUED') ?? $variant->status_id;
        } else {
            $changes['is_active'] = $complete ? (bool) $variant->is_active : false;
        }

        $variant = $variants->update($variant, $changes);

        return [
            'complete' => $complete,
            'missing' => $missing,
            'active' => (bool) $variant->is_active,
            'variant' => $variant,
        ];
    }

    public function isComplete(Variant $variant): bool
    {
        return $this->missingFields($variant) === [];
    }

    /**
     * @return string[]
     */
    public function missingFields(Variant $variant): array
    {
        $missing = [];

        $checks = [
            'segment_code' => $variant->segment_code,
            'sub_segment_code' => $variant->sub_segment_code,
            'fuel_type_id' => $variant->fuel_type_id,
            'seating_capacity' => $variant->seating_capacity,
            'wheels' => $variant->wheels,
            'transmission' => $variant->transmission,
            'drivetrain' => $variant->drivetrain,
            'body_make_id' => $variant->body_make_id,
            'body_type_id' => $variant->body_type_id,
            'gst_percent' => $variant->gst_percent,
            'permit_id' => $variant->permit_id,
            'taxi_price' => $variant->taxi_price,
            'custom_name' => $variant->custom_name,
            'display_name' => $variant->display_name,
            'color' => $variant->color,
        ];

        foreach ($checks as $field => $value) {
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }

        $modelName = null;
        if ($variant->relationLoaded('vehicleModel')) {
            $modelName = $variant->vehicleModel?->name ?: $variant->vehicleModel?->oem_name;
        } elseif ($variant->model_code) {
            $model = VehicleModel::query()
                ->whereIn('code', $this->modelCodeCandidates($variant->model_code))
                ->first();
            $modelName = $model?->name ?: $model?->oem_name;
        }

        if ($modelName === null || $modelName === '') {
            $missing[] = 'custom_model';
        }

        $permit = $this->permitCode($variant);
        $fuel = $this->fuelCode($variant);
        $isElectric = $fuel !== null && (str_contains($fuel, 'ELECTRIC') || $fuel === 'EV' || str_contains($fuel, 'BEV'));
        $wheels = (int) ($variant->wheels ?? 0);
        $isPassenger = $permit !== null && str_contains($permit, 'PASSENGER');
        $isPrivate = $permit === 'PRIVATE';
        $isGoods = $permit === 'GOODS';
        $is3w = $wheels === 3;
        $is4w = $wheels >= 4;

        if ($isGoods) {
            if ($variant->gvw === null || $variant->gvw === '') {
                $missing[] = 'gvw';
            }
        } elseif ($isPrivate || ($isPassenger && $is4w)) {
            if ($isElectric) {
                if ($variant->motor === null || $variant->motor === '') {
                    $missing[] = 'motor';
                }
            } elseif ($variant->cc_capacity === null || $variant->cc_capacity === '') {
                $missing[] = 'cc_capacity';
            }
        } elseif ($isPassenger && $is3w) {
            // no CC / Motor / GVW required
        }

        return $missing;
    }

    public function permitCode(Variant $variant): ?string
    {
        if (! $variant->permit_id) {
            return null;
        }
        $row = Keyvalue::query()->find($variant->permit_id);

        return $row ? $this->norm($row->code ?? $row->value) : null;
    }

    public function fuelCode(Variant $variant): ?string
    {
        if (! $variant->fuel_type_id) {
            return null;
        }
        $row = Keyvalue::query()->find($variant->fuel_type_id);

        return $row ? $this->norm($row->code ?? $row->value) : null;
    }

    public function kkvId(string $keyword, mixed $value, bool $create = false): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $code = $this->norm((string) $value);
        $compact = preg_replace('/[^A-Z0-9]/', '', $code) ?: $code;
        $keywords = array_unique([$keyword, str_replace('_', '', $keyword)]);

        foreach ($keywords as $kw) {
            foreach ([$code, $compact] as $try) {
                try {
                    $id = KeywordValueService::getValueId($kw, $try, false);
                    if ($id) {
                        return (int) $id;
                    }
                } catch (\Throwable) {
                    // fall through to table lookup
                }
            }
        }

        $row = Keyvalue::query()
            ->whereIn('keyword_code', array_map(fn ($k) => $this->norm($k), $keywords))
            ->where(function ($q) use ($code, $compact) {
                $q->whereRaw('UPPER(code) = ?', [$code])
                    ->orWhereRaw('UPPER(code) = ?', [$compact])
                    ->orWhereRaw('UPPER(value) = ?', [$code])
                    ->orWhereRaw('UPPER(REPLACE(value," ","")) = ?', [$compact]);
            })
            ->first();

        if ($row) {
            return (int) $row->id;
        }

        if (! $create) {
            return null;
        }

        try {
            $row = app(KeyvalueService::class)->create([
                'keyword_code' => $this->norm($keyword),
                'code' => $compact,
                'value' => $code,
                'is_active' => true,
            ]);

            return (int) $row->id;
        } catch (\Throwable) {
            $row = Keyvalue::query()
                ->where('keyword_code', $this->norm($keyword))
                ->where(function ($q) use ($code, $compact) {
                    $q->whereRaw('UPPER(code) = ?', [$compact])
                        ->orWhereRaw('UPPER(value) = ?', [$code]);
                })
                ->first();

            return $row ? (int) $row->id : null;
        }
    }

    public function segments(): Collection
    {
        return Segment::query()->orderBy('name')->get();
    }

    public function subSegments(?string $segmentCode = null): Collection
    {
        return SubSegment::query()
            ->when($segmentCode, fn ($q) => $q->where('segment_code', $this->norm($segmentCode)))
            ->orderBy('name')
            ->get();
    }

    public function models(?string $segmentCode = null, ?string $subSegmentCode = null): Collection
    {
        return VehicleModel::query()
            ->when($segmentCode, fn ($q) => $q->where('segment_code', $this->norm($segmentCode)))
            ->when($subSegmentCode, fn ($q) => $q->where('sub_segment_code', $this->norm($subSegmentCode)))
            ->orderBy('name')
            ->get();
    }

    public function variantsOf(
        ?string $segmentCode = null,
        ?string $subSegmentCode = null,
        ?string $modelCode = null,
        string $status = self::STATUS_ACTIVE
    ): Collection {
        $q = Variant::query()
            ->when($segmentCode, fn ($q) => $q->where('segment_code', $this->norm($segmentCode)))
            ->when($subSegmentCode, fn ($q) => $q->where('sub_segment_code', $this->norm($subSegmentCode)))
            ->when($modelCode, fn ($q) => $q->where('model_code', $this->norm($modelCode)));

        $status = $this->norm($status);
        if ($status === self::STATUS_ACTIVE) {
            $q->where('is_active', true);
        } elseif ($status === self::STATUS_INACTIVE) {
            $q->where('is_active', false);
        }

        return $q->orderBy('model_code')->orderBy('code')->get();
    }

    public function descendantsOf(string $level, string $code, string $status = self::STATUS_ACTIVE): Collection
    {
        $code = $this->norm($code);

        return match ($this->norm($level)) {
            'SEGMENT' => $this->variantsOf($code, null, null, $status),
            'SUBSEGMENT', 'SUB_SEGMENT' => $this->variantsOf(null, $code, null, $status),
            'MODEL' => $this->variantsOf(null, null, $code, $status),
            default => collect(),
        };
    }

    /**
     * Dropdown options (DEC-060, replaced CommonHelper/XpricingHelper). Eloquent rows, so callers
     * may read `$row->code` or `$row['code']` as before.
     */
    public function segmentOptions(): Collection
    {
        return Segment::query()->select('code', 'name')->where('is_active', true)->orderBy('name')->get();
    }

    /** Models of a segment (all segments when null); `$activeOnly = false` includes inactive ones. */
    public function modelOptions(?string $segmentCode = null, bool $activeOnly = true): Collection
    {
        return VehicleModel::query()
            ->select('id', 'code', 'name')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($segmentCode !== null, fn ($q) => $q->where('segment_code', $this->norm($segmentCode)))
            ->orderBy('name')
            ->get();
    }

    /** Active models of exactly this segment; none when no segment is chosen yet (dependent dropdowns). */
    public function modelOptionsFor(?string $segmentCode): Collection
    {
        return trim((string) $segmentCode) === '' ? collect() : $this->modelOptions($segmentCode);
    }

    /** Variant rows of a model: `id, code, name` (custom name), `seating_capacity`. */
    public function variantOptions(?string $modelCode): Collection
    {
        return Variant::query()
            ->select('id', 'code', 'custom_name as name', 'seating_capacity')
            ->where('is_active', true)
            ->where('model_code', $this->norm($modelCode))
            ->orderBy('custom_name')
            ->get();
    }

    /**
     * Colours available for a variant: its sibling colour rows (one variant row per colour,
     * DEC-048) as `code` (= colour code), `name` (= colour), `variant_code`. Replaces the retired
     * colour table, which new imports no longer fill.
     */
    public function colorOptions(?string $variantCode): Collection
    {
        $variant = Variant::query()->where('code', $this->norm($variantCode))->first(['model_code', 'oem_name']);
        if (! $variant) {
            return collect();
        }

        return Variant::query()
            ->select('color_code as code', 'color as name', 'code as variant_code')
            ->where('is_active', true)
            ->where('model_code', $variant->model_code)
            ->where('oem_name', $variant->oem_name)
            ->whereNotNull('color_code')
            ->orderBy('color')
            ->get()
            ->unique('code')
            ->values();
    }

    public function colorsOfVariant(string $variantOemCode): Collection
    {
        $v = Variant::query()->where('code', $this->norm($variantOemCode))->first();
        if (! $v) {
            return collect();
        }

        return Variant::query()
            ->where('model_code', $v->model_code)
            ->where('oem_name', $v->oem_name)
            ->get(['code', 'color', 'color_code']);
    }

    public function colorsOfModel(string $oemModel): Collection
    {
        return Variant::query()
            ->where('model_code', $this->norm($oemModel))
            ->get(['code', 'color', 'color_code', 'oem_name']);
    }

    public function colorsOfSegment(string $segmentCode, ?string $subSegmentCode = null): Collection
    {
        return Variant::query()
            ->where('segment_code', $this->norm($segmentCode))
            ->when($subSegmentCode, fn ($q) => $q->where('sub_segment_code', $this->norm($subSegmentCode)))
            ->get(['code', 'model_code', 'color', 'color_code']);
    }

    public function findByOemCode(string $oemCode): ?Variant
    {
        return Variant::query()->where('code', $this->norm($oemCode))->first();
    }

    public function copySpecifications(string $fromOemCode, string $toOemCode, ?int $userId = null): ?Variant
    {
        $from = $this->findByOemCode($fromOemCode);
        $to = $this->findByOemCode($toOemCode);
        if (! $from || ! $to) {
            return null;
        }

        $specs = $from->only([
            'permit_id', 'taxi_price', 'fuel_type_id', 'seating_capacity', 'wheels',
            'gvw', 'cc_capacity', 'motor', 'transmission', 'drivetrain',
            'body_type_id', 'body_make_id', 'gst_percent', 'shield_pack',
        ]);

        return app(VariantService::class)->update($to, $specs);
    }
}
