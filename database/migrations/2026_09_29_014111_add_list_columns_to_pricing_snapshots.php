<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-081: the Price List screens read snapshots by list, so each snapshot carries its price list (PV, CV, BEV, LMM,
 * LMM_TZU, CSD) and the vehicle's own permit (a taxi-priced car's extra PASSENGER snapshot has permit ≠ vehicle_permit).
 * Both mirror the payload; existing rows are back-filled from it.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_snapshots';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'price_list')) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('price_list', 20)->nullable()->after('permit');
            $table->string('vehicle_permit', 20)->nullable()->after('price_list');
            $table->index(['price_list', 'channel', 'vin_type', 'is_active'], 'idx_snapshots_list');
        });
        DB::table(self::TABLE)->update([
            'price_list' => DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.price_list')), 'null')"),
            'vehicle_permit' => DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.vehicle_permit')), 'null')"),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE) && Schema::hasColumn(self::TABLE, 'price_list')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_snapshots_list');
                $table->dropColumn(['price_list', 'vehicle_permit']);
            });
        }
    }
};
