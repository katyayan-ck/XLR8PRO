<?php

namespace App\Imports\Sheets;

use App\Imports\RbacMasterImport;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubSegmentSheet implements ToCollection, WithHeadingRow
{
    public function __construct(protected RbacMasterImport $master) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $code    = trim((string)($row['sub_segment_code'] ?? ''));
            $name    = trim((string)($row['sub_segment_name'] ?? ''));
            $segCode = trim((string)($row['segment_code'] ?? ''));
            if (!$code || !$name || !$segCode) { $this->master->recordSkip(); continue; }
            try {
                $exists = DB::table('xlr8_vehicle_subsegment')
                    ->where('code', $code)->where('segment_code', $segCode)->exists();
                if ($exists) { $this->master->recordSkip(); continue; }

                DB::table('xlr8_vehicle_subsegment')->insert([
                    'brand_code'   => SegmentSheet::DEFAULT_BRAND,
                    'segment_code' => $segCode,
                    'code'         => $code,
                    'name'         => $name,
                    'is_active'    => BranchSheet::yesNo($row['is_active'] ?? 'Yes'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $this->master->recordInsert();
            } catch (\Throwable $e) {
                $this->master->recordError('M_SubSegment', $i + 2, $e->getMessage());
            }
        }
    }
}