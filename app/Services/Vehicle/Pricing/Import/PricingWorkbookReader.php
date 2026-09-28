<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Services\Vehicle\Pricing\SheetHeaderService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Memory-safe workbook access for every pricing importer (DEC-073): one sheet at a time, columns capped, rows handed out
 * in chunks, formula cells read as the value Excel last saved (a CSD VLOOKUP into a sheet that isn't loaded must not
 * break the import), and value normalisers for the reference workbooks' formats:
 *   number("3,00,752") = 300752 · number("-") = 0 · percent(40) = 40 / percentFraction(0.4) = 40 · yesNo("Y") = "YES".
 */
class PricingWorkbookReader
{
    public const MAX_COL = 'BJ';

    public const CHUNK = 250;

    public function __construct(private readonly SheetHeaderService $headers) {}

    /** @return list<string> sheet titles, without loading any cells */
    public function sheetNames(string $path): array
    {
        return (new XlsxReader)->listWorksheetNames($path);
    }

    /**
     * Rows of one sheet, `row number (1-based) => cells (0-based)`, empty rows skipped.
     *
     * @return \Generator<int, list<mixed>>
     */
    public function rows(string $path, string $sheet, int $fromRow = 1, ?string $maxCol = null): \Generator
    {
        $spreadsheet = $this->load($path, $sheet, $maxCol);
        try {
            $ws = $spreadsheet->getActiveSheet();
            $lastRow = $ws->getHighestDataRow();
            $lastCol = $this->cap($ws->getHighestDataColumn(), $maxCol);
            for ($start = max(1, $fromRow); $start <= $lastRow; $start += self::CHUNK) {
                $end = min($lastRow, $start + self::CHUNK - 1);
                $chunk = $ws->rangeToArray("A{$start}:{$lastCol}{$end}", null, false, false, false);
                foreach ($chunk as $i => $cells) {
                    $rowNo = $start + $i;
                    $cells = $this->resolveFormulas($ws, $rowNo, $cells);
                    if ($this->isEmpty($cells)) {
                        continue;
                    }
                    yield $rowNo => $cells;
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();
        }
    }

    /**
     * Find the header row (within the first 25 rows) and map it through the sheet_headers registry.
     *
     * @return array{row: int|null, map: array<string, int>, cells: list<mixed>}
     */
    public function header(string $path, string $sheet, string $sheetCode): array
    {
        $top = [];
        foreach ($this->rows($path, $sheet) as $rowNo => $cells) {
            $top[$rowNo] = $cells;
            if (count($top) >= 25) {
                break;
            }
        }
        $indexed = array_values($top);
        [$index, $map] = $this->headers->findHeaderRow($sheetCode, $indexed);
        if ($index === null) {
            return ['row' => null, 'map' => [], 'cells' => []];
        }

        return ['row' => array_keys($top)[$index], 'map' => $map, 'cells' => $indexed[$index]];
    }

    // ------------------------------------------------------------------ value normalisers

    /** Indian-format or plain number; "-" / blank → null unless $blankIsZero; "5%" → 5. */
    public static function number(mixed $value, bool $blankIsZero = false): ?float
    {
        if ($value === null) {
            return $blankIsZero ? 0.0 : null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '-' || $s === '–') {
            return $blankIsZero || $s !== '' ? 0.0 : null;
        }
        $s = str_replace([',', ' ', '₹', '%'], '', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    /** GST-style percent as a percent (40 / "40%" / 0.4 → 40). */
    public static function percent(mixed $value): ?float
    {
        $n = self::number($value);
        if ($n === null) {
            return null;
        }

        return ($n > 0 && $n < 1) ? round($n * 100, 4) : $n;
    }

    public static function yesNo(mixed $value): ?string
    {
        $v = strtoupper(trim((string) $value));

        return match ($v) {
            'Y', 'YES', 'TRUE', '1' => 'YES',
            'N', 'NO', 'FALSE', '0' => 'NO',
            default => null,
        };
    }

    public static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }

    public static function code(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
    }

    // ------------------------------------------------------------------ internals

    private function load(string $path, string $sheet, ?string $maxCol): Spreadsheet
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheet]);
        $max = Coordinate::columnIndexFromString($this->cap('ZZZ', $maxCol));
        $reader->setReadFilter(new class($max) implements IReadFilter
        {
            public function __construct(private readonly int $maxCol) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return Coordinate::columnIndexFromString($columnAddress) <= $this->maxCol;
            }
        });

        return $reader->load($path);
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<mixed>
     */
    private function resolveFormulas(Worksheet $ws, int $rowNo, array $cells): array
    {
        foreach ($cells as $i => $value) {
            if (is_string($value) && str_starts_with($value, '=')) {
                $cell = $ws->getCell(Coordinate::stringFromColumnIndex($i + 1).$rowNo);
                $cached = $cell->getOldCalculatedValue();
                if ($cached === null) {
                    try {
                        $cached = $cell->getCalculatedValue();
                    } catch (\Throwable) {
                        $cached = null;
                    }
                }
                $cells[$i] = is_string($cached) && str_starts_with($cached, '#') ? null : $cached;
            }
        }

        return $cells;
    }

    /** @param list<mixed> $cells */
    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cap(string $col, ?string $maxCol): string
    {
        $max = $maxCol ?? self::MAX_COL;

        return Coordinate::columnIndexFromString($col) > Coordinate::columnIndexFromString($max) ? $max : $col;
    }
}
