<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->string('enq_no', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->string('enq_no', 50)->nullable(false)->change();
        });
    }
};
