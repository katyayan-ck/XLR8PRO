<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-080: one row per vehicle per Calculate & Publish run — published / failed / skipped with the reason. Drives the
 * process summary (step 10) and "Retry failed". Engine-written, append / update only.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_calc_results';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->string('model_code', 40);
            $table->string('price_list', 20)->nullable();
            $table->string('status', 12);                       // published | failed | skipped
            $table->unsignedSmallInteger('snapshots')->default(0);
            $table->string('message', 500)->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unique(['import_session_id', 'model_code'], 'uk_calc_session_code');
            $table->index(['import_session_id', 'status'], 'idx_calc_session_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);   // a table this migration created (never a live legacy table)
    }
};
