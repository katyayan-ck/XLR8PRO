<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-079: a price row remembers the price list it came from (PV, CV, BEV, LMM, LMM_TZU, CSD), so holds and the impact
 * summary can work per list. Nullable (rows imported before stay null); set by the price import.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE) && ! Schema::hasColumn(self::TABLE, 'price_list')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('price_list', 20)->nullable()->after('channel')->index('idx_pricing_price_list');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE) && Schema::hasColumn(self::TABLE, 'price_list')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_pricing_price_list');
                $table->dropColumn('price_list');
            });
        }
    }
};
