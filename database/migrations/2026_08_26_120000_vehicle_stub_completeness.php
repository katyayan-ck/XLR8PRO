<?php

/**
 * Vehicle stub + completeness support
 *
 * Path: database/migrations/2026_08_26_120000_vehicle_stub_completeness.php
 *
 * Does NOT drop vehicle masters. Alters existing tables so Price List detect
 * can create incomplete stubs without inventing Fuel / Permit / Wheels.
 *
 * Pricing tables are left as-is.
 * Convention: xlr8_vehicle_pricing.model_code = OEM Code = variant.code
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
            // OEM codes in Pricing.xlsx are up to 18+ chars; 20 is too tight.
            $table->string('code', 40)->change();

            // Taxi is required by schema but unknown at stub time.
            $table->string('taxi_price', 10)->default('NO')->change();

            if (! Schema::hasColumn('xlr8_vehicle_variant', 'motor')) {
                $table->string('motor', 50)->nullable()->after('cc_capacity');
            }

            if (! Schema::hasColumn('xlr8_vehicle_variant', 'gst_percent')) {
                $table->decimal('gst_percent', 5, 2)->nullable()->after('gvw');
            }
        });

        // Unique OEM Code (one row per colour). Ignore if already present.
        try {
            Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
                $table->unique('code', 'uk_veh_var_code');
            });
        } catch (\Throwable $e) {
            // index may already exist under another name
        }

        Schema::table('xlr8_vehicle_model', function (Blueprint $table) {
            $table->string('code', 40)->change();
            $table->string('segment_code', 10)->change();
        });

        Schema::table('xlr8_vehicle_segment', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });

        Schema::table('xlr8_vehicle_subsegment', function (Blueprint $table) {
            $table->string('segment_code', 10)->change();
            $table->string('code', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
            if (Schema::hasColumn('xlr8_vehicle_variant', 'motor')) {
                $table->dropColumn('motor');
            }
            if (Schema::hasColumn('xlr8_vehicle_variant', 'gst_percent')) {
                $table->dropColumn('gst_percent');
            }
        });

        try {
            Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
                $table->dropUnique('uk_veh_var_code');
            });
        } catch (\Throwable $e) {
            //
        }
    }
};
