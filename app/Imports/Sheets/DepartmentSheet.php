<?php
namespace App\Imports\Sheets;

use Carbon\Carbon;

class DepartmentSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Department';

    protected array $columnMap = [
        'code'   => ['Department Code', 'code', 'Dept Code', 'Code'],
        'name'   => ['Department Name', 'name', 'Name', 'Department'],
        'is_active' => ['Is Active', 'Active', 'is_active', 'Status'],
        'description' => ['Description', 'Remarks', 'description'],
    ];

    protected function processRow(array $row, int $rowIndex): void
    {
        $code = $this->code($row['code'] ?? null, 10);
        if (!$code) { $this->skip("Row {$rowIndex}: missing department code"); return; }

        $name = $this->s($row['name'] ?? '');
        if (!$name) { $this->skip("Row {$rowIndex}: missing name for [{$code}]"); return; }

        $now = Carbon::now();
        // Write only to `code` — dept_code column dropped by migration
        $this->upsert('xlr8_admin_department', [
            'code'        => $code,
            'name'        => $name,
            'description' => $this->n($row['description'] ?? null),
            'is_active'   => $this->b($row['is_active'] ?? 'Yes', true),
            'created_at'  => $now,
            'updated_at'  => $now,
        ], ['code' => $code]);
    }
}