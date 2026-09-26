<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real Insu Premium sheet's Seating column holds range text ("1 to 7",
 * "8 to 18"), the same band shape as the sibling cc_range/gvw_range columns
 * on this table — but `seating` was typed smallint unsigned, so importing
 * real data truncated/errored ("Data truncated for column 'seating'").
 * Widening it to a varchar range string, matching the existing
 * cc_range/gvw_range convention on this same table, instead of coercing
 * range text into a single number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            $table->string('seating', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('seating')->nullable()->change();
        });
    }
};
