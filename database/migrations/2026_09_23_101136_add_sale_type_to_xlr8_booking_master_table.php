<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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