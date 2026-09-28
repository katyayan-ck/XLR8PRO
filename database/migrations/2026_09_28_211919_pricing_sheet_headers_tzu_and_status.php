<?php

use App\Services\Vehicle\Pricing\SheetHeaderService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-073: header registry rows the redesigned Detect / price import need.
 *  - PRICE_LIST_LMM_TZU had no rows (it fell back to PV and found nothing): its real labels are M.CODE, Model Name,
 *    Material Description, Status, Assessable Value, GST on Assessable Value @ 5%, New Dealer Marging, the pre / post
 *    subsidy ex-showroom and the scheme columns.
 *  - Every price list gains a `status` field (Live / Discontinued on PV / CV).
 * Reference data, added only where missing; down() removes exactly the rows added.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_sheet_headers';

    /** field_code => [label, aliases, data_type, required] */
    private const TZU = [
        'model_code' => ['M.CODE', ['M CODE', 'MCODE', 'Material Code'], 'string', true],
        'oem_model' => ['Model Name', ['Model Group'], 'string', false],
        'oem_variant' => ['Material Description', [], 'string', false],
        'status' => ['Status', [], 'string', false],
        'asse_value_freight' => ['Assessable Value', ['Asse Value with Freight'], 'decimal', false],
        'gst_amount' => ['GST on Assessable Value @ 5%', ['GST Amount'], 'decimal', false],
        'dealer_margin' => ['New Dealer Marging', ['New Dealer Margin', 'Dealer Margin'], 'decimal', false],
        'ex_showroom_pre_subsidy' => ['Ex-Showroom Pre Subsidy', [], 'decimal', false],
        'subsidy' => ['PM E-DRIVE SUBSIDY Subsidy', [], 'decimal', false],
        'ex_showroom' => ['Exshowroom Post Subsidy (PM E-DRIVE SUBSIDY)', ['Ex-Showroom Post Subsidy', 'Final Transaction Price'], 'decimal', true],
        'curr_oem_scheme' => ['OEM Scheme @ BNDP', [], 'decimal', false],
        'curr_dealer_cont' => ['Dealer Contribution with GST', [], 'decimal', false],
        'curr_total_scheme' => ['Total Consumer Scheme with GST (OEM + Dealer)', [], 'decimal', false],
    ];

    private const LISTS = ['PRICE_LIST_PV', 'PRICE_LIST_CV', 'PRICE_LIST_BEV', 'PRICE_LIST_LMM', 'PRICE_LIST_CSD'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        $order = 1;
        foreach (self::TZU as $field => [$label, $aliases, $type, $required]) {
            $this->add('PRICE_LIST_LMM_TZU', $field, $label, $aliases, $type, $required, $order++);
        }
        foreach (self::LISTS as $sheet) {
            $this->add($sheet, 'status', 'Status', [], 'string', false, 4);
        }
        app(SheetHeaderService::class)->forgetCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        DB::table(self::TABLE)->where('sheet_code', 'PRICE_LIST_LMM_TZU')->delete();
        DB::table(self::TABLE)->whereIn('sheet_code', self::LISTS)->where('field_code', 'status')->delete();
        app(SheetHeaderService::class)->forgetCache();
    }

    /** @param list<string> $aliases */
    private function add(string $sheet, string $field, string $label, array $aliases, string $type, bool $required, int $order): void
    {
        $exists = DB::table(self::TABLE)->where('sheet_code', $sheet)->where('field_code', $field)->whereNull('deleted_at')->exists();
        if ($exists) {
            return;
        }
        DB::table(self::TABLE)->insert([
            'sheet_code' => $sheet, 'field_code' => $field, 'label' => $label,
            'aliases' => $aliases === [] ? null : json_encode($aliases), 'data_type' => $type,
            'is_required' => $required, 'sort_order' => $order, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
