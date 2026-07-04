<?php
namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VerticalSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Vertical';

    protected array $columnMap = [
        'code' => ['Vertical Code', 'code', 'Code', 'vert_code'],
        'name' => ['Vertical Name', 'name', 'Name', 'Vertical'],
        'is_active' => ['Is Active', 'Active', 'is_active'],
    ];

    protected function processRow(array $row, int $rowIndex): void
    {
        $code = $this->code($row['code'] ?? null, 10);
        $name = $this->s($row['name'] ?? '');

        if (!$code || !$name) { $this->skip("Row {$rowIndex}: missing code/name"); return; }

        $now = Carbon::now();
        // FIXED: existence check on `code` (not vert_code). Both are written same value.
        $this->upsert('xlr8_admin_vertical', [
            'code'       => $code,           
            'name'       => $name,
            'is_active'  => $this->b($row['is_active'] ?? 'Yes', true),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['code' => $code]);       // FIXED: match on `code`, not `vert_code`
    }
}