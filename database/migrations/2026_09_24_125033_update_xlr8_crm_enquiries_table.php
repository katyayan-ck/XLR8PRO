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
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {             // 1. Rename vh_id to vh_code$table->renameColumn('vh_id', 'vh_code');

            // 2. Add JSON column 'duplicate' after 'otf_no'
            $table->json('duplicate')->nullable()->after('otf_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropColumn('duplicate');
            $table->renameColumn('vh_code', 'vh_id');
        });
    }
};
