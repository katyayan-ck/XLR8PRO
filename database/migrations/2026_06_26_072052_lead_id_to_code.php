<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            // Add new code-based column
            $table->string('source_code', 50)->nullable()->after('capture_date');
            $table->index('source_code');
        });

        // Migrate existing data (if any) from source_id → source_code
        DB::statement("
            UPDATE xlr8_crm_leads l
            JOIN xlr8_crm_lead_sources s ON s.id = l.source_id
            SET l.source_code = s.code
            WHERE l.source_id IS NOT NULL
        ");

        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            // Remove old id-based column and its index
            $table->dropIndex(['source_id']);
            $table->dropColumn('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('capture_date');
            $table->index('source_id');
        });

        // Reverse data migration (best effort)
        DB::statement("
            UPDATE xlr8_crm_leads l
            JOIN xlr8_crm_lead_sources s ON s.code = l.source_code
            SET l.source_id = s.id
            WHERE l.source_code IS NOT NULL
        ");

        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            $table->dropIndex(['source_code']);
            $table->dropColumn('source_code');
        });
    }
};