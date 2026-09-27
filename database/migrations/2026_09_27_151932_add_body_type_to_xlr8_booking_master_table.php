<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Guarded (DEC-068): safe on databases that already have the column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('xlr8_booking_master', 'body_type')) {
            return;
        }

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->string('body_type', 10)
                ->nullable()
                ->after('sale_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('xlr8_booking_master', 'body_type')) {
            return;
        }

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->dropColumn('body_type');
        });
    }
};
