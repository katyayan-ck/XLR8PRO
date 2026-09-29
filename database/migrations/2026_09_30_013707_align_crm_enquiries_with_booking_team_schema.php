<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-088: align `xlr8_crm_enquiries` with the booking team's schema (their dump `booking.sql`, 29-09-2026), so the
 * merged stage code runs on every environment. Fail-safe: every step checks the current state first and is skipped when
 * it is already applied (or cannot be applied without losing data), so it runs cleanly on our DB, on theirs and on a
 * fresh one.
 *
 *  - `cre_lost_reason`, `cre_lost_sub_reason` (VARCHAR 100, NULL): used by the stage lost-enquiry screens.
 *  - `vh_id` → `vh_code`: what `2026_09_24_125033_update_xlr8_crm_enquiries_table` meant to do (its rename sat behind a
 *    `//` comment, so it never ran here).
 *  - `idx_mobile` index on `mobile` (theirs; the duplicate-enquiry lookups filter on it).
 *  - `x8_enq_source`: dropped only when every row is empty (theirs no longer has it; our code references are
 *    commented out). With data in it the column is kept and a warning is logged.
 *
 * Deliberately NOT aligned (ours is newer): `xlr8_vehicle_variant` defaults (`wheels`, `taxi_price`, `is_active` —
 * relaxed by DEC-073 so a stub never looks complete) and their `model_code + code + color_code` UNIQUE index
 * (`VariantService` enforces uniqueness; a DB index would also count soft-deleted rows).
 */
return new class extends Migration
{
    private string $table = 'xlr8_crm_enquiries';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        if (! Schema::hasColumn($this->table, 'cre_lost_reason')) {
            Schema::table($this->table, function (Blueprint $table) {
                $column = $table->string('cre_lost_reason', 100)->nullable();
                if (Schema::hasColumn($this->table, 'lost_remarks')) {
                    $column->after('lost_remarks');
                }
            });
        }

        if (! Schema::hasColumn($this->table, 'cre_lost_sub_reason')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->string('cre_lost_sub_reason', 100)->nullable()->after('cre_lost_reason');
            });
        }

        if (Schema::hasColumn($this->table, 'vh_id') && ! Schema::hasColumn($this->table, 'vh_code')) {
            Schema::table($this->table, fn (Blueprint $table) => $table->renameColumn('vh_id', 'vh_code'));
        }

        if (Schema::hasColumn($this->table, 'mobile') && ! $this->hasIndex('idx_mobile')) {
            Schema::table($this->table, fn (Blueprint $table) => $table->index('mobile', 'idx_mobile'));
        }

        if (Schema::hasColumn($this->table, 'x8_enq_source')) {
            $filled = DB::table($this->table)->whereNotNull('x8_enq_source')->where('x8_enq_source', '<>', '')->count();
            if ($filled === 0) {
                Schema::table($this->table, fn (Blueprint $table) => $table->dropColumn('x8_enq_source'));
            } else {
                Log::warning('DEC-088: x8_enq_source kept — it holds data', ['rows' => $filled]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        if (! Schema::hasColumn($this->table, 'x8_enq_source')) {
            Schema::table($this->table, fn (Blueprint $table) => $table->string('x8_enq_source', 50)->nullable());
        }

        if ($this->hasIndex('idx_mobile')) {
            Schema::table($this->table, fn (Blueprint $table) => $table->dropIndex('idx_mobile'));
        }

        if (Schema::hasColumn($this->table, 'vh_code') && ! Schema::hasColumn($this->table, 'vh_id')) {
            Schema::table($this->table, fn (Blueprint $table) => $table->renameColumn('vh_code', 'vh_id'));
        }

        foreach (['cre_lost_sub_reason', 'cre_lost_reason'] as $column) {
            if (Schema::hasColumn($this->table, $column)) {
                Schema::table($this->table, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes($this->table))->contains(fn (array $index) => $index['name'] === $name);
    }
};
