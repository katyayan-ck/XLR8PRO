<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-041: `sale_type` was added as varchar(15) by 2026_09_26_120000 on databases where it
 * ran before the booking team's 2026_09_23_101136 (unsigned tinyint, codes 1/2). Converts
 * such a column to the booking team's type. No-op where it is already numeric; refuses to
 * run if any stored value is not a number.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_booking_master';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'sale_type') || $this->isNumeric()) {
            return;
        }

        $bad = DB::table(self::TABLE)->whereNotNull('sale_type')->whereRaw("sale_type NOT REGEXP '^[0-9]+$'")->count();
        if ($bad > 0) {
            throw new RuntimeException("{$bad} xlr8_booking_master.sale_type values are not numeric; fix them before converting.");
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unsignedTinyInteger('sale_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as the booking team's type.
    }

    private function isNumeric(): bool
    {
        return str_contains(strtolower(Schema::getColumnType(self::TABLE, 'sale_type')), 'int');
    }
};
