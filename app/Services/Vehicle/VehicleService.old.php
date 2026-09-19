<?php

namespace App\Services\Vehicle;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\KeywordValueService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleService
{
    public function createOrUpdateFromRow(array $data): Variant
    {
        return DB::transaction(function () use ($data) {
            $segment = $this->ensureSegment(
                $data['segment_code'],
                $data['segment_name'] ?? null
            );

            $subSegment = null;
            if (!empty($data['sub_segment_code'])) {
                $subSegment = $this->ensureSubSegment(
                    $segment->code,
                    $data['sub_segment_code'],
                    $data['sub_segment_name'] ?? null
                );
            }

            $model = $this->ensureModel(
                $segment->code,
                $subSegment?->code,
                $data['model_code'],
                $data['model_name'] ?? null,
                $data['oem_model'] ?? null
            );

            return $this->ensureVariant($model, $data);
        });
    }

    public function ensureSegment(string $code, ?string $name = null): Segment
    {
        $code = strtoupper(trim($code));
        return Segment::firstOrCreate(
            ['code' => $code],
            ['name' => $name ?: ucfirst(strtolower($code)), 'is_active' => true]
        );
    }

    public function ensureSubSegment(string $segmentCode, string $code, ?string $name = null): SubSegment
    {
        $code = strtoupper(trim($code));
        return SubSegment::firstOrCreate(
            ['segment_code' => $segmentCode, 'code' => $code],
            ['name' => $name ?: ucfirst(strtolower($code)), 'is_active' => true]
        );
    }

    public function ensureModel(
        string $segmentCode,
        ?string $subSegmentCode,
        string $code,
        ?string $name = null,
        ?string $oemName = null
    ): VehicleModel {
        $code = strtoupper(trim($code));
        return VehicleModel::updateOrCreate(
            ['code' => $code],
            [
                'segment_code'     => $segmentCode,
                'sub_segment_code' => $subSegmentCode,
                'name'             => $name ?: $code,
                'oem_name'         => $oemName ? strtoupper($oemName) : null,
                'is_active'        => true,
            ]
        );
    }

    public function ensureVariant(VehicleModel $model, array $data): Variant
    {
        $fullCode    = strtoupper(trim($data['full_model_code'] ?? $data['model_code']));
        $variantCode = substr($fullCode, 0, -2);
        $colorCode   = substr($fullCode, -2);

        return Variant::updateOrCreate(
            [
                'model_code' => $model->code,
                'code'       => $variantCode,
                'color_code' => $colorCode,
            ],
            [
                'segment_code'     => $model->segment_code,
                'sub_segment_code' => $model->sub_segment_code,
                'oem_name'         => $data['oem_variant'] ?? null,
                'custom_name'      => $data['custom_variant'] ?? null,
                'display_name'     => $data['display_name'] ?? null,
                'color'            => $data['colour_name'] ?? null,
                'fuel_type_id'     => $this->resolveKeyValue('FUEL_TYPE', $data['fuel'] ?? null),
                'seating_capacity' => is_numeric($data['seating'] ?? null) ? (int) $data['seating'] : null,
                'wheels'           => is_numeric($data['wheels'] ?? null) ? (int) $data['wheels'] : 4,
                'gvw'              => is_numeric($data['gvw'] ?? null) ? (int) $data['gvw'] : null,
                'cc_capacity'      => !empty($data['cc']) ? (string) $data['cc'] : null,
                'transmission'     => strtoupper($data['transmission'] ?? '') ?: null,
                'drivetrain'       => strtoupper($data['drivetrain'] ?? '') ?: null,
                'body_make_id'     => $this->resolveKeyValue('BODY_MAKE', $data['body_make'] ?? null),
                'body_type_id'     => $this->resolveKeyValue('BODY_TYPE', $data['body_type'] ?? null),
                'permit_id'        => $this->resolveKeyValue('PERMIT', $data['permit'] ?? null),
                'taxi_price'       => strtoupper($data['taxi_price'] ?? 'NO'),
                'status_id'        => $this->resolveKeyValue('VEHICLE_STATUS', $data['status'] ?? 'ACTIVE'),
                'is_csd'           => (bool) ($data['is_csd'] ?? false),
                'is_active'        => true,
            ]
        );
    }

    public function findByOemCode(string $oemCode): ?Variant
    {
        $oemCode     = strtoupper(trim($oemCode));
        $variantCode = substr($oemCode, 0, -2);
        $colorCode   = substr($oemCode, -2);

        return Variant::with(['model', 'model.segment', 'model.subSegment'])
            ->where('code', $variantCode)
            ->where('color_code', $colorCode)
            ->first();
    }

    public function getAvailableColors(string $variantCode): Collection
    {
        return Variant::where('code', $variantCode)
            ->where('is_active', true)
            ->get(['color_code', 'color', 'display_name']);
    }



public function resolveKeyValue(string $keywordCode, ?string $value): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $keywordCode = strtoupper(trim($keywordCode));
    $upper       = strtoupper(trim((string) $value));

    // 1) Prefer existing project service (cached lookup by code)
    $id = KeywordValueService::getValueId($keywordCode, $upper, false);
    if ($id) {
        return $id;
    }

    // 2) Try match on value / key columns as well
    $existing = Keyvalue::query()
        ->where('keyword_code', $keywordCode)
        ->where(function ($q) use ($upper, $value) {
            $q->where('code', $upper)
              ->orWhere('key', $upper)
              ->orWhereRaw('UPPER(value) = ?', [$upper]);
        })
        ->first();

    if ($existing) {
        return (int) $existing->id;
    }

    // 3) Auto-create so import does not block on missing master data
    try {
        $new = Keyvalue::create([
            'keyword_code' => $keywordCode,
            'code'         => $upper,
            'key'          => $upper,
            'value'        => trim((string) $value),
            'status'       => 1,
            'is_active'    => true,
        ]);

        KeywordValueService::clearCache($keywordCode);

        Log::info("VehicleService: Auto-created Keyvalue [{$keywordCode}] = {$value} (id {$new->id})");

        return (int) $new->id;
    } catch (\Throwable $e) {
        Log::warning("VehicleService: could not create Keyvalue [{$keywordCode}]={$value}: " . $e->getMessage());
        return null;
    }
}
    public function isMasterComplete(string $fullModelCode): bool
    {
        return (bool) $this->findByOemCode($fullModelCode);
    }
}