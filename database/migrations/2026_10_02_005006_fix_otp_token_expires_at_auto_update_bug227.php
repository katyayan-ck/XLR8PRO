<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-227: `xlr8_iam_otp_token.expires_at` was created as a MySQL TIMESTAMP with `ON UPDATE CURRENT_TIMESTAMP`, so any
 * update of a token row (an attempt counter, a re-hash) silently set its expiry to "now" and the OTP expired at once.
 * The column keeps its type, NOT NULL and default; only the automatic update is removed. No data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('xlr8_iam_otp_token', 'expires_at')) {
            return;
        }
        Schema::table('xlr8_iam_otp_token', function (Blueprint $table) {
            $table->timestamp('expires_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('xlr8_iam_otp_token', 'expires_at')) {
            return;
        }
        Schema::table('xlr8_iam_otp_token', function (Blueprint $table) {
            $table->timestamp('expires_at')->useCurrent()->useCurrentOnUpdate()->change();
        });
    }
};
