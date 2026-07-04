<?php

namespace App\Imports\Sheets;

use App\Imports\RbacMasterImport;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ModelSheet implements ToCollection, WithHeadingRow
{
    public function __construct(protected RbacMasterImport $master) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $code    = trim((string)($row['model_code'] ?? ''));
            $name    = trim((string)($row['model_name'] ?? ''));
            $brand   = trim((string)($row['brand_code'] ?? SegmentSheet::DEFAULT_BRAND));
            $seg     = trim((string)($row['segment_code'] ?? ''));
            $subseg  = trim((string)($row['sub_segment_code'] ?? '')) ?: null;
            if (!$code || !$name) { $this->master->recordSkip(); continue; }
            try {
                $exists = DB::table('xlr8_vehicle_model')
                    ->where('code', $code)->where('brand_code', $brand)->exists();
                if ($exists) { $this->master->recordSkip(); continue; }

                DB::table('xlr8_vehicle_model')->insert([
                    'brand_code'      => $brand,
                    'segment_code'    => $seg,
                    'sub_segment_code'=> $subseg,
                    'code'            => $code,
                    'name'            => $name,
                    'oem_code'        => trim((string)($row['model_oem_name'] ?? '')) ?: null,
                    'is_active'       => BranchSheet::yesNo($row['is_active'] ?? 'Yes'),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $this->master->recordInsert();
            } catch (\Throwable $e) {
                $this->master->recordError('M_Model', $i + 2, $e->getMessage());
            }
        }
    }
}