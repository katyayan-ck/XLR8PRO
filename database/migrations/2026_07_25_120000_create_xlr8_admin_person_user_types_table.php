<?php
// database/migrations/2026_07_25_120000_create_xlr8_admin_person_user_types_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xlr8_admin_person_user_types', function (Blueprint $table) {
            $table->id();

            $table->string('person_code', 20);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 30);               // Emp / Cust / DSA / Insurer / Associate / Promoter / Referrer ...
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('person_code');
            $table->index('user_id');
            $table->index('user_type');
            $table->index(['is_primary', 'is_active']);

            // One active record per person + user_type
            $table->unique(['person_code', 'user_type', 'deleted_at'], 'uq_person_user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_admin_person_user_types');
    }
};