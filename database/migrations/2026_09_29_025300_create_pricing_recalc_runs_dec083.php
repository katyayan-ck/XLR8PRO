<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-083: one row per automatic recalculation run (a pricing master change outside the Pricing Process) — what
 * triggered it, how many vehicles it republished, and the failures with reasons.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_recalc_runs';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('running');   // running | done | failed
            $table->json('reasons')->nullable();                 // the changes that triggered the run (capped)
            $table->unsignedInteger('vehicles')->default(0);
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('snapshots')->default(0);
            $table->json('failures')->nullable();                // [{code, message}] (capped)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'idx_recalc_runs_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
