<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W16f (DEC-094, FRS help-and-support §7): an append-only help-usage log — articles opened, screens without an
 * article, searches (with / without results), tours finished, support requests sent — for the Help Centre report.
 * Rows older than `help.usage_retention_days` are purged daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xlr8_utils_help_usage')) {
            return;
        }
        Schema::create('xlr8_utils_help_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event', 20)->index();
            $table->string('ref', 190)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_utils_help_usage');
    }
};
