<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Path: database/migrations/2026_08_26_140000_pricing_snapshots.php
 *
 * Published on-road JSON per OEM Code + channel + vin_type + wef.
 * Does not drop existing snapshot data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xlr8_vehicle_pricing_snapshots')) {
            return;
        }

        Schema::create('xlr8_vehicle_pricing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id')->nullable();
            $table->string('model_code', 40)->comment('OEM Code = variant.code');
            $table->string('variant_code', 40)->nullable();
            $table->string('channel', 16)->default('normal');
            $table->string('vin_type', 8)->default('nv')->comment('nv|ov');
            $table->date('wef_date');
            $table->json('payload');
            $table->boolean('is_active')->default(true);
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'channel', 'vin_type', 'wef_date'], 'uk_snap_code_ch_vin_wef');
            $table->index(['import_session_id'], 'idx_snap_sess');
            $table->index(['is_active', 'wef_date'], 'idx_snap_active_wef');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_vehicle_pricing_snapshots');
    }
};
