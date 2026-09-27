<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-071 (also D22 / BUG-161 / BUG-092): a booking carries its own branch / location and vehicle codes so user data
 * scoping can filter it directly. segment_code already exists (BUG-104). Filled on create and OTF save, and for old
 * rows by `php artisan data-scope:backfill`. Nullable: an empty code counts as "unassigned" (scope.unassigned_rows).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'branch_code' => 25,
        'location_code' => 25,
        'sub_segment_code' => 20,
        'model_code' => 40,
        'variant_code' => 40,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_booking_master')) {
            return;
        }

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('xlr8_booking_master', $column)) {
                    $table->string($column, $length)->nullable()->after('segment_code');
                }
            }
        });

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            foreach (['branch_code', 'location_code', 'model_code'] as $column) {
                if (! $this->hasIndex("idx_booking_master_{$column}")) {
                    $table->index($column, "idx_booking_master_{$column}");
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_booking_master')) {
            return;
        }

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            foreach (['branch_code', 'location_code', 'model_code'] as $column) {
                if ($this->hasIndex("idx_booking_master_{$column}")) {
                    $table->dropIndex("idx_booking_master_{$column}");
                }
            }
        });

        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('xlr8_booking_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('xlr8_booking_master'))->contains(fn (array $index) => $index['name'] === $name);
    }
};
