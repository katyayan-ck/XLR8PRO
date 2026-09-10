<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Services\KeywordValueService;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for vehicle master hierarchy creation / update.
 *
 * Hierarchy: Segment → SubSegment → Model → Variant (one row per colour)
 *
 * Rules (project-life constant):
 *  - vehicle_model.code  = stem WITHOUT colour (full Model Code minus last 2 chars)
 *  - vehicle_variant.code = FULL Model Code WITH colour
 *  - color_code = last 2 chars of Model Code → used for color, color_code, colour name
 *  - Every child stores ancestor codes (segment_code, sub_segment_code, model_code)
 *  - Incomplete until: segment_code, sub_segment_code, model_code, display_name,
 *    fuel_type (fuel_type_id), permit (permit_id), drivetrain are all present
 *  - Only a complete vehicle may be Active; Incomplete may be Inactive / Discontinued
 *
 * OEM Price List supplies only: Model Code, OEM Model, OEM Variant.
 * Segment is inferred from sheet title token after "Price List" when unknown.
 */
class VehicleMasterService
{
    /**
     * Completeness fields required before Active is allowed.
     */
    public const COMPLETE_FIELDS = [
        'segment_code',
        'sub_segment_code',
        'model_code',
        'display_name',
        'fuel_type_id',
        'permit_id',
        'drivetrain',
    ];

    /**
     * Strip colour suffix (last 2 chars) → model stem.
     * ASEP23QWSC1WD → ASEP23QWSC1
     */
    public static function stemFromModelCode(string $fullCode): string
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', trim($fullCode)) ?? trim($fullCode));
        if (strlen($fullCode) <= 2) {
            return $fullCode;
        }

        return substr($fullCode, 0, -2);
    }

    /**
     * Last 2 chars of full Model Code = colour code.
     */
    public static function colorCodeFromModelCode(string $fullCode): string
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', trim($fullCode)) ?? trim($fullCode));
        if (strlen($fullCode) < 2) {
            return $fullCode;
        }

        return substr($fullCode, -2);
    }

    /**
     * Segment token from Price List sheet title.
     * "Price List PV" → PV, "Price List LMM TZU" → LMM, "Price List CSD" → CSD
     */
    public static function segmentFromSheetTitle(string $title): string
    {
        $t = mb_strtoupper(trim($title));
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        if (preg_match('/PRICE\s*LIST\s+(\w+)/', $t, $m)) {
            $token = $m[1];
            if ($token === 'LMM' || str_contains($t, 'LMM')) {
                return 'LMM';
            }
            if (in_array($token, ['PV', 'CV', 'BEV', 'CSD', 'LMM'], true)) {
                return $token;
            }

            return $token;
        }

        if (str_contains($t, 'CSD')) {
            return 'CSD';
        }
        if (str_contains($t, 'BEV')) {
            return 'BEV';
        }
        if (str_contains($t, 'LMM')) {
            return 'LMM';
        }
        if (str_contains($t, 'CV')) {
            return 'CV';
        }

        return 'PV';
    }

    /**
     * Ensure Segment + SubSegment + Model + Variant exist for a full OEM Model Code.
     * Creates incomplete masters when missing. Never leaves model_code null on Variant.
     *
     * @param  array{
     *   full_code: string,
     *   oem_model?: ?string,
     *   oem_variant?: ?string,
     *   segment?: ?string,
     *   sheet_title?: ?string,
     *   color_name?: ?string,
     *   display_name?: ?string,
     *   custom_name?: ?string,
     *   fuel_type?: ?string,
     *   permit?: ?string,
     *   drivetrain?: ?string,
     *   transmission?: ?string,
     *   seating?: ?int,
     *   wheels?: ?int,
     *   gvw?: ?int,
     *   cc?: ?string,
     *   body_type?: ?string,
     *   body_make?: ?string,
     *   shield_pack?: ?string,
     *   taxi_price?: bool|string|null,
     *   is_active?: bool,
     *   sub_segment?: ?string,
     * }  $data
     * @return array{variant: Variant, created: int, updated: int, complete: bool}
     */
    public function ensureFromOemCode(array $data, ?int $userId = null): array
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', (string) ($data['full_code'] ?? '')) ?? '');
        if ($fullCode === '') {
            throw new \InvalidArgumentException('full_code (Model Code) is required');
        }

        $stem = self::stemFromModelCode($fullCode);
        $colorCode = self::colorCodeFromModelCode($fullCode);
        $created = 0;
        $updated = 0;

        // ── Segment ──────────────────────────────────────────────
        $segmentRaw = $data['segment']
            ?? (isset($data['sheet_title']) ? self::segmentFromSheetTitle((string) $data['sheet_title']) : null)
            ?? 'PV';
        $segmentRaw = strtoupper(trim((string) $segmentRaw));
        if (in_array($segmentRaw, ['PERSL', 'PERSONAL', 'PEROSNAL'], true)) {
            $segmentRaw = 'PV';
        }
        if (in_array($segmentRaw, ['COMML', 'COMMERCIAL'], true)) {
            $segmentRaw = 'CV';
        }
        $segmentCode = $segmentRaw;

        $segment = Segment::query()->firstOrCreate(
            ['code' => $segmentCode],
            [
                'name'       => $segmentCode,
                'is_active'  => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
        if ($segment->wasRecentlyCreated) {
            $created++;
        }

        // ── SubSegment (same code/name as segment when auto-created) ─
        $subRaw = isset($data['sub_segment']) && trim((string) $data['sub_segment']) !== ''
            ? trim((string) $data['sub_segment'])
            : $segmentCode;
        $subCode = SubSegment::generateCode($subRaw);
        if (in_array(strtoupper($subRaw), ['PV', 'CV', 'BEV', 'LMM', 'CSD', 'XUV', 'NXUV'], true)) {
            $subCode = strtoupper($subRaw);
        }

        $subSegment = SubSegment::query()->firstOrCreate(
            ['segment_code' => $segmentCode, 'code' => $subCode],
            [
                'name'       => $subRaw,
                'is_active'  => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
        if ($subSegment->wasRecentlyCreated) {
            $created++;
        }

        // ── Model (code = stem WITHOUT colour) ────────────────────
        $oemModel = trim((string) ($data['oem_model'] ?? '')) ?: null;
        $modelName = $oemModel ?: $stem;

        $model = VehicleModel::query()->firstOrCreate(
            ['code' => $stem],
            [
                'segment_code'     => $segmentCode,
                'sub_segment_code' => $subCode,
                'name'             => $modelName,
                'oem_name'         => $oemModel ?: $modelName,
                'is_active'        => true,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]
        );
        if ($model->wasRecentlyCreated) {
            $created++;
        } else {
            $dirty = false;
            if ($oemModel && $model->oem_name !== $oemModel) {
                $model->oem_name = $oemModel;
                $dirty = true;
            }
            if (! $model->segment_code) {
                $model->segment_code = $segmentCode;
                $dirty = true;
            }
            if (! $model->sub_segment_code) {
                $model->sub_segment_code = $subCode;
                $dirty = true;
            }
            if ($dirty) {
                $model->updated_by = $userId;
                $model->save();
                $updated++;
            }
        }

        // ── Colour ───────────────────────────────────────────────
        $colorName = trim((string) ($data['color_name'] ?? '')) ?: null;
        if ($colorName === null || $colorName === '') {
            $colorName = $colorCode;
        }

        // ── KKV ──────────────────────────────────────────────────
        $permitId = $this->kkvId('PERMIT', $data['permit'] ?? null);
        $fuelId = $this->kkvId('FUEL_TYPE', $data['fuel_type'] ?? null);
        $bodyTypeId = $this->kkvId('BODY_TYPE', $data['body_type'] ?? null);
        $bodyMakeId = $this->kkvId('BODY_MAKE', $data['body_make'] ?? null);

        $wantActive = (bool) ($data['is_active'] ?? false);
        $statusId = $this->kkvId('VEHICLE_STATUS', $wantActive ? 'ACTIVE' : 'INACTIVE');

        $oemVariant = trim((string) ($data['oem_variant'] ?? '')) ?: null;
        $displayName = trim((string) ($data['display_name'] ?? '')) ?: null;
        $customName = trim((string) ($data['custom_name'] ?? '')) ?: null;

        $oemVariantName = $oemVariant ?: ($displayName ?: $fullCode);

        $taxi = $data['taxi_price'] ?? null;
        if (is_bool($taxi)) {
            $taxiStr = $taxi ? 'YES' : 'NO';
        } elseif (is_string($taxi) && $taxi !== '') {
            $taxiStr = in_array(strtoupper($taxi), ['Y', 'YES', '1', 'TRUE'], true) ? 'YES' : 'NO';
        } else {
            $taxiStr = 'NO';
        }

        $variantCode = $fullCode;
        if (strlen($variantCode) > 20) {
            Log::warning('[VehicleMasterService] variant code > 20 chars', ['code' => $variantCode]);
            $variantCode = substr($variantCode, 0, 20);
        }

        // ── Variant (code = FULL with colour; model_code = stem) ──
        $variant = Variant::query()->where('code', $variantCode)->first();

        $attrs = [
            'segment_code'     => $segmentCode,
            'sub_segment_code' => $subCode,
            'model_code'       => $stem, // ALWAYS set — never null
            'oem_name'         => $oemVariantName,
            'custom_name'      => $customName,
            'display_name'     => $displayName,
            'color'            => $colorName,
            'color_code'       => $colorCode,
            'permit_id'        => $permitId,
            'fuel_type_id'     => $fuelId,
            'body_type_id'     => $bodyTypeId,
            'body_make_id'     => $bodyMakeId,
            'status_id'        => $statusId,
            'taxi_price'       => $taxiStr,
            'drivetrain'       => isset($data['drivetrain']) ? strtoupper(trim((string) $data['drivetrain'])) : null,
            'transmission'     => isset($data['transmission']) ? trim((string) $data['transmission']) : null,
            'seating_capacity' => isset($data['seating']) ? (int) $data['seating'] : null,
            'wheels'           => isset($data['wheels']) ? (int) $data['wheels'] : null,
            'gvw'              => isset($data['gvw']) ? (int) $data['gvw'] : null,
            'cc_capacity'      => isset($data['cc']) ? (string) $data['cc'] : null,
            'shield_pack'      => isset($data['shield_pack']) ? trim((string) $data['shield_pack']) : null,
            'updated_by'       => $userId,
        ];

        $probe = array_merge($attrs, ['code' => $variantCode]);
        $isComplete = $this->isCompleteArray($probe);
        if ($wantActive && ! $isComplete) {
            $attrs['is_active'] = false;
            $attrs['status_id'] = $this->kkvId('VEHICLE_STATUS', 'INACTIVE') ?? $statusId;
        } else {
            $attrs['is_active'] = $wantActive && $isComplete;
        }

        if ($variant) {
            $variant->fill($attrs);
            if (! $variant->model_code) {
                $variant->model_code = $stem;
            }
            if ($variant->isDirty()) {
                $variant->save();
                $updated++;
            }
        } else {
            $attrs['code'] = $variantCode;
            $attrs['created_by'] = $userId;
            $variant = Variant::query()->create($attrs);
            $created++;
        }

        return [
            'variant'  => $variant->fresh(),
            'created'  => $created,
            'updated'  => $updated,
            'complete' => $this->isComplete($variant),
        ];
    }

    public function isComplete(Variant $variant): bool
    {
        return $this->isCompleteArray([
            'segment_code'     => $variant->segment_code,
            'sub_segment_code' => $variant->sub_segment_code,
            'model_code'       => $variant->model_code,
            'display_name'     => $variant->display_name,
            'fuel_type_id'     => $variant->fuel_type_id,
            'permit_id'        => $variant->permit_id,
            'drivetrain'       => $variant->drivetrain,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function isCompleteArray(array $attrs): bool
    {
        foreach (self::COMPLETE_FIELDS as $field) {
            $v = $attrs[$field] ?? null;
            if ($v === null || $v === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function missingFields(Variant|array $source): array
    {
        if ($source instanceof Variant) {
            $attrs = [
                'segment_code'     => $source->segment_code,
                'sub_segment_code' => $source->sub_segment_code,
                'model_code'       => $source->model_code,
                'display_name'     => $source->display_name,
                'fuel_type_id'     => $source->fuel_type_id,
                'permit_id'        => $source->permit_id,
                'drivetrain'       => $source->drivetrain,
            ];
        } else {
            $attrs = $source;
        }

        $labels = [
            'segment_code'     => 'segment_code',
            'sub_segment_code' => 'sub_segment_code',
            'model_code'       => 'model_code',
            'display_name'     => 'display_name',
            'fuel_type_id'     => 'fuel_type',
            'permit_id'        => 'permit',
            'drivetrain'       => 'drivetrain',
        ];

        $missing = [];
        foreach (self::COMPLETE_FIELDS as $field) {
            $v = $attrs[$field] ?? null;
            if ($v === null || $v === '') {
                $missing[] = $labels[$field] ?? $field;
            }
        }

        return $missing;
    }

    public function kkvId(string $keywordCode, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (class_exists(KeywordValueService::class) && method_exists(KeywordValueService::class, 'getValueId')) {
            $id = KeywordValueService::getValueId($keywordCode, $value);
            if ($id) {
                return (int) $id;
            }
        }

        $keywordCode = strtoupper(trim($keywordCode));
        $row = Keyvalue::query()
            ->where('keyword_code', $keywordCode)
            ->where(function ($q) use ($value) {
                $q->whereRaw('UPPER(value) = ?', [strtoupper($value)])
                    ->orWhereRaw('UPPER(code) = ?', [strtoupper($value)]);
            })
            ->first();

        return $row?->id;
    }
}
