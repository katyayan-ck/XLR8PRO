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
 */

namespace App\Services\Vehicle;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\KeywordValueService;
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
        $code = $this->norm($code);
        $segment = Segment::query()->where('code', $code)->first();
        if ($segment) {
            return $segment;
        }

        return Segment::query()->create([
            'code'       => $code,
            'name'       => $name ? $this->norm($name) : $code,
            'is_active'  => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function findOrCreateSubSegment(string $segmentCode, ?string $code = null, ?string $name = null, ?int $userId = null): SubSegment
    {
        $segmentCode = $this->norm($segmentCode);
        $code = $this->norm($code ?: $segmentCode);

        $this->findOrCreateSegment($segmentCode, null, $userId);

        $candidates = $this->modelCodeCandidates($code);
        $row = SubSegment::query()
            ->where('segment_code', $segmentCode)
            ->where(function ($q) use ($candidates, $code) {
                $q->whereIn('code', $candidates)
                    ->orWhereRaw('UPPER(REPLACE(name, " ", "")) = ?', [preg_replace('/[^A-Z0-9]/', '', $code)]);
            })
            ->first();

        if ($row) {
            return $row;
        }

        try {
            return SubSegment::query()->create([
                'segment_code' => $segmentCode,
                'code'         => $candidates[0],
                'name'         => $name ? $this->norm($name) : $code,
                'is_active'    => true,
                'created_by'   => $userId,
                'updated_by'   => $userId,
            ]);
        } catch (\Throwable $e) {
            $again = SubSegment::query()
                ->where('segment_code', $segmentCode)
                ->whereIn('code', $candidates)
                ->first();
            if ($again) {
                return $again;
            }
            throw $e;
        }
    }

    /**
     * OEM Model → model.code / name / oem_name. Creates only if missing.
     */
    public function findOrCreateModel(
        string $oemModel,
        string $segmentCode,
        ?string $subSegmentCode = null,
        ?int $userId = null
    ): VehicleModel {
        $oemModel = $this->norm($oemModel);
        $segmentCode = $this->norm($segmentCode);
        $subSegmentCode = $this->norm($subSegmentCode ?: $segmentCode);

        $this->findOrCreateSubSegment($segmentCode, $subSegmentCode, $subSegmentCode, $userId);

        $candidates = $this->modelCodeCandidates($oemModel);
        $model = VehicleModel::query()
            ->where(function ($q) use ($candidates, $oemModel) {
                $q->whereIn('code', $candidates)
                    ->orWhereRaw('UPPER(TRIM(name)) = ?', [$oemModel])
                    ->orWhereRaw('UPPER(TRIM(oem_name)) = ?', [$oemModel]);
            })
            ->first();

        if ($model) {
            return $model;
        }

        try {
            return VehicleModel::query()->create([
                'segment_code'     => $segmentCode,
                'sub_segment_code' => $subSegmentCode,
                'code'             => $candidates[0],
                'name'             => $oemModel,
                'oem_name'         => $oemModel,
                'is_active'        => true,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
        } catch (\Throwable $e) {
            $again = VehicleModel::query()->whereIn('code', $candidates)->first();
            if ($again) {
                return $again;
            }
            throw $e;
        }
    }

    /**
     * Legacy rows stored BOLERONEO; Price List + norm() produce BOLERO NEO.
     *
     * @return list<string>
     */
    public function modelCodeCandidates(string $oemModel): array
    {
        $upper = $this->norm($oemModel);
        $compact = preg_replace('/[^A-Z0-9]/', '', $upper) ?: $upper;

        return array_values(array_unique(array_filter([$upper, $compact])));
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
                'model'   => $existing->vehicleModel ?? $this->findOrCreateModel(
                    $existing->model_code,
                    $existing->segment_code,
                    $existing->sub_segment_code,
                    $userId
                ),
            ];
        }

        $model = $this->findOrCreateModel($oemModel, $segmentCode, $segmentCode, $userId);

        $variant = Variant::query()->create([
            'segment_code'     => $model->segment_code,
            'sub_segment_code' => $model->sub_segment_code,
            'model_code'       => $model->code,
            'code'             => $oemCode,
            'oem_name'         => $oemVariant !== '' ? $oemVariant : $oemModel,
            'custom_name'      => $oemVariant !== '' ? $oemVariant : null,
            'display_name'     => null,
            'color'            => $color,
            'color_code'       => $color,
            'taxi_price'       => 'NO',
            'is_csd'           => $segmentCode === 'CSD',
            'is_active'        => false,
            'created_by'       => $userId,
            'updated_by'       => $userId,
        ]);

        Log::info('[VehicleService] stub created', [
            'oem_code'  => $oemCode,
            'oem_model' => $oemModel,
            'segment'   => $segmentCode,
        ]);

        return ['variant' => $variant, 'created' => true, 'model' => $model];
    }

    /**
     * Apply Vehicle Info row onto an existing variant (matched by OEM Code).
     *
     * @return array{complete:bool,missing:array,active:bool,variant:Variant}
     */
    public function applyVehicleInfo(Variant $variant, array $row, ?int $userId = null): array
    {
        $segmentCode = $this->norm($row['segment'] ?? $variant->segment_code);
        $subCode = $this->norm($row['sub_segment'] ?? $variant->sub_segment_code ?: $segmentCode);

        if ($segmentCode !== '') {
            $this->findOrCreateSubSegment($segmentCode, $subCode, $subCode, $userId);
        }

        if (! empty($row['custom_model'])) {
            $model = null;
            if ($variant->model_code) {
                $cands = $this->modelCodeCandidates($variant->model_code);
                $model = VehicleModel::query()->whereIn('code', $cands)->first();
            }
            if (! $model && ! empty($row['oem_model'])) {
                $model = $this->findOrCreateModel(
                    (string) $row['oem_model'],
                    $segmentCode ?: ($variant->segment_code ?? 'PV'),
                    $subCode ?: $variant->sub_segment_code,
                    $userId
                );
                $variant->model_code = $model->code;
            }
            if ($model) {
                $model->name = $this->norm($row['custom_model']);
                if (empty($model->oem_name) && ! empty($row['oem_model'])) {
                    $model->oem_name = $this->norm($row['oem_model']);
                }
                $model->segment_code = $segmentCode ?: $model->segment_code;
                $model->sub_segment_code = $subCode ?: $model->sub_segment_code;
                $model->updated_by = $userId;
                $model->save();
                $variant->setRelation('vehicleModel', $model);
            }
        }

        $variant->segment_code = $segmentCode ?: $variant->segment_code;
        $variant->sub_segment_code = $subCode ?: $variant->sub_segment_code;

        if (isset($row['custom_variant']) && $row['custom_variant'] !== '') {
            $variant->custom_name = $this->norm($row['custom_variant']);
        }
        if (isset($row['display_name']) && $row['display_name'] !== '') {
            $variant->display_name = $this->norm($row['display_name']);
        }
        if (isset($row['colour_name']) && $row['colour_name'] !== '') {
            $variant->color = $this->norm($row['colour_name']);
        }
        if (isset($row['taxi_price']) && $row['taxi_price'] !== '') {
            $variant->taxi_price = $this->norm($row['taxi_price']);
        }
        if (isset($row['seating']) && $row['seating'] !== '') {
            $variant->seating_capacity = (int) $row['seating'];
        }
        if (isset($row['wheels']) && $row['wheels'] !== '') {
            $variant->wheels = (int) $row['wheels'];
        }
        if (isset($row['transmission']) && $row['transmission'] !== '') {
            $variant->transmission = $this->norm($row['transmission']);
        }
        if (isset($row['drivetrain']) && $row['drivetrain'] !== '') {
            $variant->drivetrain = $this->norm($row['drivetrain']);
        }
        if (isset($row['cc']) && $row['cc'] !== '') {
            $variant->cc_capacity = $this->norm($row['cc']);
        }
        if (isset($row['motor']) && $row['motor'] !== '') {
            $variant->motor = $this->norm($row['motor']);
        }
        if (isset($row['gvw']) && $row['gvw'] !== '') {
            $variant->gvw = (int) $row['gvw'];
        }
        if (isset($row['gst_percent']) && $row['gst_percent'] !== '') {
            $raw = $row['gst_percent'];
            $variant->gst_percent = is_numeric($raw)
                ? (float) $raw
                : (float) str_replace(['%', ','], '', (string) $raw);
        }
        if (isset($row['shield_pack']) && $row['shield_pack'] !== '') {
            $variant->shield_pack = $this->norm($row['shield_pack']);
        }

        $variant->fuel_type_id = $this->kkvId('FUEL_TYPE', $row['fuel'] ?? null, true) ?? $variant->fuel_type_id;
        $variant->permit_id = $this->kkvId('PERMIT', $row['permit'] ?? null, true) ?? $variant->permit_id;
        $variant->body_make_id = $this->kkvId('BODY_MAKE', $row['body_make'] ?? null, true) ?? $variant->body_make_id;
        $variant->body_type_id = $this->kkvId('BODY_TYPE', $row['body_type'] ?? null, true) ?? $variant->body_type_id;

        $status = $this->norm($row['status'] ?? '');
        $missing = $this->missingFields($variant);
        $complete = $missing === [];

        if (in_array($status, [self::STATUS_ACTIVE, '1', 'Y', 'YES'], true)) {
            $variant->is_active = $complete;
            if ($complete) {
                $variant->status_id = $this->kkvId('VEHICLE_STATUS', 'ACTIVE') ?? $variant->status_id;
            }
        } elseif (in_array($status, [self::STATUS_INACTIVE, '0', 'N', 'NO'], true)) {
            $variant->is_active = false;
            $variant->status_id = $this->kkvId('VEHICLE_STATUS', 'INACTIVE') ?? $variant->status_id;
        } elseif ($status === self::STATUS_DISCONTINUED) {
            $variant->is_active = false;
            $variant->status_id = $this->kkvId('VEHICLE_STATUS', 'DISCONTINUED') ?? $variant->status_id;
        } else {
            $variant->is_active = $complete ? (bool) $variant->is_active : false;
        }

        $variant->updated_by = $userId;
        $variant->save();

        return [
            'complete' => $complete,
            'missing'  => $missing,
            'active'   => (bool) $variant->is_active,
            'variant'  => $variant->fresh(),
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
            'segment_code'     => $variant->segment_code,
            'sub_segment_code' => $variant->sub_segment_code,
            'fuel_type_id'     => $variant->fuel_type_id,
            'seating_capacity' => $variant->seating_capacity,
            'wheels'           => $variant->wheels,
            'transmission'     => $variant->transmission,
            'drivetrain'       => $variant->drivetrain,
            'body_make_id'     => $variant->body_make_id,
            'body_type_id'     => $variant->body_type_id,
            'gst_percent'      => $variant->gst_percent,
            'permit_id'        => $variant->permit_id,
            'taxi_price'       => $variant->taxi_price,
            'custom_name'      => $variant->custom_name,
            'display_name'     => $variant->display_name,
            'color'            => $variant->color,
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
            $row = Keyvalue::query()->create([
                'keyword_code' => $this->norm($keyword),
                'code'         => $compact,
                'value'        => $code,
                'is_active'    => true,
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

        foreach ([
            'permit_id', 'taxi_price', 'fuel_type_id', 'seating_capacity', 'wheels',
            'gvw', 'cc_capacity', 'motor', 'transmission', 'drivetrain',
            'body_type_id', 'body_make_id', 'gst_percent', 'shield_pack',
        ] as $field) {
            $to->{$field} = $from->{$field};
        }
        $to->updated_by = $userId;
        $to->save();

        return $to->fresh();
    }
}
