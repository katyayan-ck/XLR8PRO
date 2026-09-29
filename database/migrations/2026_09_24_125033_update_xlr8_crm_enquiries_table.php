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
        // DEC-088: guarded — the vh_id → vh_code rename in up() never ran here (it sat behind a comment); the later
        // align migration does it fail-safe, so only undo what exists.
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('xlr8_crm_enquiries', 'duplicate')) {
                $table->dropColumn('duplicate');
            }
            if (Schema::hasColumn('xlr8_crm_enquiries', 'vh_code') && ! Schema::hasColumn('xlr8_crm_enquiries', 'vh_id')) {
                $table->renameColumn('vh_code', 'vh_id');
            }
        });
    }
};
