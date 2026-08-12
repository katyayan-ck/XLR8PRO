<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xlr8_crm_enquiries_fup', function (Blueprint $table) {
            $table->id();

            $table->string('enquiry_no', 50)->nullable()->index();
            $table->string('sc_code', 200)->nullable();
            $table->string('sc_mile_id', 100)->nullable();

            $table->string('followup_type', 50)->nullable();
            $table->string('remark_type', 200)->nullable();
            $table->date('planned_followup_date')->nullable();
            $table->date('actual_followup_date')->nullable();
            $table->text('remarks')->nullable();
            $table->text('comments')->nullable();

            $table->date('enquiry_date')->nullable();
            $table->string('enquiry_type', 50)->nullable();
            $table->string('enquiry_source', 50)->nullable();
            $table->string('enquiry_sub_source', 50)->nullable();
            $table->string('enquiry_status', 50)->nullable();
            $table->string('purchase_type', 50)->nullable();
            $table->string('deviation_stage', 100)->nullable();

            $table->string('customer_name', 200)->nullable();
            $table->string('customer_phone', 15)->nullable();

            $table->string('model_name', 150)->nullable();
            $table->string('variant_description', 150)->nullable();
            $table->string('seating_capacity', 50)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->string('color', 100)->nullable();

            $table->string('dealer_branch', 100)->nullable();
            $table->string('dealer_location', 100)->nullable();
            $table->string('segment', 100)->nullable();
            $table->string('segment_code', 50)->nullable();

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
        Schema::dropIfExists('xlr8_crm_enquiries_fup');
    }
};
