<?php

/**
 * Path: app/Services/Vehicle/Pricing/PriceListVehicleDetector.php
 *
 * Stage 1 — Price List sheets only.
 * - Profile + change_flag per OEM Code
 * - Stub master via VehicleService::createStubFromPriceList (new only)
 * - Does NOT invent Fuel / Wheels / Permit
 *
 * Pricing profile.model_code = OEM Code = variant.code
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PriceListVehicleDetector
{
    protected const BULK_SIZE = 200;

    public function __construct(
        protected SheetHeaderService $headers,
        protected VehicleService $vehicles
    ) {}

    public static function sheetCodeFromTitle(string $title): ?string
    {
        $t = mb_strtoupper(trim($title));
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        $exact = [
            'PRICE LIST PV' => 'PRICE_LIST_PV',
            'PRICE LIST CV' => 'PRICE_LIST_CV',
            'PRICE LIST BEV' => 'PRICE_LIST_BEV',
            'PRICE LIST LMM TZU' => 'PRICE_LIST_LMM_TZU',
            'PRICE LIST TZU' => 'PRICE_LIST_LMM_TZU',
            'LMM TZU' => 'PRICE_LIST_LMM_TZU',
            'PRICE LIST LMM' => 'PRICE_LIST_LMM',
            'LMM' => 'PRICE_LIST_LMM',
            'PRICE LIST CSD' => 'PRICE_LIST_CSD',
            'CSD' => 'PRICE_LIST_CSD',
        ];
        if (isset($exact[$t])) {
            return $exact[$t];
        }
        // "CSD Index Codes" and similar lookup/reference sheets are not price
        // lists, but would otherwise false-positive against the substring
        // fallbacks below (e.g. containing "CSD").
        if (str_contains($t, 'INDEX')) {
            return null;
        }
        if (str_contains($t, 'TZU')) {
            return 'PRICE_LIST_LMM_TZU';
        }
        if (str_contains($t, 'PRICE LIST') && str_contains($t, 'PV') && ! str_contains($t, 'CV')) {
            return 'PRICE_LIST_PV';
        }
        if (str_contains($t, 'PRICE LIST') && str_contains($t, 'CV')) {
            return 'PRICE_LIST_CV';
        }
        if (str_contains($t, 'BEV')) {
            return 'PRICE_LIST_BEV';
        }
        if (str_contains($t, 'LMM')) {
            return 'PRICE_LIST_LMM';
        }
        if (str_contains($t, 'CSD')) {
            return 'PRICE_LIST_CSD';
        }

        return null;
    }

    /**
     * @param  list<string>  $selectedSheetCodes
     * @param  null|callable(array):void  $onProgress
     * @return array{fresh: list<string>, known: list<string>, created: int, masters_created: int, codes_seen: int}
     */
    public function detectFromFile(
        string $absolutePath,
        ImportSession $session,
        array $selectedSheetCodes,
        ?int $userId = null,
        ?callable $onProgress = null
    ): array {
        $userId = $userId ?? Auth::id();
        $selectedSheetCodes = array_map('strtoupper', $selectedSheetCodes);

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $plog = new PricingProcessLogger($session->id);
        $plog->info('Detect start', [
            'file' => $absolutePath,
            'sheets' => $selectedSheetCodes,
        ]);

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $plog->info('Loading spreadsheet…');
        $spreadsheet = $reader->load($absolutePath);
        $plog->info('Spreadsheet loaded');

        $worksheetsToProcess = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $title = $worksheet->getTitle();
            $sheetCode = self::sheetCodeFromTitle($title);
            $plog->debug('Worksheet seen', [
                'title' => $title,
                'mapped_code' => $sheetCode,
                'highest_row' => $worksheet->getHighestDataRow(),
            ]);
            if ($sheetCode !== null && in_array($sheetCode, $selectedSheetCodes, true)) {
                $worksheetsToProcess[] = [
                    'title' => $title,
                    'sheetCode' => $sheetCode,
                    'worksheet' => $worksheet,
                ];
            }
        }

        $totalSheets = count($worksheetsToProcess);
        $plog->info('Worksheets selected', [
            'count' => $totalSheets,
            'titles' => array_column($worksheetsToProcess, 'title'),
        ]);

        $existingCodes = DB::table('xlr8_vehicle_pricing_profile')
            ->pluck('model_code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->flip()
            ->all();
        $plog->info('Existing profiles loaded', ['count' => count($existingCodes)]);

        $fresh = [];
        $known = [];
        $created = 0;
        $mastersCreated = 0;
        $pendingBulk = [];

        $flushBulk = function () use (
            &$pendingBulk, &$created, &$mastersCreated, $session, $userId, $plog, $onProgress
        ) {
            if ($pendingBulk === []) {
                return;
            }
            $count = $this->bulkInsertFresh($pendingBulk, $session, $userId);
            $created += $count;
            $mc = $this->ensureStubs($pendingBulk, $userId, $plog);
            $mastersCreated += $mc;
            $last = end($pendingBulk);
            $plog->info('Bulk insert', [
                'profiles' => $count,
                'stubs' => $mc,
                'last' => $last['oem_code'] ?? null,
            ]);
            if ($onProgress) {
                $onProgress([
                    'phase' => 'detect',
                    'last_code' => $last['oem_code'] ?? null,
                    'logs' => [
                        $this->ts()." Bulk profiles {$count}, stubs {$mc} (last: ".($last['oem_code'] ?? '-').')',
                    ],
                ]);
            }
            $pendingBulk = [];
        };

        foreach ($worksheetsToProcess as $idx => $item) {
            $title = $item['title'];
            $sheetCode = $item['sheetCode'];
            /** @var Worksheet $worksheet */
            $worksheet = $item['worksheet'];
            $sheetNum = $idx + 1;
            $highestRow = (int) $worksheet->getHighestDataRow();
            $highestCol = $worksheet->getHighestDataColumn();

            $plog->info('Sheet begin', [
                'title' => $title, 'sheet_code' => $sheetCode, 'highest_row' => $highestRow,
            ]);

            if ($onProgress) {
                $onProgress([
                    'phase' => 'detect',
                    'message' => "Detecting: {$title}",
                    'percent' => min(14, 2 + (int) round(($idx / max($totalSheets, 1)) * 12)),
                    'sheet' => $title,
                    'processed' => $sheetNum,
                    'total' => $totalSheets,
                    'logs' => [$this->ts()." Loading sheet {$title} ({$sheetNum}/{$totalSheets}) rows≈{$highestRow}…"],
                ]);
            }

            $headerMatrix = [];
            $scanLimit = min(30, $highestRow);
            for ($r = 1; $r <= $scanLimit; $r++) {
                $headerMatrix[] = $this->readRow($worksheet, $r, $highestCol);
            }

            [$headerIdx, $fieldMap] = $this->resolveHeader($sheetCode, $headerMatrix, $plog, $title);
            $oemCodeCol = $fieldMap['model_code'] ?? $fieldMap['oem_code'] ?? null;
            if ($headerIdx === null || $oemCodeCol === null) {
                $plog->warning('header/oem_code missing — skipping sheet', [
                    'sheet' => $title, 'sheet_code' => $sheetCode,
                ]);
                if ($onProgress) {
                    $onProgress([
                        'phase' => 'detect',
                        'logs' => [$this->ts()." WARN: {$title} — OEM Code / Model Code header missing, skipped"],
                    ]);
                }

                continue;
            }

            $oemModelCol = $fieldMap['oem_model'] ?? null;
            $oemVariantCol = $fieldMap['oem_variant'] ?? null;
            $segment = $this->vehicles->segmentFromSheetTitle($title);
            $dataStart = $headerIdx + 2;
            $sheetFresh = 0;
            $sheetKnown = 0;

            $plog->info('Header OK', [
                'sheet' => $title,
                'header_row' => $headerIdx,
                'oem_code_col' => $oemCodeCol,
                'segment' => $segment,
            ]);

            for ($r = $dataStart; $r <= $highestRow; $r++) {
                if ($r === $dataStart || $r % 500 === 0 || $r === $highestRow) {
                    if ($onProgress) {
                        $onProgress([
                            'phase' => 'detect',
                            'message' => "{$title}: row {$r}/{$highestRow}",
                            'sheet' => $title,
                            'processed' => $sheetNum,
                            'total' => $totalSheets,
                            'logs' => [$this->ts()." {$title} scanning row {$r}/{$highestRow} (fresh={$sheetFresh}, known={$sheetKnown})"],
                        ]);
                    }
                }

                $rawCode = $worksheet->getCellByColumnAndRow($oemCodeCol + 1, $r)?->getValue();
                if ($rawCode === null || $rawCode === '') {
                    continue;
                }
                $oemCode = strtoupper(preg_replace('/\s+/', '', (string) $rawCode) ?? (string) $rawCode);
                if ($oemCode === '') {
                    continue;
                }
                if (isset($fresh[$oemCode]) || isset($known[$oemCode])) {
                    continue;
                }

                if (isset($existingCodes[$oemCode])) {
                    $known[$oemCode] = true;
                    $sheetKnown++;

                    continue;
                }

                $oemModel = '';
                $oemVariant = '';
                if ($oemModelCol !== null) {
                    $v = $worksheet->getCellByColumnAndRow($oemModelCol + 1, $r)?->getValue();
                    $oemModel = $v !== null && $v !== '' ? strtoupper(trim((string) $v)) : '';
                }
                if ($oemVariantCol !== null) {
                    $v = $worksheet->getCellByColumnAndRow($oemVariantCol + 1, $r)?->getValue();
                    $oemVariant = $v !== null && $v !== '' ? strtoupper(trim((string) $v)) : '';
                }

                $pendingBulk[] = [
                    'oem_code' => $oemCode,
                    'oem_model' => $oemModel,
                    'oem_variant' => $oemVariant,
                    'segment' => $segment,
                    'sheet_title' => $title,
                    'color_code' => $this->vehicles->colorFromOemCode($oemCode),
                ];
                $fresh[$oemCode] = true;
                $existingCodes[$oemCode] = true;
                $sheetFresh++;

                if (count($pendingBulk) >= self::BULK_SIZE) {
                    $flushBulk();
                    if ($onProgress) {
                        $onProgress([
                            'phase' => 'detect',
                            'message' => "{$title}: {$sheetFresh} new, {$sheetKnown} known",
                            'sheet' => $title,
                            'last_code' => $oemCode,
                            'logs' => [$this->ts()." {$title} — Fresh so far {$sheetFresh} (last: {$oemCode})"],
                        ]);
                    }
                }
            }

            $flushBulk();
            $plog->info('Sheet detect summary', [
                'sheet' => $title, 'fresh' => $sheetFresh, 'known' => $sheetKnown,
            ]);
            if ($onProgress) {
                $onProgress([
                    'phase' => 'detect',
                    'message' => "{$title}: fresh={$sheetFresh}, known={$sheetKnown}",
                    'logs' => [$this->ts()." {$title} done — fresh={$sheetFresh}, known={$sheetKnown}"],
                ]);
            }
            unset($worksheet);
        }

        $result = [
            'fresh' => array_keys($fresh),
            'known' => array_keys($known),
            'created' => $created,
            'masters_created' => $mastersCreated,
            'codes_seen' => count($fresh) + count($known),
        ];

        $plog->info('Detect done', $result);
        Log::info('[PriceListVehicleDetector] done', [
            'session_id' => $session->id,
            'created' => $created,
            'stubs' => $mastersCreated,
            'fresh' => count($fresh),
            'known' => count($known),
        ]);

        return $result;
    }

    /**
     * @param  list<array{oem_code:string,oem_model:string,oem_variant:string,segment:string,sheet_title:string,color_code:string}>  $rows
     */
    protected function ensureStubs(array $rows, ?int $userId, PricingProcessLogger $plog): int
    {
        $created = 0;
        foreach ($rows as $r) {
            if (($r['oem_model'] ?? '') === '') {
                $plog->warning('Stub skipped — OEM Model blank', ['oem_code' => $r['oem_code']]);

                continue;
            }
            try {
                $out = $this->vehicles->createStubFromPriceList(
                    $r['oem_code'],
                    $r['oem_model'],
                    $r['oem_variant'] ?? '',
                    $r['sheet_title'] ?? $r['segment'],
                    $userId
                );
                if ($out['created']) {
                    $created++;
                }
            } catch (\Throwable $e) {
                $plog->warning('Stub create failed', [
                    'oem_code' => $r['oem_code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * @param  list<array{oem_code:string,oem_model:string,oem_variant:string,segment:string,color_code:string}>  $rows
     */
    protected function bulkInsertFresh(array $rows, ImportSession $session, ?int $userId): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $profiles = [];
        $flags = [];

        foreach ($rows as $r) {
            $profiles[] = [
                'model_code' => $r['oem_code'],
                'import_session_id' => $session->id,
                'variant_code' => $r['oem_code'],
                'segment' => $r['segment'],
                'is_vehicle_master_complete' => 0,
                'is_pricing_template_complete' => 0,
                'is_rule_profile_complete' => 0,
                'is_publishable' => 0,
                'is_disabled' => 1,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $flags[] = [
                'import_session_id' => $session->id,
                'segment' => $r['segment'],
                'change_type' => 'vehicle_master',
                'model_code' => $r['oem_code'],
                'variant_code' => $r['oem_code'],
                'field_name' => 'new_vehicle',
                'old_value' => null,
                'new_value' => json_encode([
                    'oem_model' => $r['oem_model'] ?? null,
                    'oem_variant' => $r['oem_variant'] ?? null,
                    'color_code' => $r['color_code'] ?? '',
                ], JSON_UNESCAPED_UNICODE),
                'is_processed' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($profiles, $flags) {
            DB::table('xlr8_vehicle_pricing_profile')->insertOrIgnore($profiles);
            DB::table('xlr8_vehicle_pricing_change_flags')->insertOrIgnore($flags);
        });

        return count($profiles);
    }

    protected function readRow(Worksheet $worksheet, int $row1Based, string $highestCol): array
    {
        $range = 'A'.$row1Based.':'.$highestCol.$row1Based;
        $rowData = $worksheet->rangeToArray($range, null, true, true, false);

        return $rowData[0] ?? [];
    }

    protected function resolveHeader(string $sheetCode, array $matrix, PricingProcessLogger $plog, string $title): array
    {
        [$headerIdx, $fieldMap] = $this->headers->findHeaderRow($sheetCode, $matrix, 30);

        if (($headerIdx === null || (! isset($fieldMap['model_code']) && ! isset($fieldMap['oem_code'])))
            && in_array($sheetCode, ['PRICE_LIST_LMM', 'PRICE_LIST_LMM_TZU', 'PRICE_LIST_BEV', 'PRICE_LIST_CSD'], true)
        ) {
            [$headerIdx, $fieldMap] = $this->headers->findHeaderRow('PRICE_LIST_PV', $matrix, 30);
        }

        if ($headerIdx === null || (! isset($fieldMap['model_code']) && ! isset($fieldMap['oem_code']))) {
            $tryMap = $this->headers->mapHeaderRow($sheetCode, $matrix[0] ?? []);
            if (isset($tryMap['model_code']) || isset($tryMap['oem_code'])) {
                $headerIdx = 0;
                $fieldMap = $tryMap;
            }
        }

        if ($headerIdx === null || (! isset($fieldMap['model_code']) && ! isset($fieldMap['oem_code']))) {
            $plog->warning('header/oem_code missing', ['sheet' => $title, 'sheet_code' => $sheetCode]);
            $plog->dumpSheetPreview($title, $sheetCode, $matrix, 5);

            return [null, []];
        }

        return [$headerIdx, $fieldMap];
    }

    protected function ts(): string
    {
        return '['.now()->format('H:i:s').']';
    }
}
