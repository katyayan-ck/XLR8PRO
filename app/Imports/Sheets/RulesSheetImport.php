<?php

namespace App\Imports\Sheets;

use App\Imports\Concerns\MasterDataSeeder;
use App\Imports\Concerns\CodeGenerator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;


class RulesSheetImport implements ToCollection
{
    use MasterDataSeeder, CodeGenerator;

   
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

    private const HEADER_ROW = 0;

    private const SKIP_VALUES = ['any', 'all', ''];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === self::HEADER_ROW) {
                continue;
            }

            $this->seedDesignation($this->cell($row, self::COL_DESIG));
            $this->seedDepartment($this->cell($row, self::COL_DEPT));
            $this->seedVertical($this->cell($row, self::COL_VERTICAL));
            $this->seedSegment($this->cell($row, self::COL_SEGMENT));
            $this->seedSubSegment($this->cell($row, self::COL_SUBSEGMENT));
            $this->seedBranch($this->cell($row, self::COL_BRANCH));
         
        }
    }


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
        $this->upsertSegment($name);        
    }

    private function seedSubSegment(?string $name): void
    {
        if ($this->isSkippable($name)) return;
           }

    private function seedBranch(?string $name): void
    {
        if ($this->isSkippable($name)) return;
        $this->upsertBranch($name);
    }

   
    private function cell(Collection $row, int $index): ?string
    {
        $val = $row->get($index);
        if ($val === null || $val === '') return null;
        $str = trim((string) $val);
        return $str === '' ? null : $str;
    }

    private function isSkippable(?string $value): bool
    {
        if ($value === null) return true;
        return in_array(strtolower(trim($value)), self::SKIP_VALUES, true);
    }

   
    private function parseCoords(?string $coords): array
    {
        if (!$coords) return [null, null];
        $parts = explode(',', $coords);
        if (count($parts) < 2) return [null, null];
        $lat = (float) trim($parts[0]);
        $lng = (float) trim($parts[1]);
        return [$lat ?: null, $lng ?: null];
    }

    private function inferBranchFromName(?string $name): ?string
    {
        if (!$name) return null;
        $lower = strtolower($name);
        if (str_contains($lower, 'bikaner'))  return 'BKN';
        if (str_contains($lower, 'churu'))    return 'CHR';
        if (str_contains($lower, 'delhi'))    return null;   
        if (str_contains($lower, 'gurugram')) return null;   
        return null;
    }

       private int $_wlSeq = 0;

    private function nextWorkLocationSeq(): int
    {
        return ++$this->_wlSeq;
    }
}