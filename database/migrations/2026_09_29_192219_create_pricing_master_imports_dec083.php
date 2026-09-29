<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-083: one row per pricing-master import (queued): which master, the stored upload, WEF, progress and the result
 * (counts + the first issues) shown on the master's screen.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_pricing_master_imports';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('master', 40);
            $table->string('status', 16)->default('queued');   // queued | running | done | failed
            $table->string('file_name', 190)->nullable();
            $table->string('path', 255);
            $table->date('wef_date')->nullable();
            $table->string('message', 255)->nullable();
            $table->json('result')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['master', 'created_at'], 'idx_master_imports_master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
