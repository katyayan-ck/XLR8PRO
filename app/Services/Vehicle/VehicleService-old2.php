<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Project-wide Vehicle SSOT.
 *
 * Hierarchy: Segment → SubSegment → Model → Variant (one row per colour).
 *  - vehicle_model.code   = stem WITHOUT colour
 *  - vehicle_variant.code = FULL Model Code WITH colour
 *  - color_code           = last 2 chars of full code
 *
 * Completeness (Active only when all present):
 *  segment_code, sub_segment_code, model_code, display_name,
 *  fuel_type_id, permit_id, drivetrain
 *
 * Creation of incomplete masters from OEM sheets is delegated to VehicleMasterService.
 */
class VehicleService
{
    public const STATUS_ACTIVE        = 'active';
    public const STATUS_INACTIVE      = 'inactive';
    public const STATUS_DISCONTINUED  = 'discontinued';
    public const STATUS_ALL           = 'all';

    public function __construct(
        protected VehicleMasterService $masters
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // 1–3  Find / Create / Update
    // ═══════════════════════════════════════════════════════════════

    /**
     * Find or create Segment by code (PV, CV, BEV, LMM, CSD…).
     */
    public function findOrCreateSegment(string $code, ?string $name = null, ?int $userId = null): Segment
    {
        $code = strtoupper(trim($code));
        if (in_array($code, ['PERSL', 'PERSONAL', 'PEROSNAL'], true)) {
            $code = 'PV';
        }
        if (in_array($code, ['COMML', 'COMMERCIAL'], true)) {
            $code = 'CV';
        }

        return Segment::query()->firstOrCreate(
            ['code' => $code],
            [
                'name'       => $name ?: $code,
                'is_active'  => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Find or create default SubSegment under a Segment.
     * When $subCode is null, uses segment code as temporary default.
     */
    public function findOrCreateSubSegment(
        string $segmentCode,
        ?string $subCode = null,
        ?string $name = null,
        ?int $userId = null
    ): SubSegment {
        $segmentCode = strtoupper(trim($segmentCode));
        $this->findOrCreateSegment($segmentCode, null, $userId);

        $subCode = $subCode ? strtoupper(trim($subCode)) : $segmentCode;
        if (in_array($subCode, ['PV', 'CV', 'BEV', 'LMM', 'CSD', 'XUV', 'NXUV'], true)) {
            // keep short codes as-is
        } else {
            $subCode = SubSegment::generateCode($subCode);
        }

        return SubSegment::query()->firstOrCreate(
            ['segment_code' => $segmentCode, 'code' => $subCode],
            [
                'name'       => $name ?: $subCode,
                'is_active'  => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Find or create Model. Code = stem WITHOUT colour.
     *
     * @param  array{code: string, segment_code: string, sub_segment_code?: ?string, name?: ?string, oem_name?: ?string}  $data
     */
    public function findOrCreateModel(array $data, ?int $userId = null): VehicleModel
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            throw new \InvalidArgumentException('model code is required');
        }

        $segmentCode = strtoupper(trim((string) ($data['segment_code'] ?? 'PV')));
        $subCode = isset($data['sub_segment_code']) && $data['sub_segment_code'] !== ''
            ? strtoupper(trim((string) $data['sub_segment_code']))
            : $segmentCode;

        $this->findOrCreateSegment($segmentCode, null, $userId);
        $this->findOrCreateSubSegment($segmentCode, $subCode, null, $userId);

        $name = $data['name'] ?? $data['oem_name'] ?? $code;
        $oemName = $data['oem_name'] ?? $name;

        $model = VehicleModel::query()->firstOrCreate(
            ['code' => $code],
            [
                'segment_code'     => $segmentCode,
                'sub_segment_code' => $subCode,
                'name'             => $name,
                'oem_name'         => $oemName,
                'is_active'        => true,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]
        );

        if (!$model->wasRecentlyCreated) {
            $dirty = false;
            if (!$model->segment_code) {
                $model->segment_code = $segmentCode;
                $dirty = true;
            }
            if (!$model->sub_segment_code) {
                $model->sub_segment_code = $subCode;
                $dirty = true;
            }
            if (!empty($data['oem_name']) && $model->oem_name !== $data['oem_name']) {
                $model->oem_name = $data['oem_name'];
                $dirty = true;
            }
            if ($dirty) {
                $model->updated_by = $userId;
                $model->save();
            }
        }

        return $model;
    }

    /**
     * Find variant by full Model Code (with colour), or create incomplete shell.
     * Prefer ensureFromOemCode() when coming from Price List / Vehicle Info.
     */
    public function findOrCreateVariant(array $data, ?int $userId = null): Variant
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', (string) ($data['code'] ?? $data['full_code'] ?? '')) ?? '');
        if ($fullCode === '') {
            throw new \InvalidArgumentException('variant full code is required');
        }

        $existing = Variant::query()->where('code', $fullCode)->first();
        if ($existing) {
            return $existing;
        }

        $result = $this->masters->ensureFromOemCode(array_merge($data, [
            'full_code' => $fullCode,
        ]), $userId);

        return $result['variant'];
    }

    /**
     * Update an existing variant (specs, names, status). Never clears model_code.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function updateVariant(string $fullCode, array $attrs, ?int $userId = null): Variant
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', $fullCode) ?? $fullCode);
        $variant = Variant::query()->where('code', $fullCode)->firstOrFail();

        // Protect identity columns
        unset($attrs['code']);
        if (empty($attrs['model_code'])) {
            unset($attrs['model_code']);
        }

        $attrs['updated_by'] = $userId;
        $variant->fill($attrs);

        // Active only if complete
        if (array_key_exists('is_active', $attrs) && $attrs['is_active'] && !$this->masters->isComplete($variant)) {
            $variant->is_active = false;
            Log::info('[VehicleService] refused Active on incomplete variant', [
                'code'    => $fullCode,
                'missing' => $this->masters->missingFields($variant),
            ]);
        }

        $variant->save();
        return $variant->fresh();
    }

    /**
     * OEM detect / import entry — delegates to VehicleMasterService.
     *
     * @return array{variant: Variant, created: int, updated: int, complete: bool}
     */
    public function ensureFromOemCode(array $data, ?int $userId = null): array
    {
        return $this->masters->ensureFromOemCode($data, $userId);
    }

    // ═══════════════════════════════════════════════════════════════
    // 4  Descendants with status filter
    // ═══════════════════════════════════════════════════════════════

    /**
     * All descendants under a Segment, SubSegment, or Model.
     *
     * @param  'segment'|'sub_segment'|'model'  $level
     * @param  self::STATUS_*  $status  active|inactive|discontinued|all
     * @return Collection<int, Variant>
     */
    public function descendantsOf(
        string $level,
        string $code,
        string $status = self::STATUS_ACTIVE
    ): Collection {
        $code = strtoupper(trim($code));
        $q = Variant::query();

        match ($level) {
            'segment'     => $q->where('segment_code', $code),
            'sub_segment' => $q->where('sub_segment_code', $code),
            'model'       => $q->where('model_code', $code),
            default       => throw new \InvalidArgumentException("Unknown level: {$level}"),
        };

        $this->applyStatusFilter($q, $status);

        return $q->orderBy('model_code')->orderBy('code')->get();
    }

    // ═══════════════════════════════════════════════════════════════
    // 5–8  Lists
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return Collection<int, SubSegment>
     */
    public function subSegmentsOf(string $segmentCode, string $status = self::STATUS_ACTIVE): Collection
    {
        $q = SubSegment::query()->where('segment_code', strtoupper(trim($segmentCode)));
        if ($status === self::STATUS_ACTIVE) {
            $q->where('is_active', true);
        } elseif ($status === self::STATUS_INACTIVE) {
            $q->where('is_active', false);
        }
        return $q->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Segment>
     */
    public function allSegments(string $status = self::STATUS_ACTIVE): Collection
    {
        $q = Segment::query();
        if ($status === self::STATUS_ACTIVE) {
            $q->where('is_active', true);
        } elseif ($status === self::STATUS_INACTIVE) {
            $q->where('is_active', false);
        }
        return $q->orderBy('code')->get();
    }

    /**
     * Models under a Segment and/or SubSegment.
     *
     * @return Collection<int, VehicleModel>
     */
    public function modelsOf(
        ?string $segmentCode = null,
        ?string $subSegmentCode = null,
        string $status = self::STATUS_ACTIVE
    ): Collection {
        $q = VehicleModel::query();
        if ($segmentCode) {
            $q->where('segment_code', strtoupper(trim($segmentCode)));
        }
        if ($subSegmentCode) {
            $q->where('sub_segment_code', strtoupper(trim($subSegmentCode)));
        }
        if ($status === self::STATUS_ACTIVE) {
            $q->where('is_active', true);
        } elseif ($status === self::STATUS_INACTIVE) {
            $q->where('is_active', false);
        }
        return $q->orderBy('name')->get();
    }

    /**
     * Variants under Segment / SubSegment / Model (any combination).
     *
     * @return Collection<int, Variant>
     */
    public function variantsOf(
        ?string $segmentCode = null,
        ?string $subSegmentCode = null,
        ?string $modelCode = null,
        string $status = self::STATUS_ACTIVE
    ): Collection {
        $q = Variant::query();
        if ($segmentCode) {
            $q->where('segment_code', strtoupper(trim($segmentCode)));
        }
        if ($subSegmentCode) {
            $q->where('sub_segment_code', strtoupper(trim($subSegmentCode)));
        }
        if ($modelCode) {
            $q->where('model_code', strtoupper(trim($modelCode)));
        }
        $this->applyStatusFilter($q, $status);
        return $q->orderBy('model_code')->orderBy('code')->get();
    }

    // ═══════════════════════════════════════════════════════════════
    // 9–11  Colours
    // ═══════════════════════════════════════════════════════════════

    /**
     * All colour rows that share the same stem (variant family).
     * Pass either full code or stem — both work.
     *
     * @return Collection<int, array{code: string, color_code: string, color: ?string, display_name: ?string}>
     */
    public function colorsOfVariant(string $codeOrStem, string $status = self::STATUS_ACTIVE): Collection
    {
        $code = strtoupper(preg_replace('/\s+/', '', $codeOrStem) ?? $codeOrStem);
        $stem = VehicleMasterService::stemFromModelCode($code);

        $q = Variant::query()->where('model_code', $stem);
        // Also catch rows where model_code was wrongly set to full code historically
        $q->orWhere(function ($qq) use ($stem) {
            $qq->where('code', 'like', $stem . '__')
               ->where(function ($q2) use ($stem) {
                   $q2->whereNull('model_code')->orWhere('model_code', '');
               });
        });

        $this->applyStatusFilter($q, $status);

        return $q->orderBy('color_code')->get()->map(fn (Variant $v) => [
            'code'         => $v->code,
            'color_code'   => $v->color_code ?: VehicleMasterService::colorCodeFromModelCode($v->code),
            'color'        => $v->color,
            'display_name' => $v->display_name,
            'is_active'    => (bool) $v->is_active,
        ]);
    }

    /**
     * All colours under a Model (stem code).
     */
    public function colorsOfModel(string $modelCode, string $status = self::STATUS_ACTIVE): Collection
    {
        return $this->colorsOfVariant($modelCode, $status);
    }

    /**
     * Distinct colours under a Segment or SubSegment.
     *
     * @param  'segment'|'sub_segment'  $level
     * @return Collection<int, array{color_code: string, color: ?string, count: int}>
     */
    public function colorsOfScope(string $level, string $code, string $status = self::STATUS_ACTIVE): Collection
    {
        $q = Variant::query()->whereNotNull('color_code')->where('color_code', '!=', '');
        match ($level) {
            'segment'     => $q->where('segment_code', strtoupper(trim($code))),
            'sub_segment' => $q->where('sub_segment_code', strtoupper(trim($code))),
            default       => throw new \InvalidArgumentException("colorsOfScope level must be segment|sub_segment"),
        };
        $this->applyStatusFilter($q, $status);

        return $q->select('color_code', 'color', DB::raw('COUNT(*) as cnt'))
            ->groupBy('color_code', 'color')
            ->orderBy('color_code')
            ->get()
            ->map(fn ($r) => [
                'color_code' => $r->color_code,
                'color'      => $r->color,
                'count'      => (int) $r->cnt,
            ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 12  Copy specifications
    // ═══════════════════════════════════════════════════════════════

    /**
     * Copy technical specs from one variant to another (same or different model).
     * Does NOT copy identity: code, model_code, segment, colour, names, status.
     *
     * @param  list<string>|null  $only  Restrict to these attribute names; null = all spec fields
     */
    public function copySpecifications(
        string $fromFullCode,
        string $toFullCode,
        ?array $only = null,
        ?int $userId = null
    ): Variant {
        $from = Variant::query()->where('code', strtoupper(trim($fromFullCode)))->firstOrFail();
        $to   = Variant::query()->where('code', strtoupper(trim($toFullCode)))->firstOrFail();

        $specFields = [
            'permit_id', 'fuel_type_id', 'body_type_id', 'body_make_id',
            'seating_capacity', 'wheels', 'gvw', 'cc_capacity',
            'transmission', 'drivetrain', 'taxi_price', 'shield_pack',
            'is_csd', 'csd_index',
        ];

        if ($only !== null) {
            $specFields = array_values(array_intersect($specFields, $only));
        }

        foreach ($specFields as $field) {
            $to->{$field} = $from->{$field};
        }
        $to->updated_by = $userId;
        $to->save();

        return $to->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // Lookups
    // ═══════════════════════════════════════════════════════════════

    public function findByFullCode(string $fullCode): ?Variant
    {
        $fullCode = strtoupper(preg_replace('/\s+/', '', $fullCode) ?? $fullCode);
        return Variant::query()->where('code', $fullCode)->first();
    }

    /**
     * All colour rows for a model stem.
     *
     * @return Collection<int, Variant>
     */
    public function findByStem(string $stem, string $status = self::STATUS_ALL): Collection
    {
        $stem = strtoupper(trim($stem));
        $q = Variant::query()->where('model_code', $stem);
        $this->applyStatusFilter($q, $status);
        return $q->orderBy('color_code')->get();
    }

    public function isComplete(Variant $variant): bool
    {
        return $this->masters->isComplete($variant);
    }

    /**
     * @return list<string>
     */
    public function missingFields(Variant $variant): array
    {
        return $this->masters->missingFields($variant);
    }

    // ═══════════════════════════════════════════════════════════════
    // Search & pricing helpers
    // ═══════════════════════════════════════════════════════════════

    /**
     * Filtered variant search for quotation / stock / admin.
     *
     * @param  array{
     *   segment?: ?string,
     *   sub_segment?: ?string,
     *   model_code?: ?string,
     *   fuel?: ?string,
     *   transmission?: ?string,
     *   drivetrain?: ?string,
     *   seats?: ?int,
     *   wheels?: ?int,
     *   q?: ?string,
     *   status?: string,
     *   complete_only?: bool,
     * }  $filters
     * @return Builder
     */
    public function search(array $filters = []): Builder
    {
        $q = Variant::query();

        if (!empty($filters['segment'])) {
            $q->where('segment_code', strtoupper(trim($filters['segment'])));
        }
        if (!empty($filters['sub_segment'])) {
            $q->where('sub_segment_code', strtoupper(trim($filters['sub_segment'])));
        }
        if (!empty($filters['model_code'])) {
            $q->where('model_code', strtoupper(trim($filters['model_code'])));
        }
        if (!empty($filters['transmission'])) {
            $q->where('transmission', 'like', '%' . $filters['transmission'] . '%');
        }
        if (!empty($filters['drivetrain'])) {
            $q->where('drivetrain', strtoupper(trim($filters['drivetrain'])));
        }
        if (isset($filters['seats'])) {
            $q->where('seating_capacity', (int) $filters['seats']);
        }
        if (isset($filters['wheels'])) {
            $q->where('wheels', (int) $filters['wheels']);
        }
        if (!empty($filters['q'])) {
            $term = '%' . trim($filters['q']) . '%';
            $q->where(function ($qq) use ($term) {
                $qq->where('code', 'like', $term)
                   ->orWhere('display_name', 'like', $term)
                   ->orWhere('oem_name', 'like', $term)
                   ->orWhere('custom_name', 'like', $term)
                   ->orWhere('model_code', 'like', $term);
            });
        }

        $status = $filters['status'] ?? self::STATUS_ACTIVE;
        $this->applyStatusFilter($q, $status);

        if (!empty($filters['complete_only'])) {
            $q->whereNotNull('display_name')
              ->where('display_name', '!=', '')
              ->whereNotNull('model_code')
              ->whereNotNull('segment_code')
              ->whereNotNull('sub_segment_code')
              ->whereNotNull('fuel_type_id')
              ->whereNotNull('permit_id')
              ->whereNotNull('drivetrain')
              ->where('drivetrain', '!=', '');
        }

        // fuel via KKV id if service can resolve
        if (!empty($filters['fuel'])) {
            $fuelId = $this->masters->kkvId('FUEL_TYPE', $filters['fuel']);
            if ($fuelId) {
                $q->where('fuel_type_id', $fuelId);
            }
        }

        return $q->orderBy('segment_code')->orderBy('model_code')->orderBy('code');
    }

    /**
     * Variants eligible for on-road pricing calculation.
     * Complete + active (+ optional segment filter).
     *
     * @return Collection<int, Variant>
     */
    public function forPricing(?string $segmentCode = null): Collection
    {
        $q = $this->search([
            'segment'       => $segmentCode,
            'status'        => self::STATUS_ACTIVE,
            'complete_only' => true,
        ]);
        return $q->get();
    }

    /**
     * Expand scope tokens for rule matching (RTO / Insurance / Addon sheets).
     * Blank / Any / null → null (means all). Comma-separated → list of codes.
     *
     * @return list<string>|null  null = match all
     */
    public function resolveScopeCodes(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || strcasecmp($raw, 'Any') === 0 || strcasecmp($raw, 'All') === 0) {
            return null;
        }
        $parts = array_map(
            fn ($p) => strtoupper(trim($p)),
            explode(',', $raw)
        );
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        return $parts === [] ? null : $parts;
    }

    /**
     * Variants matching rule-sheet scope (segment / model / variant columns).
     * Used by Addon / RTO / Insurance importers.
     *
     * @return Builder
     */
    public function matchingScope(
        ?string $segmentRaw = null,
        ?string $modelRaw = null,
        ?string $variantRaw = null,
        string $status = self::STATUS_ACTIVE
    ): Builder {
        $q = Variant::query();
        $this->applyStatusFilter($q, $status);

        $segments = $this->resolveScopeCodes($segmentRaw);
        if ($segments !== null) {
            $q->whereIn('segment_code', $segments);
        }

        $models = $this->resolveScopeCodes($modelRaw);
        if ($models !== null) {
            // Match model_code (stem) OR display/oem name depending on global Excel rule
            $q->where(function ($qq) use ($models) {
                $qq->whereIn('model_code', $models)
                   ->orWhereIn('oem_name', $models);
            });
        }

        $variants = $this->resolveScopeCodes($variantRaw);
        if ($variants !== null) {
            $q->where(function ($qq) use ($variants) {
                $qq->whereIn('code', $variants)
                   ->orWhereIn('display_name', $variants)
                   ->orWhereIn('oem_name', $variants);
            });
        }

        return $q;
    }

    // ═══════════════════════════════════════════════════════════════
    // Admin / tree / stats / repair
    // ═══════════════════════════════════════════════════════════════

    /**
     * Segment → SubSegment → Model counts for admin tree UI.
     *
     * @return list<array{code: string, name: string, sub_segments: list<array{code: string, name: string, models: int, variants: int}>}>
     */
    public function segmentTree(string $status = self::STATUS_ACTIVE): array
    {
        $segments = $this->allSegments($status);
        $tree = [];

        foreach ($segments as $seg) {
            $subs = $this->subSegmentsOf($seg->code, $status);
            $subNodes = [];
            foreach ($subs as $sub) {
                $modelCount = VehicleModel::query()
                    ->where('segment_code', $seg->code)
                    ->where('sub_segment_code', $sub->code)
                    ->count();
                $variantCount = Variant::query()
                    ->where('segment_code', $seg->code)
                    ->where('sub_segment_code', $sub->code);
                $this->applyStatusFilter($variantCount, $status);
                $subNodes[] = [
                    'code'     => $sub->code,
                    'name'     => $sub->name,
                    'models'   => $modelCount,
                    'variants' => $variantCount->count(),
                ];
            }
            $tree[] = [
                'code'         => $seg->code,
                'name'         => $seg->name,
                'sub_segments' => $subNodes,
            ];
        }

        return $tree;
    }

    /**
     * @return array{segments: int, sub_segments: int, models: int, variants: int, active: int, incomplete: int}
     */
    public function stats(): array
    {
        $variants = Variant::query();
        $active = (clone $variants)->where('is_active', true)->count();
        $incomplete = Variant::query()
            ->where(function ($q) {
                $q->whereNull('display_name')->orWhere('display_name', '')
                  ->orWhereNull('model_code')->orWhere('model_code', '')
                  ->orWhereNull('fuel_type_id')
                  ->orWhereNull('permit_id')
                  ->orWhereNull('drivetrain')->orWhere('drivetrain', '');
            })
            ->count();

        return [
            'segments'     => Segment::query()->count(),
            'sub_segments' => SubSegment::query()->count(),
            'models'       => VehicleModel::query()->count(),
            'variants'     => Variant::query()->count(),
            'active'       => $active,
            'incomplete'   => $incomplete,
        ];
    }

    /**
     * Repair variants missing model_code / ancestor codes (from bad earlier imports).
     * model_code derived from stem of variant.code.
     *
     * @return int number of rows fixed
     */
    public function syncAncestorCodes(?int $userId = null): int
    {
        $fixed = 0;
        Variant::query()
            ->where(function ($q) {
                $q->whereNull('model_code')->orWhere('model_code', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$fixed, $userId) {
                foreach ($rows as $v) {
                    $stem = VehicleMasterService::stemFromModelCode($v->code);
                    $v->model_code = $stem;
                    if (!$v->color_code) {
                        $v->color_code = VehicleMasterService::colorCodeFromModelCode($v->code);
                    }
                    if (!$v->color) {
                        $v->color = $v->color_code;
                    }
                    $v->updated_by = $userId;
                    $v->save();
                    $fixed++;
                }
            });

        return $fixed;
    }

    // ═══════════════════════════════════════════════════════════════
    // Internal
    // ═══════════════════════════════════════════════════════════════

    protected function applyStatusFilter(Builder $q, string $status): void
    {
        match ($status) {
            self::STATUS_ACTIVE => $q->where('is_active', true),
            self::STATUS_INACTIVE => $q->where('is_active', false),
            self::STATUS_DISCONTINUED => $q->where(function ($qq) {
                // Prefer status KKV when present; fallback is_active=false
                $qq->where('is_active', false);
            }),
            self::STATUS_ALL => null,
            default => $q->where('is_active', true),
        };
    }
}
