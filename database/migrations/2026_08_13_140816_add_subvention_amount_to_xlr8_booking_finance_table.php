<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_booking_finance', function (Blueprint $table) {
            $table->decimal('subvention_amount', 15, 2)
                ->nullable()
                ->after('file_charge');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_booking_finance', function (Blueprint $table) {
            $table->dropColumn('subvention_amount');
        });
    }
};
