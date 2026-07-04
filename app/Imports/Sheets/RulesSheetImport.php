<?php

namespace App\Imports\Sheets;

use App\Imports\Concerns\MasterDataSeeder;
use App\Imports\Concerns\CodeGenerator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;


class RulesSheetImport implements ToCollection
{
    use MasterDataSeeder, CodeGenerator;

    // ── Column indices (0-based) — matches your Rules sheet exactly ──────────
    //
    // Col 0:  Designation name
    // Col 1:  Department name
    // Col 2:  Sub Department (Division) name
    // Col 3:  Vertical name
    // Col 4:  Segment name
    // Col 5:  Sub Segment name
    // Col 6:  Branch name
    // Col 7:  Location name          (geo area: Bhanipura, Churu, etc.)
    // Col 8:  Location Short Code    (BNP, BKN, CTG, etc.)
    // Col 9:  Office Loc sequence    (1, 2, 3... — used for ordering only)
    // Col 10: Office Location name   (BMPL Head Office, Bikaner, etc.)
    // Col 11: Google coordinates     (stored as string, optional)

    private const COL_DESIG        = 0;
    private const COL_DEPT         = 1;
    private const COL_DIVISION     = 2;
    private const COL_VERTICAL     = 3;
    private const COL_SEGMENT      = 4;
    private const COL_SUBSEGMENT   = 5;
    private const COL_BRANCH       = 6;
    private const COL_LOC_NAME     = 7;
    private const COL_LOC_CODE     = 8;
    private const COL_WL_SEQ       = 9;
    private const COL_WL_NAME      = 10;
    private const COL_WL_COORDS    = 11;

    // Row 0 is the header row — always skip it
    private const HEADER_ROW = 0;

    // Sentinel values that mean "any/all" — don't create master records for these
    private const SKIP_VALUES = ['any', 'all', ''];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            // Skip header row
            if ($rowIndex === self::HEADER_ROW) {
                continue;
            }

            // Process each column independently — they are unrelated lists
            $this->seedDesignation($this->cell($row, self::COL_DESIG));
            $this->seedDepartment($this->cell($row, self::COL_DEPT));
            // Note: Divisions need dept context — seeded during employee import
            $this->seedVertical($this->cell($row, self::COL_VERTICAL));
            $this->seedSegment($this->cell($row, self::COL_SEGMENT));
            $this->seedSubSegment($this->cell($row, self::COL_SUBSEGMENT));
            $this->seedBranch($this->cell($row, self::COL_BRANCH));
            // $this->seedLocation(
            //     $this->cell($row, self::COL_LOC_NAME),
            //     $this->cell($row, self::COL_LOC_CODE)
            // );
            // $this->seedWorkLocation(
            //     $this->cell($row, self::COL_WL_SEQ),
            //     $this->cell($row, self::COL_WL_NAME),
            //     $this->cell($row, self::COL_WL_COORDS)
            // );
        }
    }

    // ── Seeders — each checks isSkippable() before doing anything ────────────

    private function seedDesignation(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertDesignation($name);
    }

    private function seedDepartment(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertDepartment($name);
    }

    private function seedVertical(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertVertical($name);
    }

    private function seedSegment(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertSegment($name);        // brand_code defaults to 'MHD'
    }

    private function seedSubSegment(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        // Sub-segment needs a segment_code. From the Rules sheet we can't know
        // which segment it belongs to — store with placeholder, resolved in
        // employee import when both segment + sub-segment are on the same row.
        // SKIP here; handled in EmployeeSheetImport->upsertSubSegment() instead.
    }

    private function seedBranch(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertBranch($name);
    }

    // private function seedLocation(?string $name, ?string $shortCode): void
    // {
    //     if ($this->isSkippable($name) || $this->isSkippable($shortCode)) return;

    //     // Location table: code = short code (BNP, BKN, CTG — 3 chars ✓)
    //     // branch_code is unknown from the rules sheet — set to 'ANY' placeholder
    //     // Updated to actual branch when employee rows are processed
    //     \Illuminate\Support\Facades\DB::table('xlr8_admin_location')->updateOrInsert(
    //         ['code' => strtoupper(trim($shortCode))],
    //         [
    //             'name'        => trim($name),
    //             'branch_code' => null,          // resolved during employee import
    //             'is_active'   => 1,
    //             'created_at'  => now(),
    //             'updated_at'  => now(),
    //         ]
    //     );
    // }

    // private function seedWorkLocation(?string $seq, ?string $name, ?string $coords): void
    // {
    //     if ($this->isSkippable($name)) return;

    //     // Office Location code = WL + zero-padded seq (WL01…WL20 = 4 chars ✓)
    //     $seqNum = is_numeric($seq) ? (int) $seq : $this->nextWorkLocationSeq();
    //     $code   = 'WL' . str_pad($seqNum, 2, '0', STR_PAD_LEFT);

    //     [$lat, $lng] = $this->parseCoords($coords);

    //     \Illuminate\Support\Facades\DB::table('xlr8_admin_work_location')->updateOrInsert(
    //         ['code' => $code],
    //         [
    //             'name'        => trim($name),
    //             'latitude'    => $lat,
    //             'longitude'   => $lng,
    //             'branch_code' => $this->inferBranchFromName($name),
    //             'is_active'   => 1,
    //             'created_at'  => now(),
    //             'updated_at'  => now(),
    //         ]
    //     );
    // }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Safely read a cell by column index.
     * Returns null for missing, null-value, or whitespace-only cells.
     */
    private function cell(Collection $row, int $index): ?string
    {
        $val = $row->get($index);
        if ($val === null || $val === '') return null;
        $str = trim((string) $val);
        return $str === '' ? null : $str;
    }

    /**
     * Returns true if the value should NOT create a master record.
     * Null, blank, "Any", "All" are all skippable.
     */
    private function isSkippable(?string $value): bool
    {
        if ($value === null) return true;
        return in_array(strtolower(trim($value)), self::SKIP_VALUES, true);
    }

    /**
     * Parse "28.023836, 73.382668" → [28.023836, 73.382668]
     */
    private function parseCoords(?string $coords): array
    {
        if (!$coords) return [null, null];
        $parts = explode(',', $coords);
        if (count($parts) < 2) return [null, null];
        $lat = (float) trim($parts[0]);
        $lng = (float) trim($parts[1]);
        return [$lat ?: null, $lng ?: null];
    }

    /**
     * Infer branch_code from the work location name.
     * e.g. "BMPL Head Office, Bikaner" → 'BKN'
     *      "BMPL LMM, Churu"           → 'CHR'
     *      "Iconic Honda Gurugram..."  → null (non-BMPL location)
     */
    private function inferBranchFromName(?string $name): ?string
    {
        if (!$name) return null;
        $lower = strtolower($name);
        if (str_contains($lower, 'bikaner'))  return 'BKN';
        if (str_contains($lower, 'churu'))    return 'CHR';
        if (str_contains($lower, 'delhi'))    return null;   // other company
        if (str_contains($lower, 'gurugram')) return null;   // other company
        return null;
    }

    /**
     * Fallback seq counter when office location seq column is blank.
     */
    private int $_wlSeq = 0;

    private function nextWorkLocationSeq(): int
    {
        return ++$this->_wlSeq;
    }
}