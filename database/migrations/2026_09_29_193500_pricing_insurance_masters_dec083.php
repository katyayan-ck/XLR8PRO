<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-083 insurance masters:
 *  - xlr8_vehicle_pricing_ins_companies (code, name, short name, order, active) — seeded from the company names the
 *    insurance rules / preferences already use;
 *  - xlr8_vehicle_pricing_ins_addons (code, name, default flag, order, active) — seeded from the Insurance workbook's
 *    add-on columns; NIL_DEP and CONSUMABLES are the default combo (DEC-080);
 *  - `segment` on xlr8_vehicle_pricing_ins_defaults, so a preference is segment + permit with an optional model override.
 * down() drops the two tables and the column.
 */
return new class extends Migration
{
    private const DEFAULTS = 'xlr8_vehicle_pricing_ins_defaults';

    private const ADDONS = [
        'NIL_DEP' => 'Nil Depreciation', 'CONSUMABLES' => 'Consumables', 'ENGINE' => 'Engine Protect',
        'BATTERY_FULL' => 'Battery Protect (Including Motor, Charger & Adapter)', 'BATTERY_MOTOR' => 'Battery Protect (Including Motor)',
        'BATTERY' => 'Battery Protect', 'CHARGER' => 'Charger & Adapter Cover', 'MOTOR' => 'Motor Protect', 'TYRE' => 'Tyre Protection',
        'RTI' => 'Return to Invoice', 'KEY' => 'Key Replacement', 'RSA' => 'RSA', 'BELONGINGS' => 'Personal Belongings',
        'DAILY_CASH' => 'Daily Cash', 'ADVANCE' => 'Advance Assistance Cover', 'MEDICAL' => 'Medical Expenses Cover', 'TOWING' => 'Towing',
    ];

    public function up(): void
    {
        $now = now();
        if (! Schema::hasTable('xlr8_vehicle_pricing_ins_companies')) {
            Schema::create('xlr8_vehicle_pricing_ins_companies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('short_name', 40)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_by')->nullable();
            });
            $names = collect()
                ->merge(Schema::hasTable('xlr8_vehicle_pricing_ins_base_rules') ? DB::table('xlr8_vehicle_pricing_ins_base_rules')->whereNull('deleted_at')->distinct()->pluck('company') : [])
                ->merge(Schema::hasTable(self::DEFAULTS) ? DB::table(self::DEFAULTS)->whereNull('deleted_at')->distinct()->pluck('insurance_company') : [])
                ->map(fn ($n) => strtoupper(trim((string) $n)))->filter()->unique()->values();
            foreach ($names as $i => $code) {
                DB::table('xlr8_vehicle_pricing_ins_companies')->insert(['code' => mb_substr($code, 0, 40), 'name' => $code, 'short_name' => mb_substr($code, 0, 40),
                    'sort_order' => ($i + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! Schema::hasTable('xlr8_vehicle_pricing_ins_addons')) {
            Schema::create('xlr8_vehicle_pricing_ins_addons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_by')->nullable();
            });
            $i = 0;
            foreach (self::ADDONS as $code => $name) {
                DB::table('xlr8_vehicle_pricing_ins_addons')->insert(['code' => $code, 'name' => $name, 'is_default' => in_array($code, ['NIL_DEP', 'CONSUMABLES'], true),
                    'sort_order' => (++$i) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (Schema::hasTable(self::DEFAULTS) && ! Schema::hasColumn(self::DEFAULTS, 'segment')) {
            Schema::table(self::DEFAULTS, function (Blueprint $table) {
                $table->string('segment', 20)->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_companies');
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_addons');
        if (Schema::hasTable(self::DEFAULTS) && Schema::hasColumn(self::DEFAULTS, 'segment')) {
            Schema::table(self::DEFAULTS, function (Blueprint $table) {
                $table->dropColumn('segment');
            });
        }
    }
};
