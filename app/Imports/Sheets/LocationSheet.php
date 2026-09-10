<?php
namespace App\Imports\Sheets;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LocationSheet extends BaseSheetImport
{
    protected string $sheetName = 'M_Location';

    protected function processRow(array $row, int $rowIndex): void
    {
        $locCode = $this->code($row['Location Code*'] ?? $row['Location Code'] ?? null, 10);
        if (!$locCode) {
            $this->skip("Row {$rowIndex}: missing location code"); 
            return;
        }

        $branchCode = $this->code($row['Branch Code*'] ?? null, 5);
        if ($branchCode && !DB::table('xlr8_admin_branch')->where('branch_code', $branchCode)->exists()) {
            $this->skip("Row {$rowIndex}: branch {$branchCode} not found for location {$locCode}");
            return;
        }

        $now = Carbon::now();

        $this->upsert('xlr8_admin_location', [
            'code'                => $locCode,
            'name'                => $this->s($row['Location Name*'] ?? $row['Location Name'] ?? ''),
            'branch_code'         => $branchCode,
            'is_sales_location'   => $this->b($row['Is Sales Location'] ?? 'No'),
            'is_workshop'         => $this->b($row['Is Workshop'] ?? 'No'),
            'is_parts_location'   => $this->b($row['Is Parts Location'] ?? 'No'),
            'is_stock_location'   => $this->b($row['Is Stock Location'] ?? 'No'),
            'is_office_only'      => $this->b($row['Is Office Only'] ?? 'No'),
            'is_mwh'              => $this->b($row['Is MWH'] ?? 'No'),
            'is_lmmws'            => $this->b($row['Is LMMWS'] ?? 'No'),
            'latitude'            => $this->n($row['Latitude'] ?? null),
            'longitude'           => $this->n($row['Longitude'] ?? null),
            'is_active'           => $this->b($row['Is Active'] ?? 'Yes', true),
            'created_at'          => $now,
            'updated_at'          => $now,
        ], ['code' => $locCode]);
    }
}