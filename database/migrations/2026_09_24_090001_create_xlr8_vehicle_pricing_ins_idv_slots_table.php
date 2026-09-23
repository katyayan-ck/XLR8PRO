<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per IDV year-slot per base rule, instead of fixed idv_1..idv_N
 * columns — so a future 5+8 plan needs no schema change, just more rows.
 * idv_basis carries the sheet's own text ("95% of Invoice") for audit;
 * idv_pct is the parsed percentage (95.000) the engine actually computes
 * with. No SQL FK per project convention (code-based/Eloquent relations only)
 * — base_rule_id links to xlr8_vehicle_pricing_ins_base_rules.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_idv_slots')) {
            return;
        }

        Schema::create('xlr8_vehicle_pricing_ins_idv_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_rule_id')->index('idx_ins_idv_base_rule');
            $table->unsignedTinyInteger('year_no');
            $table->string('idv_basis', 60)->nullable();
            $table->decimal('idv_pct', 6, 3)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_idv_slots');
    }
};
