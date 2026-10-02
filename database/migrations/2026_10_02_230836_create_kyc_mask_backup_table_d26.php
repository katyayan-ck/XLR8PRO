<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D26 (DEC-095 #19): before `privacy:mask-kyc-history --apply` masks the Aadhaar / PAN copies in history rows, it keeps
 * each changed cell's original value here, encrypted with the app key, so `--restore` can undo the masking exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xlr8_privacy_kyc_mask_backup')) {
            return;
        }
        Schema::create('xlr8_privacy_kyc_mask_backup', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('source_column', 64);
            $table->longText('original_encrypted');
            $table->timestamp('created_at')->nullable();
            $table->unique(['source_table', 'source_id', 'source_column'], 'kyc_mask_backup_cell_unique');
        });
    }

    public function down(): void
    {
        // Only drop when empty: rows hold the originals needed by --restore.
        if (Schema::hasTable('xlr8_privacy_kyc_mask_backup') && ! DB::table('xlr8_privacy_kyc_mask_backup')->exists()) {
            Schema::drop('xlr8_privacy_kyc_mask_backup');
        }
    }
};
