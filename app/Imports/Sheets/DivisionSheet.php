<?php
namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DivisionSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Division';

    protected function processRow(array $row, int $rowIndex): void
    {
        $divCode = $this->code($row['Division Code*'] ?? $row['code'] ?? null, 10);
        if (!$divCode) {
            $this->logRow($rowIndex, '❌ FAIL', 'missing Division Code');
            return;
        }

        $name = $this->s($row['Division Name*'] ?? $row['name'] ?? '');
        if (!$name) {
            $this->logRow($rowIndex, '❌ FAIL', "missing name for division {$divCode}");
            return;
        }

        $deptCode = $this->code($row['Department Code*'] ?? $row['dept_code'] ?? null, 10);

        if ($deptCode && !DB::table('xlr8_admin_department')->where('code', $deptCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "department {$deptCode} not found — skipped");
            return;
        }

        $now = Carbon::now();
        $result = $this->upsert('xlr8_admin_division', [
            'code'        => $divCode,
            'dept_code'   => $deptCode,
            'name'        => $name,
            'description' => $this->n($row['Description'] ?? null),
            'is_active'   => $this->b($row['Is Active'] ?? 'Yes', true),
            'created_at'  => $now,
            'updated_at'  => $now,
        ], ['code' => $divCode]);

        $this->logRow($rowIndex, $result['action'] === 'inserted' ? '✅ INSERTED' : '🔄 UPDATED', "Division {$divCode} — {$name}");
    }
}