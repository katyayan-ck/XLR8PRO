<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_import_sessions')) {
            return;
        }

        Schema::table('xlr8_vehicle_pricing_import_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'notes')) {
                $table->text('notes')->nullable()->after('stats');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'source_filename')) {
                $table->string('source_filename', 191)->nullable()->after('stats');
            }
            if (! Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'selected_segments')) {
                $table->json('selected_segments')->nullable()->after('selected_sheets');
            }
        });
    }

    public function down(): void
    {
        // keep columns
    }
};
