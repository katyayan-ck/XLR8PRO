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
    Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
        $table->integer('manufacturing_year')->nullable()->after('vehicle_no');
        $table->string('odometer_reading', 50)->nullable()->after('manufacturing_year');
        $table->decimal('expected_price', 15, 2)->nullable()->after('odometer_reading');
        $table->decimal('offered_price', 15, 2)->nullable()->after('expected_price');
        $table->decimal('exchange_bonus', 15, 2)->nullable()->after('offered_price');
        $table->decimal('difference', 15, 2)->nullable()->after('exchange_bonus');
        
        $table->string('finance_mode', 50)->nullable()->after('purchase_type');
        $table->unsignedBigInteger('financier')->nullable()->after('finance_mode');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            //
        });
    }
};
