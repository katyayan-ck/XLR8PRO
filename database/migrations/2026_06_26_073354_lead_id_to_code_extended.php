<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->string('source_code', 50)->nullable()->after('enquiry_date');
            $table->string('lead_no', 50)->nullable()->after('source_code');

            $table->index('source_code');
            $table->index('lead_no');
        });

        // Migrate data if any exists
        DB::statement("
            UPDATE xlr8_crm_enquiries e
            JOIN xlr8_crm_lead_sources s ON s.id = e.source_id
            SET e.source_code = s.code
            WHERE e.source_id IS NOT NULL
        ");

        DB::statement("
            UPDATE xlr8_crm_enquiries e
            JOIN xlr8_crm_leads l ON l.id = e.lead_id
            SET e.lead_no = l.lead_no
            WHERE e.lead_id IS NOT NULL
        ");

        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropIndex(['source_id']);
            $table->dropIndex(['lead_id']);
            $table->dropColumn(['source_id', 'lead_id']);
        });

        Schema::table('xlr8_crm_quotations', function (Blueprint $table) {
            $table->string('enquiry_no', 50)->nullable()->after('quotation_no');
            $table->index('enquiry_no');
        });

        DB::statement("
            UPDATE xlr8_crm_quotations q
            JOIN xlr8_crm_enquiries e ON e.id = q.enquiry_id
            SET q.enquiry_no = e.enquiry_no
            WHERE q.enquiry_id IS NOT NULL
        ");

        Schema::table('xlr8_crm_quotations', function (Blueprint $table) {
            $table->dropIndex(['enquiry_id']);
            $table->dropColumn('enquiry_id');
        });

        Schema::table('xlr8_crm_quote_actions', function (Blueprint $table) {
            $table->string('quotation_no', 50)->nullable()->after('id');
            $table->index('quotation_no');
        });

        DB::statement("
            UPDATE xlr8_crm_quote_actions qa
            JOIN xlr8_crm_quotations q ON q.id = qa.quotation_id
            SET qa.quotation_no = q.quotation_no
            WHERE qa.quotation_id IS NOT NULL
        ");

        Schema::table('xlr8_crm_quote_actions', function (Blueprint $table) {
            $table->dropIndex(['quotation_id']);
            $table->dropColumn('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('enquiry_date');
            $table->unsignedBigInteger('lead_id')->nullable()->after('source_id');
            $table->index('source_id');
            $table->index('lead_id');
        });

        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropIndex(['source_code']);
            $table->dropIndex(['lead_no']);
            $table->dropColumn(['source_code', 'lead_no']);
        });

        Schema::table('xlr8_crm_quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('enquiry_id')->nullable()->after('quotation_no');
            $table->index('enquiry_id');
        });

        Schema::table('xlr8_crm_quotations', function (Blueprint $table) {
            $table->dropIndex(['enquiry_no']);
            $table->dropColumn('enquiry_no');
        });

        Schema::table('xlr8_crm_quote_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('quotation_id')->nullable()->after('id');
            $table->index('quotation_id');
        });

        Schema::table('xlr8_crm_quote_actions', function (Blueprint $table) {
            $table->dropIndex(['quotation_no']);
            $table->dropColumn('quotation_no');
        });
    }
    
};