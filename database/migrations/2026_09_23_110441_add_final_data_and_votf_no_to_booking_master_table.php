<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->json('final_data')
                ->nullable()
                ->after('quotation_id');

            $table->string('votf_no', 100)
                ->nullable()
                ->after('final_data');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->dropColumn([
                'final_data',
                'votf_no',
            ]);
        });
    }
};