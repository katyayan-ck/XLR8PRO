<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-104: booking create, edit and OTF save write six columns that
 * xlr8_booking_master never had (confirmed missing in production too), so each
 * of those saves failed with an unknown-column error. Types were reviewed and
 * approved on 26-09-2026 (DEC-025); code-type columns match the enquiry columns
 * their values are copied from. `status_kw` is deliberately not added: only the
 * unused BookingStateService references it.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_booking_master';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'sale_type')) {
                // Form codes: '1' Within State, '2' Outside State.
                $table->string('sale_type', 15)->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'final_data')) {
                // OTF form snapshot (BookingOtfService json_encode/json_decode).
                $table->json('final_data')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'consultant')) {
                // Sales consultant code, same as xlr8_crm_enquiries.x8_sc_code.
                $table->string('consultant', 50)->nullable()->index();
            }
            if (! Schema::hasColumn(self::TABLE, 'buyer_type')) {
                // Same values as xlr8_crm_enquiries.purchase_type (FIRST_TIME_BUY, EXCHANGE_BUY, …).
                $table->string('buyer_type', 50)->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'accessories')) {
                // Comma-separated accessory ids.
                $table->text('accessories')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'segment_code')) {
                // Same as xlr8_crm_enquiries.segment_code.
                $table->string('segment_code', 50)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['consultant', 'segment_code'] as $indexed) {
                if (Schema::hasColumn(self::TABLE, $indexed) && Schema::hasIndex(self::TABLE, [$indexed])) {
                    $table->dropIndex([$indexed]);
                }
            }
            foreach (['sale_type', 'final_data', 'consultant', 'buyer_type', 'accessories', 'segment_code'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
