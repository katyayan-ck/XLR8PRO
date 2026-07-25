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
            
            if (!Schema::hasColumn('xlr8_crm_enquiries', 'consider_make')) {
                $table->string('consider_make', 100)->nullable()->after('purchase_type');
            }

            if (!Schema::hasColumn('xlr8_crm_enquiries', 'consider_model')) {
                $table->string('consider_model', 100)->nullable()->after('consider_make');
            }

            if (!Schema::hasColumn('xlr8_crm_enquiries', 'consider_variant')) {
                $table->string('consider_variant', 100)->nullable()->after('consider_model');
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'consider_make', 
                'consider_model', 
                'consider_variant'
            ]);
        });
    }
};