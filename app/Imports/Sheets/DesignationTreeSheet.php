<?php
namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DesignationTreeSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_DesignationTree';

    protected function processRow(array $row, int $rowIndex): void
    {
        $desig = $this->code($row['Designation Code*'] ?? null, 10);
        $dept  = $this->code($row['Department Code*'] ?? null, 10);
        $div   = $this->code($row['Division Code*'] ?? null, 10);

        if (!$desig || !$dept) {
            $this->logRow($rowIndex, '❌ FAIL', 'missing desig/dept code');
            return;
        }

        if (!DB::table('xlr8_admin_designation')->where('code', $desig)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "designation {$desig} not found");
            return;
        }
        if (!DB::table('xlr8_admin_department')->where('code', $dept)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "department {$dept} not found");
            return;
        }
        if ($div && !DB::table('xlr8_admin_division')->where('code', $div)->exists()) {
            $this->logRow($rowIndex, '⚠️ WARN', "division {$div} not found — inserting without div");
            $div = null;
        }

        $treeCode = $div ? "{$dept}-{$div}-{$desig}" : "{$dept}-{$desig}";
        $displayName = $desig . ($dept ? ' | ' . $dept : '') . ($div ? ' | ' . $div : '');

        $now = Carbon::now();
        $result = $this->upsert('xlr8_admin_desig_dept_tree', [
            'tree_code'       => $treeCode,
            'desig_code'      => $desig,
            'dept_code'       => $dept,
            'div_code'        => $div,
            'reports_to_code' => $this->n($row['Reports To'] ?? null),
            'display_name'    => $displayName,
            'level'           => 1,
            'is_active'       => $this->b($row['Is Active'] ?? 'Yes', true),
            'created_at'      => $now,
            'updated_at'      => $now,
        ], ['tree_code' => $treeCode]);

        $this->logRow($rowIndex, $result['action'] === 'inserted' ? '✅ INSERTED' : '🔄 UPDATED', "Tree {$treeCode}");
    }
}