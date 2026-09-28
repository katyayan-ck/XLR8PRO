<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Variant;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Step 2 — Detect (DEC-073). Reads only the selected Price List sheets (never the retired PV / CV Vehicle sheets) and
 * makes sure every OEM code is a vehicle:
 *  - known  = a variant with that full OEM code already exists (the variant master, not the old profile table);
 *  - new    = a FRESH INCOMPLETE stub (OEM code, OEM model, OEM variant, colour = last 2 chars; LMM TZU colour NA);
 *  - CSD    = never creates vehicles (CSD rows are PV / BEV / CV vehicles sold through CSD); unknown codes are reported;
 *  - duplicate codes within a sheet are counted once and reported.
 * Run it inside PricingSessionService::record() so Discard can remove the stubs.
 */
class PriceListDetectService
{
    /** sheet code => the stub segment (null = never create) */
    public const SHEETS = [
        'PRICE_LIST_PV' => 'PV', 'PRICE_LIST_CV' => 'CV', 'PRICE_LIST_BEV' => 'BEV',
        'PRICE_LIST_LMM' => 'LMM', 'PRICE_LIST_LMM_TZU' => 'LMM', 'PRICE_LIST_CSD' => null,
    ];

    public function __construct(private readonly PricingWorkbookReader $reader, private readonly VehicleService $vehicles) {}

    /** "Price List LMM TZU" → PRICE_LIST_LMM_TZU (null when the title is not a price list we read). */
    public static function sheetCode(string $title): ?string
    {
        $t = strtoupper(preg_replace('/\s+/', ' ', trim($title)) ?? $title);
        if (! str_starts_with($t, 'PRICE LIST ')) {
            return null;
        }
        $code = 'PRICE_LIST_'.str_replace(' ', '_', substr($t, 11));

        return array_key_exists($code, self::SHEETS) ? $code : null;
    }

    /**
     * Variant rows still in the pre-DEC-051 shape: code without the colour suffix (BUG-199). Detect would duplicate them,
     * so the Start screen warns until the masters are purged and re-imported (DEC-074).
     */
    public function legacyCodeCount(): int
    {
        return Variant::query()
            ->whereNotNull('color_code')->where('color_code', '<>', '')->where('color_code', '<>', 'NA')
            ->whereRaw('RIGHT(code, CHAR_LENGTH(color_code)) <> color_code')
            ->count();
    }

    /**
     * Match the chosen lists (PV, CV, BEV, LMM, LMM_TZU, CSD) to the workbook's sheet titles.
     *
     * @param  list<string>  $titles  the workbook's sheet titles
     * @param  list<string>  $lists
     * @return array{found: array<string, string>, missing: list<string>} found = list => sheet title
     */
    public static function matchSheets(array $titles, array $lists): array
    {
        $byCode = [];
        foreach ($titles as $title) {
            $code = self::sheetCode($title);
            if ($code !== null && ! isset($byCode[$code])) {
                $byCode[$code] = $title;
            }
        }
        $found = [];
        $missing = [];
        foreach ($lists as $list) {
            $code = 'PRICE_LIST_'.strtoupper($list);
            if (isset($byCode[$code])) {
                $found[strtoupper($list)] = $byCode[$code];
            } else {
                $missing[] = strtoupper($list);
            }
        }

        return ['found' => $found, 'missing' => $missing];
    }

    /**
     * @param  list<string>  $sheets  sheet titles to read
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array<string, array<string, mixed>> per sheet title: rows, known, created, csd_unknown, duplicates, blank_code, errors[]
     */
    public function detect(string $path, array $sheets, ?callable $onProgress = null): array
    {
        $report = [];
        foreach ($sheets as $sheet) {
            $code = self::sheetCode($sheet);
            if ($code === null) {
                $report[$sheet] = ['error' => 'Not a price list sheet.'];

                continue;
            }
            $report[$sheet] = $this->detectSheet($path, $sheet, $code, $onProgress);
        }

        return $report;
    }

    /** @return array<string, mixed> */
    private function detectSheet(string $path, string $sheet, string $sheetCode, ?callable $onProgress): array
    {
        $stats = ['rows' => 0, 'known' => 0, 'created' => 0, 'csd_unknown' => 0, 'duplicates' => 0, 'blank_code' => 0, 'errors' => [], 'new_codes' => [], 'csd_unknown_codes' => []];
        $header = $this->reader->header($path, $sheet, $sheetCode);
        if ($header['row'] === null || ! isset($header['map']['model_code'])) {
            $stats['errors'][] = 'No "Model Code" header found.';

            return $stats;
        }
        $map = $header['map'];
        $seen = [];
        $batch = [];

        foreach ($this->reader->rows($path, $sheet, $header['row'] + 1) as $rowNo => $cells) {
            $code = PricingWorkbookReader::code($cells[$map['model_code']] ?? '');
            if ($code === '') {
                $stats['blank_code']++;

                continue;
            }
            if (isset($seen[$code])) {
                $stats['duplicates']++;

                continue;
            }
            $seen[$code] = true;
            $stats['rows']++;
            $batch[$code] = [
                'row' => $rowNo,
                'model' => PricingWorkbookReader::text($cells[$map['oem_model'] ?? -1] ?? ''),
                'variant' => PricingWorkbookReader::text($cells[$map['oem_variant'] ?? -1] ?? ''),
            ];
            if (count($batch) >= PricingWorkbookReader::CHUNK) {
                $this->flush($batch, $sheet, $sheetCode, $stats);
                $batch = [];
                $onProgress && $onProgress(['sheet' => $sheet, 'done' => $stats['rows']]);
            }
        }
        $this->flush($batch, $sheet, $sheetCode, $stats);
        $onProgress && $onProgress(['sheet' => $sheet, 'done' => $stats['rows']]);

        $stats['new_codes'] = array_slice($stats['new_codes'], 0, 500);
        $stats['csd_unknown_codes'] = array_slice($stats['csd_unknown_codes'], 0, 500);
        Log::info('[Pricing] detect', ['sheet' => $sheet] + array_diff_key($stats, ['new_codes' => 1, 'csd_unknown_codes' => 1]));

        return $stats;
    }

    /**
     * @param  array<string, array{row: int, model: string, variant: string}>  $batch
     * @param  array<string, mixed>  $stats
     */
    private function flush(array $batch, string $sheet, string $sheetCode, array &$stats): void
    {
        if ($batch === []) {
            return;
        }
        $known = Variant::query()->whereIn('code', array_keys($batch))->pluck('code')->map(fn ($c) => strtoupper((string) $c))->flip()->all();

        // one commit per chunk: row-by-row autocommit made each stub cost ~90 ms (vs ~9 ms)
        DB::transaction(function () use ($batch, $known, $sheet, $sheetCode, &$stats) {
            $this->createStubs($batch, $known, $sheet, $sheetCode, $stats);
        });
    }

    /**
     * @param  array<string, array{row: int, model: string, variant: string}>  $batch
     * @param  array<string, int|string>  $known  codes already in the master
     * @param  array<string, mixed>  $stats
     */
    private function createStubs(array $batch, array $known, string $sheet, string $sheetCode, array &$stats): void
    {
        foreach ($batch as $code => $row) {
            if (isset($known[$code])) {
                $stats['known']++;

                continue;
            }
            if (self::SHEETS[$sheetCode] === null) {
                $stats['csd_unknown']++;
                $stats['csd_unknown_codes'][] = $code;

                continue;
            }
            if ($row['model'] === '') {
                $stats['errors'][] = "Row {$row['row']} ({$code}): OEM Model is blank — no vehicle created.";

                continue;
            }
            try {
                $result = $this->vehicles->createStubFromPriceList($code, $row['model'], $row['variant'], $sheet);
                if ($result['created']) {
                    $stats['created']++;
                    $stats['new_codes'][] = $code;
                } else {
                    $stats['known']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = "Row {$row['row']} ({$code}): ".$this->message($e);
            }
        }
    }

    private function message(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return implode(' ', array_merge(...array_values($e->errors())));
        }

        return $e->getMessage();
    }
}
