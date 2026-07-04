<?php

namespace App\Imports\Sheets;

use App\Imports\RbacMasterImport;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SegmentSheet implements ToCollection, WithHeadingRow
{
    // Default brand — all segments belong to MHN (Mahindra) in this workbook
    const DEFAULT_BRAND = 'MHN';

    public function __construct(protected RbacMasterImport $master) {}

    public function collection(Collection $rows): void
    {
        // Ensure brand exists first
        DB::table('xlr8_vehicle_brand')->updateOrInsert(
            ['code' => self::DEFAULT_BRAND],
            ['name' => 'Mahindra', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        foreach ($rows as $i => $row) {
            $code = trim((string)($row['segment_code'] ?? ''));
            $name = trim((string)($row['segment_name'] ?? ''));
            if (!$code || !$name) { $this->master->recordSkip(); continue; }
            try {
                $exists = DB::table('xlr8_vehicle_segment')
                    ->where('code', $code)->where('brand_code', self::DEFAULT_BRAND)->exists();
                if ($exists) { $this->master->recordSkip(); continue; }

                DB::table('xlr8_vehicle_segment')->insert([
                    'brand_code' => self::DEFAULT_BRAND,
                    'code'       => $code,
                    'name'       => $name,
                    'is_active'  => BranchSheet::yesNo($row['is_active'] ?? 'Yes'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->master->recordInsert();
            } catch (\Throwable $e) {
                $this->master->recordError('M_Segment', $i + 2, $e->getMessage());
            }
        }
    }
}