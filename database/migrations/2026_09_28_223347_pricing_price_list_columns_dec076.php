<?php

use App\Services\Vehicle\Pricing\SheetHeaderService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-076: the price-list columns the price import reads.
 *  - Ex-showroom per list (user): PV / CV / BEV "Ex-Showroom Price ORG" (unchanged), LMM "Ex Showroom Price(Org)",
 *    LMM TZU "Final Transaction Price" (its scheme columns are not imported), CSD "CSD Final Price".
 *  - PV scheme columns carry no CV- / OV- prefix: unprefixed aliases for the NV fields (the OV block repeats them — the
 *    importer takes the second occurrence as OV).
 *  - LMM: "Assesable Value" + "Freight New", "VIN Scheme" (OEM scheme with GST), "New Dealer Margin" + "Extra Handling",
 *    "LMM Inv Amt"; CSD "Retail Dealer Margin"; TZU "LMM Billing Price After (…)".
 *  - Accessory / Shield discount eligibility (NV + OV) on PV / CV / BEV, stored in four new pricing columns.
 * Reference data only; down() restores the previous labels / aliases and removes what was added.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_sheet_headers';

    private const PRICING = 'xlr8_vehicle_pricing';

    private const ELG = ['curr_acc_elg', 'curr_shield_elg', 'old_acc_elg', 'old_shield_elg'];

    /** sheet => field => [new label, new aliases, previous label, previous aliases] */
    private const RELABEL = [
        'PRICE_LIST_LMM' => ['ex_showroom' => ['Ex Showroom Price(Org)', ['Ex-Showroom Price(Org)', 'Ex-Showroom Price ORG'], 'Ex-Showroom Price ORG', ['Ex-Showroom Price', 'Ex Showroom Price', 'Ex Showroom Price(Org)', 'Ex-Showroom Price(Org)']]],
        'PRICE_LIST_CSD' => ['ex_showroom' => ['CSD Final Price', [], 'Ex-Showroom Price ORG', ['Ex-Showroom Price', 'Ex Showroom Price', 'Ex Showroom Price(Org)', 'Ex-Showroom Price(Org)']]],
        'PRICE_LIST_LMM_TZU' => ['ex_showroom' => ['Final Transaction Price', [], 'Exshowroom Post Subsidy (PM E-DRIVE SUBSIDY)', ['Ex-Showroom Post Subsidy', 'Final Transaction Price']]],
    ];

    /** sheet => field => aliases appended */
    private const ALIASES = [
        'PRICE_LIST_PV' => [
            'curr_oem_scheme' => ['OEM Scheme with GST'],
            'curr_dealer_cont' => ['Dealer Contribution with GST'],
            'curr_total_scheme' => ['Total Consumer Scheme with GST (OEM + Dealer)'],
            'curr_cash_discount' => ['Cash Discount'],
            'curr_acc_discount' => ['Accessories Discount'],
            'curr_shield_discount' => ['Shield Discount'],
        ],
        'PRICE_LIST_LMM' => [
            'asse_value_freight' => ['Assesable Value', 'Assessable Value'],
            'dealer_margin' => ['New Dealer Margin'],
            'dealer_handling' => ['Extra Handling'],
            'mm_inv_amt' => ['LMM Inv Amt'],
            'curr_oem_scheme' => ['VIN Scheme'],
            'curr_dealer_cont' => ['Dealer Contribution with GST'],
            'curr_total_scheme' => ['Total Consumer Scheme with GST (OEM + Dealer)'],
        ],
        'PRICE_LIST_CSD' => ['dealer_margin' => ['Retail Dealer Margin']],
    ];

    /** sheet => [field, label, aliases] added */
    private const ADD = [
        'PRICE_LIST_LMM' => [['freight', 'Freight New', ['Freight']]],
        'PRICE_LIST_LMM_TZU' => [['mm_inv_amt', 'LMM Billing Price After (PM E-DRIVE SUBSIDY)', []]],
        'PRICE_LIST_PV' => [['curr_acc_elg', 'CV-Acc Dsc Elg', ['Acc Dsc Elg']], ['curr_shield_elg', 'CV-Sheld Disc Elg', ['Sheld Disc Elg']], ['old_acc_elg', 'OV-Acc Dsc Elg', []], ['old_shield_elg', 'OV-Sheld Disc Elg', []]],
        'PRICE_LIST_CV' => [['curr_acc_elg', 'CV-Acc Dsc Elg', ['Acc Dsc Elg']], ['curr_shield_elg', 'CV-Sheld Disc Elg', ['Sheld Disc Elg']], ['old_acc_elg', 'OV-Acc Dsc Elg', []], ['old_shield_elg', 'OV-Sheld Disc Elg', []]],
        'PRICE_LIST_BEV' => [['curr_acc_elg', 'CV-Acc Dsc Elg', ['Acc Dsc Elg']], ['curr_shield_elg', 'CV-Sheld Disc Elg', ['Sheld Disc Elg']], ['old_acc_elg', 'OV-Acc Dsc Elg', []], ['old_shield_elg', 'OV-Sheld Disc Elg', []]],
    ];

    /** TZU: no separate scheme discount (user, DEC-076) */
    private const DEACTIVATE = ['PRICE_LIST_LMM_TZU' => ['curr_oem_scheme', 'curr_dealer_cont', 'curr_total_scheme']];

    public function up(): void
    {
        if (Schema::hasTable(self::PRICING)) {
            Schema::table(self::PRICING, function (Blueprint $table) {
                foreach (self::ELG as $column) {
                    if (! Schema::hasColumn(self::PRICING, $column)) {
                        $table->decimal($column, 6, 4)->default(0)->after('old_shield_discount');
                    }
                }
            });
        }
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        foreach (self::RELABEL as $sheet => $fields) {
            foreach ($fields as $field => [$label, $aliases]) {
                $this->row($sheet, $field)->update(['label' => $label, 'aliases' => $this->json($aliases), 'updated_at' => now()]);
            }
        }
        foreach (self::ALIASES as $sheet => $fields) {
            foreach ($fields as $field => $add) {
                $current = $this->aliases($sheet, $field);
                $this->row($sheet, $field)->update(['aliases' => $this->json(array_values(array_unique(array_merge($current, $add)))), 'updated_at' => now()]);
            }
        }
        foreach (self::ADD as $sheet => $rows) {
            $order = (int) DB::table(self::TABLE)->where('sheet_code', $sheet)->max('sort_order');
            foreach ($rows as [$field, $label, $aliases]) {
                if (! $this->row($sheet, $field)->exists()) {
                    DB::table(self::TABLE)->insert([
                        'sheet_code' => $sheet, 'field_code' => $field, 'label' => $label, 'aliases' => $this->json($aliases),
                        'data_type' => 'decimal', 'is_required' => 0, 'sort_order' => ++$order, 'is_active' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
        foreach (self::DEACTIVATE as $sheet => $fields) {
            DB::table(self::TABLE)->where('sheet_code', $sheet)->whereIn('field_code', $fields)->update(['is_active' => 0, 'updated_at' => now()]);
        }
        app(SheetHeaderService::class)->forgetCache();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            foreach (self::DEACTIVATE as $sheet => $fields) {
                DB::table(self::TABLE)->where('sheet_code', $sheet)->whereIn('field_code', $fields)->update(['is_active' => 1]);
            }
            foreach (self::ADD as $sheet => $rows) {
                DB::table(self::TABLE)->where('sheet_code', $sheet)->whereIn('field_code', array_column($rows, 0))->delete();
            }
            foreach (self::ALIASES as $sheet => $fields) {
                foreach ($fields as $field => $added) {
                    $left = array_values(array_diff($this->aliases($sheet, $field), $added));
                    $this->row($sheet, $field)->update(['aliases' => $this->json($left)]);
                }
            }
            foreach (self::RELABEL as $sheet => $fields) {
                foreach ($fields as $field => [, , $label, $aliases]) {
                    $this->row($sheet, $field)->update(['label' => $label, 'aliases' => $this->json($aliases)]);
                }
            }
            app(SheetHeaderService::class)->forgetCache();
        }
        if (Schema::hasTable(self::PRICING)) {
            Schema::table(self::PRICING, function (Blueprint $table) {
                foreach (self::ELG as $column) {
                    if (Schema::hasColumn(self::PRICING, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function row(string $sheet, string $field): Builder
    {
        return DB::table(self::TABLE)->where('sheet_code', $sheet)->where('field_code', $field)->whereNull('deleted_at');
    }

    /** @return list<string> */
    private function aliases(string $sheet, string $field): array
    {
        return (array) json_decode((string) $this->row($sheet, $field)->value('aliases'), true);
    }

    /** @param list<string> $aliases */
    private function json(array $aliases): ?string
    {
        return $aliases === [] ? null : json_encode($aliases);
    }
};
