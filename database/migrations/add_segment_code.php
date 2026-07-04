<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->string('segment_code', 50)
                ->nullable()
                ->before('model_code');
        });

        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            $table->string('segment_code', 50)
                ->nullable()
                ->before('model_code');
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropColumn('segment_code');
        });

        Schema::table('xlr8_crm_leads', function (Blueprint $table) {
            $table->dropColumn('segment_code');
        });
    }
};