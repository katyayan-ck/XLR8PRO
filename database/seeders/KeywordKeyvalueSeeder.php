<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Core\KeywordMaster;
use App\Models\Core\Keyvalue;

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

        foreach ($keywords as $keyword => $values) {
            $master = KeywordMaster::firstOrCreate(
                ['keyword' => $keyword],
                ['details' => ucwords(str_replace('_', ' ', $keyword)), 'status' => 1]
            );

            foreach ($values as $key) {
                Keyvalue::firstOrCreate(
                    ['keyword_master_id' => $master->id, 'key' => $key],
                    ['value' => ucwords(strtolower(str_replace('_', ' ', $key))), 'status' => 1]
                );
            }
        }
    }
}
