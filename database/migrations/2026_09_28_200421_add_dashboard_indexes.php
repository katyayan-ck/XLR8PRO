<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-072: indexes for the dashboard counts and the data-scope filters — the enquiry, follow-up and test-drive tables
 * had none on their date / status / scope columns, and the booking satellites none on `bid` (every "via booking" scope
 * and "no insurance / RTO row yet" count scanned them). Additive only; each index is skipped when its table or a
 * column is missing, or it already exists.
 */
return new class extends Migration
{
    /** table => [index name => columns] */
    private const INDEXES = [
        'xlr8_crm_enquiries' => [
            'idx_enq_stage_date' => ['stage', 'enquiry_date'],
            'idx_enq_scope_org' => ['dealer_branch', 'dealer_location'],
            'idx_enq_sc_mile' => ['sc_mile_id'],
            'idx_enq_next_fup' => ['next_planned_followup_date'],
        ],
        'xlr8_crm_enquiries_fup' => [
            'idx_fup_status_planned' => ['followup_status', 'planned_followup_date'],
            'idx_fup_sc_mile' => ['sc_mile_id'],
        ],
        'xlr8_crm_testdrive' => [
            'idx_td_enquiry_no' => ['enquiry_no'],
            'idx_td_created' => ['td_created_date'],
        ],
        'xlr8_booking_amount' => ['idx_bkamt_bid' => ['bid'], 'idx_bkamt_type_date' => ['type', 'date']],
        'xlr8_booking_finance' => ['idx_bkfin_bid' => ['bid']],
        'xlr8_booking_exchange' => ['idx_bkexc_bid' => ['bid']],
        'xlr8_booking_delivered' => ['idx_bkdel_bid' => ['bid']],
        'xlr8_booking_insurance' => ['idx_bkins_bid' => ['bid']],
        'xlr8_booking_rto' => ['idx_bkrto_bid' => ['bid']],
        'xlr8_booking_refund' => ['idx_bkref_entity' => ['entity_type', 'entity_id']],
        'xlr8_booking_master' => ['idx_bkm_status_date' => ['status', 'booking_date']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if ($this->hasIndex($table, $name) || ! Schema::hasColumns($table, $columns)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if ($this->hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $name);
    }
};
