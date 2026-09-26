<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: environments that ran 2026_09_26_120000 first already have the column (DEC-041).
        if (Schema::hasColumn('xlr8_booking_master', 'sale_type')) {
            return;
        }

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->unsignedTinyInteger('sale_type')
                ->nullable()
                ->after('b_source');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->dropColumn('sale_type');
        });
    }
};
