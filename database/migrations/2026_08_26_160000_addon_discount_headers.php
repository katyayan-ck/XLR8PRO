<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Path: database/migrations/2026_08_26_160000_addon_discount_headers.php
 * Seed Exchange + Corporate field_code headers if missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_sheet_headers')) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ([
            ['model', 'Model', 10],
            ['variant', 'Variant', 20],
            ['scheme_type', 'Scheme Type', 30],
            ['oem_share', 'OEM Share', 40],
            ['dealer_share', 'Dealer Share', 50],
            ['total', 'Total', 60],
        ] as [$code, $label, $ord]) {
            $rows[] = ['EXCHANGE', $code, $label, $ord];
        }

        foreach ([
            ['model', 'Model', 10],
            ['variant', 'Variant', 20],
            ['category', 'Category', 30],
            ['oem_share', 'OEM Share', 40],
            ['dealer_share', 'Dealer Share', 50],
            ['total', 'Total', 60],
        ] as [$code, $label, $ord]) {
            $rows[] = ['CORPORATE', $code, $label, $ord];
        }

        foreach ($rows as [$sheet, $code, $label, $ord]) {
            $exists = DB::table('xlr8_vehicle_pricing_sheet_headers')
                ->where('sheet_code', $sheet)
                ->where('field_code', $code)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('xlr8_vehicle_pricing_sheet_headers')->insert([
                'sheet_code'  => $sheet,
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => null,
                'data_type'   => in_array($code, ['oem_share', 'dealer_share', 'total'], true) ? 'decimal' : 'string',
                'is_required' => 0,
                'sort_order'  => $ord,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_sheet_headers')) {
            return;
        }
        DB::table('xlr8_vehicle_pricing_sheet_headers')
            ->whereIn('sheet_code', ['EXCHANGE', 'CORPORATE'])
            ->delete();
    }
};
