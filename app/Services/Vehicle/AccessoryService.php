<?php

namespace App\Services\Vehicle;

use App\Models\Core\ImportLog;
use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Unified Vehicle Accessory catalog service.
 *
 * - importExcel(): purge + reload all sheets (type per sheet)
 * - list(): fetch by type + SEGMENT/MODEL/Variant/Permit
 * - exportRows() / exportToDisk(): flat export
 *
 * Types: Accessory, Ceramic, PPF, Maxicare, GPS_VLTD, RTO_Tape, Kazam
 * "all" list mode = everything except RTO_Tape + Kazam (fetched only explicitly).
 */
class AccessoryService
{
    protected array $errors = [];

    protected array $warnings = [];

    protected int $total = 0;

    protected int $imported = 0;

    protected int $skipped = 0;

    protected int $userId = 1;

    protected ?ImportLog $log = null;

    /** Sheet name (case-insensitive) → catalog type */
    protected array $sheetTypeMap = [
        'accessories' => Accessory::TYPE_ACCESSORY,
        'accessory' => Accessory::TYPE_ACCESSORY,
        'ceramic' => Accessory::TYPE_CERAMIC,
        'ppf' => Accessory::TYPE_PPF,
        'maxicare' => Accessory::TYPE_MAXICARE,
        'gps vltd' => Accessory::TYPE_GPS_VLTD,
        'gps_vltd' => Accessory::TYPE_GPS_VLTD,
        'gpsvltd' => Accessory::TYPE_GPS_VLTD,
        'rto tape' => Accessory::TYPE_RTO_TAPE,
        'rto_tape' => Accessory::TYPE_RTO_TAPE,
        'rtotape' => Accessory::TYPE_RTO_TAPE,
        'kazam' => Accessory::TYPE_KAZAM,
    ];

    /** Excel SEGMENT name → master segment.code */
    protected array $segmentAliases = [
        'PERSONAL' => 'PV',
        'PV' => 'PV',
        'COMMERCIAL' => 'CV',
        'CV' => 'CV',
        'BEV' => 'BEV',
        'LMM' => 'LMM',
        'ANY' => null, // wildcard scope
        'ALL' => null,
    ];

    // ─────────────────────────────────────────────────────────────
    // IMPORT (purge + full reload)
    // ─────────────────────────────────────────────────────────────

    /**
     * Import multi-sheet workbook. Always purges existing catalog first.
     *
     * @return array{success:bool,message:string,total_records:int,imported_count:int,skipped_count:int,errors_count:int,warnings:array}
     */
    public function importExcel(string $path, int $userId = 1): array
    {
        $this->resetCounters();
        $this->userId = $userId;
        $this->startLog($userId, basename($path));

        try {
            if (! is_file($path)) {
                throw new \InvalidArgumentException("File not found: {$path}");
            }

            $sheets = Excel::toCollection(null, $path);

            DB::transaction(function () use ($sheets) {
                // Hard purge — fresh catalog every import
                DB::table('xlr8_vehicle_accessory_scopes')->delete();
                DB::table('xlr8_vehicle_accessories')->delete();

                foreach ($sheets as $sheetName => $collection) {
                    $type = $this->resolveSheetType((string) $sheetName);
                    if (! $type) {
                        $this->warnings[] = "Sheet '{$sheetName}' skipped (unknown type).";

                        continue;
                    }

                    if ($collection->isEmpty()) {
                        continue;
                    }

                    $headers = $collection->first()->toArray();
                    $collection->skip(1)->each(function ($row, $index) use ($headers, $type) {
                        $rowNo = $index + 2;
                        $assoc = $this->mapRowWithHeaders($row->toArray(), $headers);
                        $this->processRow($rowNo, $assoc, $type);
                    });
                }
            });
        } catch (Throwable $e) {
            $this->errors[] = $e->getMessage();
            Log::error('AccessoryService::importExcel failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $this->finishLog();

        $success = count($this->errors) === 0;

        return [
            'success' => $success,
            'message' => $success ? 'Import completed' : 'Import completed with errors',
            'total_records' => $this->total,
            'imported_count' => $this->imported,
            'skipped_count' => $this->skipped,
            'errors_count' => count($this->errors),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }

    protected function processRow(int $rowNo, array $row, string $type): void
    {
        $this->total++;

        try {
            $partNo = strtoupper(trim((string) $this->cell($row, [
                'part no.', 'part no', 'part_no', 'partno', 'PART NO.',
            ])));
            $item = trim((string) $this->cell($row, [
                'item name', 'item_name', 'item', 'ITEM NAME',
            ]));

            if ($partNo === '' || $item === '') {
                $this->skipped++;
                $this->warnings[] = "Row {$rowNo} [{$type}]: skipped (part/item missing)";

                return;
            }

            // Captured columns ONLY — no fallback to NDP / Set Qty / Retn % / etc.
            // MRP (ROUNDED) only; missing → 0
            $mrp = $this->toDecimal($this->cell($row, [
                'mrp (rounded)', 'MRP (ROUNDED)',
            ]));
            if ($mrp === null) {
                $mrp = 0.0;
            }

            // Discount column only; missing/invalid → 0
            $discount = $this->parseDiscountOnly(
                $this->cell($row, ['discount', 'Discount'])
            );

            $segRaw = (string) ($this->cell($row, ['segment', 'SEGMENT']) ?? '');
            $modelRaw = (string) ($this->cell($row, ['model', 'MODEL']) ?? '');
            $variantRaw = (string) ($this->cell($row, ['variant', 'Variant', 'VARIANT']) ?? '');
            // Permit only on GPS VLTD / RTO Tape; blank elsewhere → ANY
            $permitRaw = (string) ($this->cell($row, ['permit', 'Permit', 'PERMIT']) ?? '');

            Accessory::updateOrCreate(
                ['part_no' => $partNo],
                [
                    'type' => $type,
                    'display_name' => null,
                    'item' => $item,
                    'ndp' => null,
                    'mrp' => $mrp,
                    'set_qty' => 1,
                    'discount' => $discount,
                    'status' => 1,
                    'updated_by' => $this->userId,
                    'created_by' => $this->userId,
                ]
            );

            $scopes = $this->expandScopes($segRaw, $modelRaw, $variantRaw, $permitRaw, $rowNo);

            foreach ($scopes as $scope) {
                AccessoryScope::updateOrCreate(
                    [
                        'part_no' => $partNo,
                        'segment_code' => $scope['segment_code'],
                        'model_code' => $scope['model_code'],
                        'variant_code' => $scope['variant_code'],
                        'permit' => $scope['permit'],
                    ],
                    [
                        'status' => 1,
                        'updated_by' => $this->userId,
                        'created_by' => $this->userId,
                    ]
                );
            }

            $this->imported++;
        } catch (Throwable $e) {
            $this->errors[] = "Row {$rowNo} [{$type}]: ".$e->getMessage();
            Log::warning('AccessoryService processRow failed', [
                'row' => $rowNo,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Discount column ONLY — no fallback to Retn % or any other column.
     * Empty / formula / invalid → 0.
     * Values 1–100 treated as percent points → fraction.
     */
    protected function parseDiscountOnly(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim === '' || $trim[0] === '=' || preg_match('/#(DIV|VALUE|REF|N\/A|NAME|NUM|NULL)/i', $trim)) {
                return 0.0;
            }
            if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $trim)) {
                return 0.0;
            }
            $val = (float) $trim;
        } elseif (is_numeric($raw)) {
            $val = (float) $raw;
        } else {
            return 0.0;
        }

        if ($val > 1 && $val <= 100) {
            $val = $val / 100;
        }

        if (abs($val) > 10) {
            return 0.0;
        }

        return round($val, 4);
    }

    /**
     * Cartesian product of resolved segment × model × variant × permit.
     * ANY / empty → null (wildcard match on list).
     */
    protected function expandScopes(
        string $segments,
        string $models,
        string $variants,
        string $permits,
        int $rowNo
    ): array {
        $segCodes = $this->resolveSegments($segments, $rowNo);
        $mdlCodes = $this->resolveModels($models, $rowNo);
        $varCodes = $this->resolveVariants($variants, $rowNo);
        $permits = $this->resolvePermits($permits);

        $rows = [];
        foreach ($segCodes as $seg) {
            foreach ($mdlCodes as $mdl) {
                foreach ($varCodes as $var) {
                    foreach ($permits as $permit) {
                        $rows[] = [
                            'segment_code' => $seg,
                            'model_code' => $mdl,
                            'variant_code' => $var,
                            'permit' => $permit,
                        ];
                    }
                }
            }
        }

        return array_values(array_unique($rows, SORT_REGULAR));
    }

    protected function resolveSegments(string $raw, int $rowNo): array
    {
        if ($raw === '' || strtoupper(trim($raw)) === 'ANY' || strtoupper(trim($raw)) === 'ALL') {
            return [null];
        }

        $out = [];
        foreach ($this->splitList($raw) as $piece) {
            $key = strtoupper($piece);
            if (array_key_exists($key, $this->segmentAliases)) {
                $out[] = $this->segmentAliases[$key];

                continue;
            }
            $code = Segment::query()
                ->whereRaw('UPPER(code) = ?', [$key])
                ->orWhereRaw('UPPER(name) = ?', [$key])
                ->value('code');
            if ($code) {
                $out[] = strtoupper($code);
            } else {
                $this->warnings[] = "Row {$rowNo}: segment '{$piece}' not found → ANY";
                $out[] = null;
            }
        }

        return $out ?: [null];
    }

    protected function resolveModels(string $raw, int $rowNo): array
    {
        if ($raw === '' || strtoupper(trim($raw)) === 'ANY' || strtoupper(trim($raw)) === 'ALL') {
            return [null];
        }

        $out = [];
        foreach ($this->splitList($raw) as $piece) {
            $code = $this->matchModelCode($piece);
            if ($code) {
                $out[] = $code;
            } else {
                // Segment names sometimes appear in MODEL column (BEV, PERSONAL…) — treat as ANY quietly
                $upper = strtoupper(trim($piece));
                if (! isset($this->segmentAliases[$upper]) && ! in_array($upper, ['BEV', 'PV', 'CV', 'LMM'], true)) {
                    $this->warnings[] = "Row {$rowNo}: model '{$piece}' not found → ANY";
                }
                $out[] = null;
            }
        }

        return $out ?: [null];
    }

    protected function resolveVariants(string $raw, int $rowNo): array
    {
        if ($raw === '' || strtoupper(trim($raw)) === 'ANY' || strtoupper(trim($raw)) === 'ALL') {
            return [null];
        }

        $out = [];
        foreach ($this->splitList($raw) as $piece) {
            $key = strtoupper(preg_replace('/\s+/', '', $piece));
            $rec = Variant::query()
                ->where(function ($q) use ($key, $piece) {
                    $q->whereRaw("UPPER(REPLACE(code,' ','')) = ?", [$key])
                        ->orWhereRaw('UPPER(code) = ?', [strtoupper($piece)])
                        ->orWhereRaw("UPPER(REPLACE(COALESCE(display_name,''),' ','')) = ?", [$key])
                        ->orWhereRaw("UPPER(REPLACE(COALESCE(custom_name,''),' ','')) = ?", [$key])
                        ->orWhereRaw("UPPER(REPLACE(COALESCE(oem_name,''),' ','')) = ?", [$key]);
                })
                ->first();

            if ($rec) {
                $out[] = $rec->code;
            } else {
                $this->warnings[] = "Row {$rowNo}: variant '{$piece}' not found → ANY";
                $out[] = null;
            }
        }

        return $out ?: [null];
    }

    protected function resolvePermits(string $raw): array
    {
        // Blank / ANY / ALL → NULL (ANY) — same rule as segment/model/variant
        if ($raw === '' || in_array(strtoupper(trim($raw)), ['ANY', 'ALL'], true)) {
            return [null];
        }

        $out = [];
        foreach ($this->splitList($raw) as $piece) {
            if (in_array(strtoupper($piece), ['ANY', 'ALL', ''], true)) {
                $out[] = null;

                continue;
            }
            $out[] = Str::title(trim($piece)); // Passenger, Goods
        }

        return $out ?: [null];
    }

    /**
     * Match Excel model label to xlr8_vehicle_model.code.
     *
     * Live table columns: code, name, oem_name (NO custom_name).
     * Codes may contain spaces (e.g. "XUV 3XO", "THAR ROXX", "BOLERO NEO").
     */
    protected function matchModelCode(string $piece): ?string
    {
        $piece = trim($piece);
        if ($piece === '') {
            return null;
        }

        // Common Excel → master code aliases (0 vs O, spacing, marketing names)
        $aliases = [
            'BE 6' => 'BE6',
            'BE6' => 'BE6',
            'XEV 9E' => 'XEV9E',
            'XEV9E' => 'XEV9E',
            'XEV 9S' => 'XEV9S',
            'XEV9S' => 'XEV9S',
            'XUV 3X0' => 'XUV 3XO',
            'XUV3X0' => 'XUV 3XO',
            'XUV 3XO' => 'XUV 3XO',
            'XUV3XO' => 'XUV 3XO',
            'XUV 3XO EV' => 'XUV3XO EV',
            'XUV3XOEV' => 'XUV3XO EV',
            'XUV 400' => 'XUV400',
            'XUV400' => 'XUV400',
            'XUV 700' => 'XUV700',
            'XUV700' => 'XUV700',
            'XUV 7XO' => 'XUV7XO',
            'XUV7XO' => 'XUV7XO',
            'XUV 9S' => 'XUV9S', // may not exist — fall through to DB
            'THAR ROXX' => 'THAR ROXX',
            'THARROXX' => 'THAR ROXX',
            'SCORPIO N' => 'SCORPIO-N',
            'SCORPIO-N' => 'SCORPIO-N',
            'SCORPION' => 'SCORPIO-N',
            'SCORPIO CLASSIC' => 'SCORPIO',
            'SCORPIOCLASSIC' => 'SCORPIO',
            'BOLERO NEO' => 'BOLERO NEO',
            'BOLERONEO' => 'BOLERO NEO',
            'MAXX HD' => 'MAXX HD',
            'MAXX CITY' => 'MAXX CITY',
        ];

        $upperKey = strtoupper($piece);
        if (isset($aliases[$upperKey])) {
            $piece = $aliases[$upperKey];
        }

        $normalized = strtoupper(preg_replace('/[\s\-_]+/', '', $piece));

        // 1) Exact / normalized match on code
        $row = VehicleModel::query()
            ->where(function ($q) use ($piece, $normalized) {
                $q->whereRaw('UPPER(code) = ?', [strtoupper($piece)])
                    ->orWhereRaw("UPPER(REPLACE(REPLACE(code,' ',''),'-','')) = ?", [$normalized]);
            })
            ->first();
        if ($row) {
            return $row->code;
        }

        // 2) name / oem_name only (schema has no custom_name)
        $row = VehicleModel::query()
            ->where(function ($q) use ($piece, $normalized) {
                $q->whereRaw('UPPER(name) = ?', [strtoupper($piece)])
                    ->orWhereRaw("UPPER(REPLACE(REPLACE(COALESCE(name,''),' ',''),'-','')) = ?", [$normalized])
                    ->orWhereRaw('UPPER(COALESCE(oem_name,\'\')) = ?', [strtoupper($piece)])
                    ->orWhereRaw("UPPER(REPLACE(REPLACE(COALESCE(oem_name,''),' ',''),'-','')) = ?", [$normalized]);
            })
            ->first();
        if ($row) {
            return $row->code;
        }

        // 3) Loose contains on code/name (min length 4 to avoid noise)
        if (strlen($normalized) >= 4) {
            $row = VehicleModel::query()
                ->where(function ($q) use ($normalized) {
                    $q->whereRaw("UPPER(REPLACE(REPLACE(code,' ',''),'-','')) LIKE ?", ['%'.$normalized.'%'])
                        ->orWhereRaw("UPPER(REPLACE(REPLACE(COALESCE(name,''),' ',''),'-','')) LIKE ?", ['%'.$normalized.'%']);
                })
                ->first();
            if ($row) {
                return $row->code;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // LIST / FETCH
    // ─────────────────────────────────────────────────────────────

    /**
     * List accessories.
     *
     * MANDATORY filters: segment, model, variant, permit (keys must be present).
     * Allowed combinations ONLY:
     *   1) ALL four = ANY  → full catalog for the requested type(s)
     *   2) ALL four = concrete values → specific vehicle + permit combo
     * Mixed (some ANY, some concrete) is rejected.
     *
     * Stored scope NULL = ANY → matches any concrete fetch value at that level.
     * Hierarchy: segment → model → variant; ANY on a parent means all children.
     *
     * @param  array{
     *   type?: string|array,
     *   segment: string,   // code/name or ANY
     *   model: string,
     *   variant: string,
     *   permit: string,    // Passenger|Goods|ANY
     *   active_only?: bool
     * }  $filters
     * @return array<int, array<string, mixed>>
     *
     * @throws \InvalidArgumentException
     */
    public function list(array $filters = []): array
    {
        $this->assertScopeFilterContract($filters);

        $types = $this->normalizeTypeFilter($filters['type'] ?? 'all');
        $activeOnly = $filters['active_only'] ?? true;

        $segment = $this->normalizeSegmentFilter($filters['segment']);
        $model = $this->normalizeModelFilter($filters['model']);
        $variant = $this->normalizeVariantFilter($filters['variant']);
        $permit = $this->normalizePermitFilter($filters['permit']);

        $allAny = ($segment === null && $model === null && $variant === null && $permit === null);

        $query = Accessory::query()
            ->with(['scopes' => function ($q) use ($activeOnly) {
                if ($activeOnly) {
                    $q->where('status', 1)->whereNull('deleted_at');
                }
            }])
            ->whereIn('type', $types);

        if ($activeOnly) {
            $query->where('status', 1)->whereNull('deleted_at');
        }

        if (! $allAny) {
            // Specific vehicle+permit: match exact code OR NULL (ANY) on each scope column
            $query->whereHas('scopes', function ($q) use ($segment, $model, $variant, $permit, $activeOnly) {
                if ($activeOnly) {
                    $q->where('status', 1)->whereNull('deleted_at');
                }

                $q->where(function ($sq) use ($segment) {
                    $sq->where('segment_code', $segment)->orWhereNull('segment_code');
                });
                $q->where(function ($sq) use ($model) {
                    $sq->where('model_code', $model)->orWhereNull('model_code');
                });
                $q->where(function ($sq) use ($variant) {
                    $sq->where('variant_code', $variant)->orWhereNull('variant_code');
                });
                $q->where(function ($sq) use ($permit) {
                    $sq->where('permit', $permit)->orWhereNull('permit');
                });
            });
        } else {
            // ALL ANY: any accessory that has at least one active scope
            $query->whereHas('scopes', function ($q) use ($activeOnly) {
                if ($activeOnly) {
                    $q->where('status', 1)->whereNull('deleted_at');
                }
            });
        }

        $accessories = $query
            ->orderBy('type')
            ->orderBy('item')
            ->orderBy('part_no')
            ->get();

        $rows = [];
        foreach ($accessories as $acc) {
            $matchedScopes = $acc->scopes->filter(function ($s) use ($allAny, $segment, $model, $variant, $permit) {
                if ($allAny) {
                    return true;
                }
                if ($s->segment_code !== null && strtoupper((string) $s->segment_code) !== $segment) {
                    return false;
                }
                if ($s->model_code !== null && strtoupper((string) $s->model_code) !== $model) {
                    return false;
                }
                if ($s->variant_code !== null && strtoupper((string) $s->variant_code) !== strtoupper((string) $variant)) {
                    return false;
                }
                if ($s->permit !== null && $s->permit !== '' && $s->permit !== $permit) {
                    return false;
                }

                return true;
            });

            if ($matchedScopes->isEmpty()) {
                continue;
            }

            foreach ($matchedScopes as $scope) {
                $rows[] = $this->formatListRow($acc, $scope);
            }
        }

        return $rows;
    }

    /**
     * Enforce: segment, model, variant, permit keys required;
     * either ALL are ANY or ALL are concrete — no mixed mode.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertScopeFilterContract(array $filters): void
    {
        foreach (['segment', 'model', 'variant', 'permit'] as $key) {
            if (! array_key_exists($key, $filters)) {
                throw new \InvalidArgumentException(
                    "Accessory fetch requires '{$key}'. Pass ANY for catalog-wide listing, or a concrete value for a vehicle+permit combo."
                );
            }
        }

        $flags = [
            'segment' => $this->isAnyToken($filters['segment']),
            'model' => $this->isAnyToken($filters['model']),
            'variant' => $this->isAnyToken($filters['variant']),
            'permit' => $this->isAnyToken($filters['permit']),
        ];

        $anyCount = count(array_filter($flags));
        if ($anyCount !== 0 && $anyCount !== 4) {
            $detail = collect($flags)
                ->map(fn ($isAny, $k) => $k.'='.($isAny ? 'ANY' : 'concrete'))
                ->implode(', ');
            throw new \InvalidArgumentException(
                "Invalid scope combination ({$detail}). "
                .'Either set ALL of segment/model/variant/permit to ANY, '
                .'or provide concrete values for ALL four. Mixed combinations are not allowed.'
            );
        }
    }

    protected function isAnyToken(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        $v = strtoupper(trim((string) $value));

        return $v === '' || in_array($v, ['ANY', 'ALL', '*'], true);
    }

    protected function normalizeVariantFilter(mixed $variant): ?string
    {
        if ($this->isAnyToken($variant)) {
            return null;
        }
        $v = trim((string) $variant);

        return $this->matchVariantCode($v) ?? strtoupper($v);
    }

    protected function normalizePermitFilter(mixed $permit): ?string
    {
        if ($this->isAnyToken($permit)) {
            return null;
        }

        return Str::title(trim((string) $permit));
    }

    /**
     * Full bundle for a vehicle (excludes RTO_Tape + Kazam).
     * All four scope args are required (use 'ANY' for catalog-wide).
     */
    public function listForVehicle(
        string $segment,
        string $model,
        string $variant,
        string $permit
    ): array {
        return $this->list([
            'type' => 'all',
            'segment' => $segment,
            'model' => $model,
            'variant' => $variant,
            'permit' => $permit,
        ]);
    }

    /**
     * Explicit single-type fetch (use for RTO_Tape / Kazam).
     */
    public function listByType(
        string $type,
        string $segment,
        string $model,
        string $variant,
        string $permit
    ): array {
        return $this->list([
            'type' => $type,
            'segment' => $segment,
            'model' => $model,
            'variant' => $variant,
            'permit' => $permit,
        ]);
    }

    protected function formatListRow(Accessory $acc, $scope = null): array
    {
        return [
            'type' => $acc->type,
            'part_no' => $acc->part_no,
            'item' => $acc->item,
            'display_name' => $acc->display_name,
            'ndp' => $acc->ndp !== null ? (float) $acc->ndp : null,
            'mrp' => $acc->mrp !== null ? (float) $acc->mrp : null,
            'set_qty' => (int) $acc->set_qty,
            'discount' => $acc->discount !== null ? (float) $acc->discount : null,
            'segment_code' => $scope->segment_code ?? null,
            'model_code' => $scope->model_code ?? null,
            'variant_code' => $scope->variant_code ?? null,
            'permit' => $scope->permit ?? null,
            'status' => (int) $acc->status,
        ];
    }

    protected function normalizeTypeFilter(string|array $type): array
    {
        if (is_array($type)) {
            return array_values(array_intersect($type, Accessory::ALL_TYPES)) ?: Accessory::BUNDLE_TYPES;
        }

        $t = strtolower(trim($type));

        return match ($t) {
            'all', 'bundle', '' => Accessory::BUNDLE_TYPES,
            'onlyacc', 'only_acc', 'accessory' => [Accessory::TYPE_ACCESSORY],
            'ceramic' => [Accessory::TYPE_CERAMIC],
            'ppf' => [Accessory::TYPE_PPF],
            'maxicare' => [Accessory::TYPE_MAXICARE],
            'gps_vltd', 'gps vltd', 'gpsvltd' => [Accessory::TYPE_GPS_VLTD],
            'rto_tape', 'rto tape', 'rtotape' => [Accessory::TYPE_RTO_TAPE],
            'kazam' => [Accessory::TYPE_KAZAM],
            default => in_array($type, Accessory::ALL_TYPES, true)
                ? [$type]
                : Accessory::BUNDLE_TYPES,
        };
    }

    protected function normalizeSegmentFilter(mixed $segment): ?string
    {
        if ($this->isAnyToken($segment)) {
            return null;
        }
        $key = strtoupper(trim((string) $segment));
        if (array_key_exists($key, $this->segmentAliases)) {
            return $this->segmentAliases[$key]; // may be null for ANY alias — treated as ANY
        }
        $code = Segment::query()
            ->whereRaw('UPPER(code) = ?', [$key])
            ->orWhereRaw('UPPER(name) = ?', [$key])
            ->value('code');

        return $code ? strtoupper($code) : $key;
    }

    protected function normalizeModelFilter(mixed $model): ?string
    {
        if ($this->isAnyToken($model)) {
            return null;
        }
        $m = trim((string) $model);

        return $this->matchModelCode($m) ?? strtoupper(preg_replace('/[\s\-_]+/', '', $m));
    }

    protected function matchVariantCode(string $piece): ?string
    {
        $key = strtoupper(preg_replace('/\s+/', '', $piece));

        return Variant::query()
            ->where(function ($q) use ($key, $piece) {
                $q->whereRaw("UPPER(REPLACE(code,' ','')) = ?", [$key])
                    ->orWhereRaw('UPPER(code) = ?', [strtoupper(trim($piece))]);
            })
            ->value('code');
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────────────────────

    /**
     * Flat rows suitable for Excel export (one row per accessory×scope).
     */
    public function exportRows(array $filters = [], bool $activeFirst = true): array
    {
        $types = $this->normalizeTypeFilter($filters['type'] ?? 'all');
        // For export, allow including RTO/Kazam when type=all_including or explicit
        if (($filters['type'] ?? '') === 'all_with_special') {
            $types = Accessory::ALL_TYPES;
        }

        $activeOnly = ! empty($filters['active_only']);

        $query = Accessory::query()
            ->with(['scopes' => function ($q) use ($activeOnly, $activeFirst) {
                if ($activeOnly) {
                    $q->where('status', 1);
                }
                if ($activeFirst) {
                    $q->orderByDesc('status');
                }
                $q->orderBy('segment_code')->orderBy('model_code')->orderBy('variant_code');
            }])
            ->whereIn('type', $types);

        if ($activeOnly) {
            $query->where('status', 1);
        }

        if (! empty($filters['part_no'])) {
            $query->where('part_no', 'like', '%'.trim($filters['part_no']).'%');
        }

        $accessories = $query->orderBy('type')->orderBy('item')->orderBy('part_no')->get();

        $rows = [];
        foreach ($accessories as $acc) {
            $scopes = $acc->scopes;
            if ($scopes->isEmpty()) {
                $rows[] = $this->mapExportRow($acc, null);

                continue;
            }
            foreach ($scopes as $scope) {
                $rows[] = $this->mapExportRow($acc, $scope);
            }
        }

        return $rows;
    }

    protected function mapExportRow(Accessory $acc, $scope = null): array
    {
        $status = ((int) $acc->status === 1 && (! $scope || (int) $scope->status === 1))
            ? 'ACTIVE'
            : 'INACTIVE';

        return [
            'TYPE' => $acc->type,
            'SEGMENT' => $scope->segment_code ?? '',
            'MODEL' => $scope->model_code ?? '',
            'Variant' => $scope->variant_code ?? '',
            'Permit' => $scope->permit ?? '',
            'DISPLAY NAME' => (string) ($acc->display_name ?? ''),
            'ITEM NAME' => (string) $acc->item,
            'PART NO.' => (string) $acc->part_no,
            'Set Qty' => (int) $acc->set_qty,
            'NDP' => $acc->ndp,
            'MRP (ROUNDED)' => $acc->mrp,
            'Discount' => $acc->discount,
            'STATUS' => $status,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    protected function resolveSheetType(string $sheetName): ?string
    {
        // Excel may pass numeric index when using toCollection — try title if available
        $key = strtolower(trim($sheetName));
        if (isset($this->sheetTypeMap[$key])) {
            return $this->sheetTypeMap[$key];
        }

        // Numeric index: map by order if sheet name is "0","1",...
        // Caller should prefer named sheets; fallback null
        return null;
    }

    /**
     * When Maatwebsite returns numeric sheet keys, map by position.
     * Order matches the Accessories.xlsx workbook.
     */
    public function importExcelWithSheetOrder(string $path, int $userId = 1): array
    {
        $this->resetCounters();
        $this->userId = $userId;
        $this->startLog($userId, basename($path));

        $orderMap = [
            0 => Accessory::TYPE_ACCESSORY,
            1 => Accessory::TYPE_MAXICARE,
            2 => Accessory::TYPE_CERAMIC,
            3 => Accessory::TYPE_PPF,
            4 => Accessory::TYPE_GPS_VLTD,
            5 => Accessory::TYPE_RTO_TAPE,
            6 => Accessory::TYPE_KAZAM,
        ];

        try {
            if (! is_file($path)) {
                throw new \InvalidArgumentException("File not found: {$path}");
            }

            $sheets = Excel::toCollection(null, $path);

            DB::transaction(function () use ($sheets, $orderMap) {
                DB::table('xlr8_vehicle_accessory_scopes')->delete();
                DB::table('xlr8_vehicle_accessories')->delete();

                $index = 0;
                foreach ($sheets as $sheetName => $collection) {
                    $type = $this->resolveSheetType((string) $sheetName)
                        ?? ($orderMap[$index] ?? null);
                    $index++;

                    if (! $type || $collection->isEmpty()) {
                        if (! $type) {
                            $this->warnings[] = "Sheet index/name '{$sheetName}' skipped.";
                        }

                        continue;
                    }

                    $headers = $collection->first()->toArray();
                    $collection->skip(1)->each(function ($row, $rowIndex) use ($headers, $type) {
                        $assoc = $this->mapRowWithHeaders($row->toArray(), $headers);
                        $this->processRow($rowIndex + 2, $assoc, $type);
                    });
                }
            });
        } catch (Throwable $e) {
            $this->errors[] = $e->getMessage();
            Log::error('AccessoryService::importExcelWithSheetOrder failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->finishLog();

        $success = count($this->errors) === 0;

        return [
            'success' => $success,
            'message' => $success ? 'Import completed' : 'Import completed with errors',
            'total_records' => $this->total,
            'imported_count' => $this->imported,
            'skipped_count' => $this->skipped,
            'errors_count' => count($this->errors),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }

    protected function mapRowWithHeaders(array $rowData, array $headers): array
    {
        $mapped = [];
        foreach ($rowData as $i => $value) {
            $header = trim((string) ($headers[$i] ?? "col_{$i}"));
            $mapped[strtolower($header)] = $value;
            $mapped[strtoupper($header)] = $value;
            $mapped[$header] = $value;
        }

        return $mapped;
    }

    protected function cell(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
            $lower = strtolower($key);
            if (array_key_exists($lower, $row) && $row[$lower] !== null && $row[$lower] !== '') {
                return $row[$lower];
            }
        }

        return null;
    }

    protected function splitList(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;|]+/', $raw))));
    }

    protected function toDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return round((float) $v, 4);
        }
        if (! is_string($v)) {
            return null;
        }
        $trim = trim($v);
        // Never strip digits out of formulas (=A1*B1) or Excel errors
        if ($trim === '' || $trim[0] === '=' || preg_match('/#(DIV|VALUE|REF|N\/A|NAME|NUM|NULL)/i', $trim)) {
            return null;
        }
        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $trim)) {
            return null;
        }

        return round((float) $trim, 4);
    }

    protected function resetCounters(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->total = 0;
        $this->imported = 0;
        $this->skipped = 0;
        $this->log = null;
    }

    protected function startLog(int $userId, string $filename): void
    {
        if (! class_exists(ImportLog::class)) {
            return;
        }
        try {
            $this->log = ImportLog::forceCreate([
                'user_id' => $userId,
                'filename' => $filename,
                'import_type' => 'vehicle_accessories',
                'status' => 'processing',
                'started_at' => now(),
                'warnings' => ['module' => 'vehicle_accessories'],
            ]);
        } catch (Throwable) {
            $this->log = null;
        }
    }

    protected function finishLog(): void
    {
        if (! $this->log) {
            return;
        }
        try {
            $this->log->forceFill([
                'total_records' => $this->total,
                'imported_count' => $this->imported,
                'skipped_count' => $this->skipped,
                'errors_count' => count($this->errors),
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'status' => count($this->errors) ? 'partial' : 'success',
                'completed_at' => now(),
                'duration_seconds' => optional($this->log->started_at)->diffInSeconds(now()),
            ])->save();
        } catch (Throwable) {
            // ignore log failures
        }
    }
}
