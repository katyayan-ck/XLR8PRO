<?php

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\SheetHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves Excel header labels → stable field_code via registry.
 * Labels may change; field_code is what importers use.
 */
class SheetHeaderService
{
    protected const CACHE_TTL = 3600; // seconds; invalidated on header admin change

    protected const CACHE_PREFIX = 'pricing.sheet_headers.v2.';

    /**
     * Map of normalized label → field_code for a sheet.
     *
     * @return array<string, string> lowercase label => field_code
     */
    public function labelMap(string $sheetCode): array
    {
        return $this->registry($sheetCode)['map'];
    }

    /**
     * Normalised label => [field_code, rank] (rank 0 = the field's primary label, 1… = its aliases in order). Registry
     * labels are normalised exactly like sheet cells (BUG-200: "Ex-Showroom Price ORG" never matched before).
     *
     * @return array{map: array<string, string>, rank: array<string, int>}
     */
    protected function registry(string $sheetCode): array
    {
        $sheetCode = strtoupper(trim($sheetCode));

        return Cache::remember(self::CACHE_PREFIX.$sheetCode, self::CACHE_TTL, function () use ($sheetCode): array {
            $map = [];
            $rank = [];
            $headers = SheetHeader::query()
                ->active()
                ->forSheet($sheetCode)
                ->ordered()
                ->get();

            foreach ($headers as $header) {
                foreach ($header->allLabels() as $i => $label) {
                    $label = $this->normalizeLabel($label);
                    // First registration wins if two field_codes share a label (avoid silent overwrite of required)
                    if ($label !== '' && ! isset($map[$label])) {
                        $map[$label] = $header->field_code;
                        $rank[$label] = $i;
                    }
                }
            }

            return ['map' => $map, 'rank' => $rank];
        });
    }

    public function mapHeaderRow(string $sheetCode, array $headerCells): array
    {
        $registry = $this->registry($sheetCode);
        $result = [];
        $bestRank = [];

        foreach ($headerCells as $colIndex => $raw) {
            $label = $this->normalizeLabel($raw);
            if ($label === '') {
                continue;
            }
            // Built-in hard aliases (LMM TZU material code)
            $hard = [
                'm code' => 'model_code',
                'mcode' => 'model_code',
                'material code' => 'model_code',
                'vehicle code' => 'model_code',
            ];
            $fieldCode = $registry['map'][$label] ?? $hard[$label] ?? null;
            if ($fieldCode === null) {
                continue;
            }
            // the primary label beats an alias; between equals the first column wins (BUG-200)
            $rank = $registry['rank'][$label] ?? 99;
            if (! isset($result[$fieldCode]) || $rank < $bestRank[$fieldCode]) {
                $result[$fieldCode] = (int) $colIndex;
                $bestRank[$fieldCode] = $rank;
            }
        }

        return $result;
    }

    /**
     * Scan first N rows of a sheet matrix to find the header row (Price Lists often have title rows).
     * Returns [headerRowIndex, field_code => colIndex] or [null, []].
     *
     * @param  array<int, array<int, mixed>>  $rows  0-based rows of cells
     * @return array{0: int|null, 1: array<string, int>}
     */
    public function findHeaderRow(string $sheetCode, array $rows, int $maxScan = 25): array
    {
        $required = $this->requiredFieldCodes($sheetCode);
        $bestIndex = null;
        $bestMap = [];
        $bestScore = -1;

        $limit = min(count($rows), $maxScan);
        for ($i = 0; $i < $limit; $i++) {
            $map = $this->mapHeaderRow($sheetCode, $rows[$i] ?? []);
            if ($map === []) {
                continue;
            }
            $score = count($map);
            // Bonus if all required field_codes present
            $hasRequired = true;
            foreach ($required as $code) {
                if (! isset($map[$code])) {
                    $hasRequired = false;
                    break;
                }
            }
            if ($hasRequired) {
                $score += 100;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
                $bestMap = $map;
            }
        }

        if ($bestIndex === null) {
            Log::warning('[SheetHeaderService] No header row found', [
                'sheet_code' => $sheetCode,
                'scanned' => $limit,
                'required' => $required,
                'label_map_sample' => array_slice($this->labelMap($sheetCode), 0, 15, true),
            ]);
        } else {
            Log::info('[SheetHeaderService] Header row found', [
                'sheet_code' => $sheetCode,
                'header_row' => $bestIndex,
                'fields' => array_keys($bestMap),
                'score' => $bestScore,
            ]);
        }

        return [$bestIndex, $bestMap];
    }

    /**
     * Read a value from a data row using field_code map.
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $fieldMap  field_code => col index
     */
    public function val(array $row, array $fieldMap, string $fieldCode, mixed $default = null): mixed
    {
        if (! isset($fieldMap[$fieldCode])) {
            return $default;
        }
        $idx = $fieldMap[$fieldCode];
        if (! array_key_exists($idx, $row)) {
            return $default;
        }
        $v = $row[$idx];
        if ($v === null || $v === '') {
            return $default;
        }

        return $v;
    }

    /**
     * @return list<string>
     */
    public function requiredFieldCodes(string $sheetCode): array
    {
        return SheetHeader::query()
            ->active()
            ->forSheet($sheetCode)
            ->where('is_required', true)
            ->pluck('field_code')
            ->all();
    }

    /**
     * Ordered header definitions for export builders.
     */
    public function headersForExport(string $sheetCode): Collection
    {
        return SheetHeader::query()
            ->active()
            ->forSheet($sheetCode)
            ->ordered()
            ->get();
    }

    public function forgetCache(?string $sheetCode = null): void
    {
        if ($sheetCode) {
            Cache::forget(self::CACHE_PREFIX.strtoupper(trim($sheetCode)));

            return;
        }

        $codes = SheetHeader::query()->distinct()->pluck('sheet_code');
        foreach ($codes as $code) {
            Cache::forget(self::CACHE_PREFIX.$code);
        }
    }

    public function normalizeLabel(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }
        $s = trim((string) $raw);
        // Dots/underscores → space so "M.CODE" becomes "m code"
        $s = str_replace(['.', '_', '-'], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return mb_strtolower($s);
    }
}
