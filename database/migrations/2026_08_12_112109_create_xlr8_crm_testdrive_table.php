<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xlr8_crm_testdrive', function (Blueprint $table) {
            $table->id();

            $table->string('test_drive_no', 50)->nullable()->index();
            $table->string('enquiry_no', 50)->nullable()->index();

            $table->string('sc_code', 200)->nullable();
            $table->string('sc_mile_id', 100)->nullable();

            $table->string('stage', 100)->nullable();

            $table->date('td_created_date')->nullable();
            $table->datetime('scheduled_td_start_time')->nullable();
            $table->datetime('scheduled_td_end_time')->nullable();
            $table->datetime('actual_td_start_time')->nullable();
            $table->datetime('actual_td_end_time')->nullable();

            $table->string('model', 150)->nullable();
            $table->string('model_code', 50)->nullable();
            $table->string('variant', 150)->nullable();
            $table->string('variant_code', 50)->nullable();

            $table->string('customer_name', 200)->nullable();
            $table->string('customer_phone', 15)->nullable();

            $table->tinyInteger('is_active')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_crm_testdrive');
    }
};
