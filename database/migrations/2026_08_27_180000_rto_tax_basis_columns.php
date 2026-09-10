<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_rto_rules')) {
            return;
        }

        Schema::table('xlr8_vehicle_pricing_rto_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('xlr8_vehicle_pricing_rto_rules', 'tax_basis')) {
                $table->string('tax_basis', 120)->nullable()->after('tax_factor');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_rto_rules', 'surcharge_formula')) {
                $table->string('surcharge_formula', 120)->nullable()->after('surcharge');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_rto_rules', 'extra_json')) {
                $table->json('extra_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        // keep columns
    }
};
