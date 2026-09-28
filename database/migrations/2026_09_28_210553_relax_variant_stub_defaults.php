<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-073: a new variant must not pass the completeness gate through database defaults. `wheels` (default 4) and
 * `taxi_price` (default 'NO') become nullable without a default, and a new row is inactive unless someone activates
 * it (`is_active` default 0). Existing values are untouched. Reversible (nulls are restored to the old defaults).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_variant')) {
            return;
        }
        Schema::table('xlr8_vehicle_variant', function (Blueprint $t) {
            $t->unsignedTinyInteger('wheels')->nullable()->default(null)->change();
            $t->string('taxi_price', 10)->nullable()->default(null)->change();
            $t->boolean('is_active')->default(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_variant')) {
            return;
        }
        DB::table('xlr8_vehicle_variant')->whereNull('wheels')->update(['wheels' => 4]);
        DB::table('xlr8_vehicle_variant')->whereNull('taxi_price')->update(['taxi_price' => 'NO']);
        Schema::table('xlr8_vehicle_variant', function (Blueprint $t) {
            $t->unsignedTinyInteger('wheels')->nullable(false)->default(4)->change();
            $t->string('taxi_price', 10)->nullable(false)->default('NO')->change();
            $t->boolean('is_active')->default(true)->change();
        });
    }
};
