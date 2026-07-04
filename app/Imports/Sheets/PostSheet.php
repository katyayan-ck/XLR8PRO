<?php
namespace App\Imports\Sheets;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Post';

    protected function processRow(array $row, int $rowIndex): void
    {
        $postCode = $this->code($row['Post Code*'] ?? null, 30);
        if (!$postCode) {
            $this->logRow($rowIndex, '❌ FAIL', 'missing Post Code');
            return;
        }

        $desigCode  = $this->code($row['Designation Code*'] ?? null, 10);
        $deptCode   = $this->code($row['Department Code'] ?? null, 10);
        $divCode    = $this->code($row['Division Code'] ?? null, 10);
        $branchCode = $this->code($row['Branch Code'] ?? null, 5);
        $locCode    = $this->code($row['Location Code'] ?? null, 10);

        // Incremental guards
        if ($desigCode && !DB::table('xlr8_admin_designation')->where('code', $desigCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "designation {$desigCode} not found");
            return;
        }
        if ($deptCode && !DB::table('xlr8_admin_department')->where('code', $deptCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "department {$deptCode} not found");
            return;
        }
        if ($divCode && !DB::table('xlr8_admin_division')->where('code', $divCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "division {$divCode} not found");
            return;
        }
        if ($branchCode && !DB::table('xlr8_admin_branch')->where('branch_code', $branchCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "branch {$branchCode} not found");
            return;
        }
        if ($locCode && !DB::table('xlr8_admin_location')->where('code', $locCode)->exists()) {
            $this->logRow($rowIndex, '❌ FAIL', "location {$locCode} not found");
            return;
        }

        $now = Carbon::now();
        $result = $this->upsert('xlr8_iam_roles', [
            'post_code'    => $postCode,
            'name'         => $postCode,
            'guard_name'   => 'web',
            'is_post'      => true,
            'display_name' => $this->s($row['Post Name'] ?? $postCode),
            'branch_code'  => $branchCode,
            'loc_code'     => $locCode,
            'dept_code'    => $deptCode,
            'div_code'     => $divCode,
            'desig_code'   => $desigCode,
            'is_active'    => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], ['post_code' => $postCode]);

        $this->logRow($rowIndex, $result['action'] === 'inserted' ? '✅ INSERTED' : '🔄 UPDATED', "Post {$postCode}");
    }
}