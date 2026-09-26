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
            $table->string('referee_model', 255)
                ->nullable()
                ->after('referee_name');

            $table->string('referee_variant', 255)
                ->nullable()
                ->after('referee_model');

            $table->string('referee_chassis', 255)
                ->nullable()
                ->after('referee_variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlr8_crm_enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'referee_model',
                'referee_variant',
                'referee_chassis',
            ]);
        });
    }
};