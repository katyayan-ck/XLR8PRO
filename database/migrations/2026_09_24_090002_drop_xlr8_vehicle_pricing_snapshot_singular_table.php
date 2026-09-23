<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * xlr8_vehicle_pricing_snapshot (singular) is a stray duplicate of the real,
 * model-mapped xlr8_vehicle_pricing_snapshots (plural) — confirmed empty and
 * unreferenced by any model or service before dropping. Kept as its own
 * reversible migration (not folded into the ins_base_rules change above)
 * since it's an unrelated cleanup, not part of the insurance schema fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('xlr8_vehicle_pricing_snapshot');
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_vehicle_pricing_snapshot')) {
            return;
        }

        Schema::create('xlr8_vehicle_pricing_snapshot', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }
};
