<?php

/**
 * Path: app/Services/Vehicle/Pricing/PricingResetService.php
 *
 * Destructive reset of workflow / OEM price / snapshot tables.
 * Does NOT touch sheet headers, Insurance, RTO, addons, discounts, dealer charges, TCS.
 * Deletes vehicle_model / vehicle_variant rows created on or after $afterDate.
 */

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PricingResetService
{
    public const FLUSH_TABLES = [
        'xlr8_vehicle_pricing_import_sessions',
        'xlr8_vehicle_pricing_profile',
        'xlr8_vehicle_pricing',
        'xlr8_vehicle_pricing_history',
        'xlr8_vehicle_pricing_change_flags',
        'xlr8_vehicle_pricing_draft',
        'xlr8_vehicle_pricing_affected',
        'xlr8_vehicle_pricing_snapshots',
        'xlr8_vehicle_pricing_holds',
        'xlr8_vehicle_pricing_csd',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public const KEEP_TABLES = [
        'xlr8_vehicle_pricing_sheet_headers',
        'xlr8_vehicle_pricing_addons',
        'xlr8_vehicle_pricing_addon_history',
        'xlr8_vehicle_pricing_discounts',
        'xlr8_vehicle_pricing_discount_history',
        'xlr8_vehicle_pricing_dealer_charges',
        'xlr8_vehicle_pricing_rto_rules',
        'xlr8_vehicle_pricing_ins_defaults',
        'xlr8_vehicle_pricing_ins_base_rules',
        'xlr8_vehicle_pricing_ins_addon_rates',
        'xlr8_vehicle_pricing_tcs_config',
        'xlr8_utils_synonyms',
    ];

    /**
     * @return list<string>
     */
    public function run(string $afterDate, bool $flushQueue = true): array
    {
        $log = [];
        $after = date('Y-m-d 00:00:00', strtotime($afterDate));
        $log[] = '[' . now()->toDateTimeString() . '] Pricing reset start';
        $log[] = 'Cutoff (created_at >=): ' . $after;
        $log[] = 'KEEP: headers, addons, discounts, dealer charges, RTO, insurance, TCS, synonyms';

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = self::FLUSH_TABLES;
        if (! $flushQueue) {
            $tables = array_values(array_diff($tables, ['jobs', 'job_batches', 'failed_jobs']));
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $log[] = "SKIP missing table {$table}";
                continue;
            }
            $before = DB::table($table)->count();
            DB::table($table)->truncate();
            $log[] = "FLUSH {$table} ({$before} → 0)";
        }

        $log = array_merge($log, $this->deleteVehiclesAfter($after));

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Cache::flush();
        $log[] = 'Cache::flush() done';

        foreach (self::KEEP_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $log[] = 'KEEP ' . $table . ' rows=' . DB::table($table)->count();
        }

        $log[] = '[' . now()->toDateTimeString() . '] Pricing reset finished';

        return $log;
    }

    /**
     * @return list<string>
     */
    protected function deleteVehiclesAfter(string $after): array
    {
        $log = [];
        $variantTable = Schema::hasTable('xlr8_vehicle_variant')
            ? 'xlr8_vehicle_variant'
            : (Schema::hasTable('vehicle_variant') ? 'vehicle_variant' : null);
        $modelTable = Schema::hasTable('xlr8_vehicle_model')
            ? 'xlr8_vehicle_model'
            : (Schema::hasTable('vehicle_model') ? 'vehicle_model' : null);

        if ($variantTable) {
            $q = DB::table($variantTable)->where('created_at', '>=', $after);
            $n = (clone $q)->count();
            $q->delete();
            $log[] = "DELETE {$variantTable} created_at >= {$after} ({$n} rows)";
        } else {
            $log[] = 'SKIP variant table not found';
        }

        if ($modelTable) {
            $keepCodes = [];
            if ($variantTable) {
                $keepCodes = DB::table($variantTable)->pluck('model_code')->unique()->filter()->all();
            }
            $q = DB::table($modelTable)->where('created_at', '>=', $after);
            if ($keepCodes !== []) {
                $q->whereNotIn('code', $keepCodes);
            }
            $n = (clone $q)->count();
            $q->delete();
            $log[] = "DELETE {$modelTable} created_at >= {$after} with no remaining variants ({$n} rows)";
            $log[] = "REMAIN {$modelTable}=" . DB::table($modelTable)->count()
                . " {$variantTable}=" . ($variantTable ? DB::table($variantTable)->count() : 0);
        } else {
            $log[] = 'SKIP model table not found';
        }

        return $log;
    }
}
