<?php
namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BranchSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Branch';

    protected array $columnMap = [
        'branch_code' => ['Branch Code', 'branch_code', 'Code', 'Br Code'],
        'name'        => ['Branch Name', 'name', 'Name'],
        'city'        => ['City', 'city'],
        'state'       => ['State', 'state'],
        'country'     => ['Country', 'country'],
        'is_head_office' => ['Is Head Office', 'is_head_office', 'Head Office'],
        'is_active'   => ['Is Active', 'is_active', 'Active', 'Status'],
    ];

    protected function processRow(array $row, int $rowIndex): void
    {
        $branchCode = $this->code($row['branch_code'] ?? null, 5);
        if (!$branchCode) { $this->skip("Row {$rowIndex}: missing branch code"); return; }

        $name = $this->s($row['name'] ?? '');
        if (!$name) { $this->skip("Row {$rowIndex}: missing branch name for [{$branchCode}]"); return; }

        $now = Carbon::now();

        // Existence check on `code` (unique column in schema) — NOT branch_code
        // branch.code and branch.branch_code hold the same short value for branches.
        $this->upsert('xlr8_admin_branch', [
            'code'           => $branchCode,
            'branch_code'    => $branchCode,
            'name'           => $name,
            'city'           => $this->n($row['city'] ?? null),
            'state'          => $this->n($row['state'] ?? null),
            'country'        => $this->n($row['country'] ?? null) ?? 'India',
            'is_head_office' => $this->b($row['is_head_office'] ?? 'No'),
            'is_active'      => $this->b($row['is_active'] ?? 'Yes', true),
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['code' => $branchCode]);
    }
}