<?php
namespace App\Imports\Sheets;

use Carbon\Carbon;

class DesignationSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Designation';

    protected array $columnMap = [
        'code'            => ['Designation Code', 'Desig Code', 'code', 'Code'],
        'name'            => ['Designation Name', 'Name', 'Designation', 'name'],
        'description'     => ['Description', 'Remarks', 'description'],
        'hierarchy_level' => ['Hierarchy Level', 'hierarchy_level', 'Level'],
        'rank'            => ['Rank', 'rank', 'Sort Order'],
        'category'        => ['Category', 'category', 'Dept Category'],
        'is_top_mgmt'     => ['Is Top Mgmt', 'is_top_mgmt', 'Top Management'],
        'parent_desig_code' => ['Default Parent Designation Code', 'parent_desig_code', 'Parent Desig Code'],
        'is_active'       => ['Is Active', 'Active', 'is_active', 'Status'],
    ];

    protected function processRow(array $row, int $rowIndex): void
    {
        // IMPORTANT: writes to `code` ONLY — desig_code column was DROPPED from table.
        $code = $this->code($row['code'] ?? null, 10);
        if (!$code) { $this->skip("Row {$rowIndex}: missing designation code"); return; }

        $name = trim($this->s($row['name'] ?? ''));
        if (!$name) { $this->skip("Row {$rowIndex}: missing name for [{$code}]"); return; }

        $now = Carbon::now();

        $this->upsert('xlr8_admin_designation', [
            'code'              => $code,
            'name'              => $name,
            'description'       => $this->n($row['description'] ?? null),
            'hierarchy_level'   => $this->i($row['hierarchy_level'] ?? null, 0),
            'rank'              => max(0, min(255, $this->i($row['rank'] ?? null, 0))),
            'category'          => ($c = ucwords(strtolower($this->s($row['category'] ?? '')))) ? $c : null,
            'is_top_mgmt'       => $this->b($row['is_top_mgmt'] ?? 'No'),
            'parent_desig_code' => $this->code($row['parent_desig_code'] ?? null, 10),
            'is_active'         => $this->b($row['is_active'] ?? 'Yes', true),
            'created_at'        => $now,
            'updated_at'        => $now,
        ], ['code' => $code]);
    }
}