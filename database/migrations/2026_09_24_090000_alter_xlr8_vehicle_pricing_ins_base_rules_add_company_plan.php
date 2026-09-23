<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Insu Premium sheet scopes every OD/TP row by insurance company and plan
 * (e.g. USGI / 1+3), but xlr8_vehicle_pricing_ins_base_rules was created without
 * columns to hold either — RulesWorkbookService::importInsurancePremium() has
 * always tried to write 'company' and 'plan', silently stripped by
 * onlyExisting() on every import since the columns never existed. This closes
 * that gap. od_years/tp_years record the plan's year components (e.g. 1+3 →
 * od_years=1, tp_years=3) so the engine doesn't have to re-parse the plan
 * label every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'company')) {
                $table->string('company', 40)->nullable()->after('import_session_id');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'plan')) {
                $table->string('plan', 20)->nullable()->after('company');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'od_years')) {
                $table->unsignedTinyInteger('od_years')->nullable()->after('plan');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'tp_years')) {
                $table->unsignedTinyInteger('tp_years')->nullable()->after('od_years');
            }
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            foreach (['company', 'plan', 'od_years', 'tp_years'] as $column) {
                if (Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
