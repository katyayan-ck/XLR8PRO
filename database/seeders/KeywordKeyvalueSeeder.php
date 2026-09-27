<?php

namespace Database\Seeders;

use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Seeder;

class KeywordKeyvalueSeeder extends Seeder
{
    public function run()
    {
        $keywords = [
            'segment' => ['LMM', 'PERSONAL', 'COMMERCIAL'],
            'sub_segment' => ['NON XUV'],
            'fuel_type' => ['DIESEL', 'PETROL', 'CNG', 'ELECTRIC'],
            'transmission' => ['MANUAL', 'AUTOMATIC'],
            'drivetrain' => ['RWD', 'FWD'],
            'body_make' => ['CARGO', 'PASSENGER', 'COMPLETE', 'SUV'],
            'body_type' => ['COMPLETE'],
            'permit' => ['GOODS', 'PRIVATE', 'PASSENGER'],
            'vehicle_status' => ['ACTIVE', 'DISCONTINUED'],
        ];

        // Through the entity services (DEC-050/055). Values belong to a keyword by its code (the old
        // version matched on a non-existent `keyword_master_id` column and could not run).
        $masters = app(KeywordMasterService::class);
        $keyvalues = app(KeyvalueService::class);

        foreach ($keywords as $keyword => $values) {
            $master = $masters->firstOrCreate(
                ['code' => strtoupper($keyword)],
                ['keyword' => $keyword, 'details' => ucwords(str_replace('_', ' ', $keyword)), 'status' => 1]
            );

            foreach ($values as $key) {
                $keyvalues->firstOrCreate(
                    ['keyword_code' => $master->code, 'code' => $key],
                    ['key' => $key, 'value' => ucwords(strtolower(str_replace('_', ' ', $key))), 'status' => 1]
                );
            }
        }
    }
}
