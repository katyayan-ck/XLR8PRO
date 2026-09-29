<?php

use App\Services\Vehicle\Pricing\SheetHeaderService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-083: the Loyalty sheet of Addon-N-Discounts.xlsx works like Exchange — its header registry rows are copied from
 * the EXCHANGE sheet (model, variant, scheme, OEM / dealer share, total with the same aliases). down() removes them.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_sheet_headers';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || DB::table(self::TABLE)->where('sheet_code', 'LOYALTY')->exists()) {
            return;
        }
        $now = now();
        $rows = DB::table(self::TABLE)->where('sheet_code', 'EXCHANGE')->whereNull('deleted_at')->get()
            ->map(fn ($r) => ['sheet_code' => 'LOYALTY', 'field_code' => $r->field_code, 'label' => $r->label, 'aliases' => $r->aliases,
                'data_type' => $r->data_type, 'is_required' => $r->is_required, 'sort_order' => $r->sort_order, 'is_active' => $r->is_active,
                'created_at' => $now, 'updated_at' => $now])
            ->all();
        DB::table(self::TABLE)->insert($rows);
        app(SheetHeaderService::class)->forgetCache();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            DB::table(self::TABLE)->where('sheet_code', 'LOYALTY')->delete();
            app(SheetHeaderService::class)->forgetCache();
        }
    }
};
