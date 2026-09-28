<?php

use App\Services\Vehicle\Pricing\SheetHeaderService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-078: lossless storage for the standalone Insurance / RTO workbooks.
 *  - rto_rules: seater, assessable_range
 *  - ins_base_rules: heads (JSON, every OD / TP head as written), tp_pa_owner
 *  - ins_addon_rates: base_rule_id (rates per company / permit / band / plan row), rate_text
 *  - sheet_headers: RTO_RULES, INSU_COMPANY, INSU_PREMIUM, PERMIT_MAP (reference labels + typo aliases)
 * The tables hold no rows yet; down() drops exactly what up() added.
 */
return new class extends Migration
{
    private const HEADERS = 'xlr8_vehicle_pricing_sheet_headers';

    /** sheet => [field, label, aliases] */
    private const REGISTRY = [
        'RTO_RULES' => [
            ['permit', 'Permit', []], ['wheels', 'Wheels', []], ['reg_type', 'Reg Type', ['Registration Type']], ['body_type', 'Body Type', []],
            ['gvw_range', 'GVW', []], ['seater', 'Seater', ['Seating', 'Seats']], ['fuel_type', 'Fuel', []], ['cc_range', 'CC', []],
            ['assessable_range', 'Assesable Value', ['Assessable Value']], ['trc', 'Outside State - TRC Only', ['Outside State - TRC', 'TRC']],
            ['tax_basis', 'Tax Factor', ['Tax Basis']], ['tax_slab', 'Tax Slab', []], ['surcharge', 'Surcharge', []],
            ['hypothecation', 'Hypothecation', []], ['green_tax', 'Green Tax', []], ['registration_fee', 'Registration Fee', []],
            ['duplicate_tax_card', 'Duplicate Tax Card', []], ['fitness', 'Fitness', []], ['penalty', 'Penalty', []], ['total', 'Total Amount', ['Total']],
        ],
        'INSU_COMPANY' => [
            ['model', 'Model', ['OEM Model']], ['permit', 'Permit', []],
            ['company_1', 'Insu Co. 1', ['Insurance Co 1']], ['company_2', 'Insu Co. 2', ['Insurance Co 2']], ['company_3', 'Insu Co. 3', ['Insurance Co 3']],
        ],
        'INSU_PREMIUM' => [
            ['company', 'Insu Co.', ['Insurance Co', 'Insurance Company']], ['permit', 'Permit', []], ['wheels', 'Wheels', []], ['fuel', 'Fuel', []],
            ['cc', 'CC', ['CC / Power', 'Power']], ['gvw', 'GVW', []], ['seating', 'Seating', ['Seatng', 'Seater']], ['plan', 'Plan', []],
            ['idv_1', 'IDV 1', []], ['idv_2', 'IDV 2', []], ['idv_3', 'IDV 3', []],
            ['od_factor', 'OD - OD Factor', ['OD Factor']], ['od_cng_kit', 'OD - CNG / LPG Kit', []], ['od_imt23', 'OD - IMT 23', []],
            ['tp_basic', 'TP Cover Basic', []], ['tp_per_pass', 'TP Cover Per Pass', []], ['tp_bi_fuel', 'TP Cover Bi Fuel Kit (CNG / LPG)', []],
            ['tp_pa_owner', 'TP - Compulsory PA Cover Owner Driver', []], ['tp_pa_passengers', 'TP - PA Cover for Passengers (1 Lakh Per Person)', []],
            ['tp_ll_driver', 'TP - Legal Liability to Driver', []], ['tp_ll_non_fare', 'TP - Legal Liability for Non Fare Paying Passengers', []],
            ['addon_nil_dep', 'Add On - Nil Depreciation', []], ['addon_consumables', 'Add On - Consumables', ['Add On - Cosnumables']],
            ['addon_engine', 'Add On - Engine Protect', []], ['addon_battery_full', 'Add On - Battery Protect (Including Motor, Charger & Adapter)', []],
            ['addon_battery_motor', 'Add On - Battery Protect (Including Motor)', []], ['addon_battery', 'Add On - Battery Protect', []],
            ['addon_charger', 'Add On - Charger & Adapter Cover', []], ['addon_motor', 'Add On - Motor Protect', []],
            ['addon_tyre', 'Add On - Tyre Protection', []], ['addon_rti', 'Add On - Return to Invoice', []], ['addon_key', 'Add On - Key Replacement', []],
            ['addon_rsa', 'Add On - RSA', []], ['addon_belongings', 'Add On - Personal Belongings', []], ['addon_daily_cash', 'Add On - Daily Cash', []],
            ['addon_advance', 'Add On - Advance Assistance Cover', []], ['addon_medical', 'Add On - Medical Expenses Cover', []], ['addon_towing', 'Add On - Towing', []],
        ],
        'PERMIT_MAP' => [
            ['vehicle_permit', 'Vehicle Permit', []], ['wheels', 'Wheels', []], ['rto_permit', 'RTO Permit', []],
            ['insu_permit', 'Insurance Permit', ['Insu Permit']], ['label', 'Label', []],
        ],
    ];

    public function up(): void
    {
        $this->addColumns('xlr8_vehicle_pricing_rto_rules', ['seater' => fn (Blueprint $t) => $t->string('seater', 20)->nullable()->after('gvw_range'),
            'assessable_range' => fn (Blueprint $t) => $t->string('assessable_range', 40)->nullable()->after('cc_range')]);
        $this->addColumns('xlr8_vehicle_pricing_ins_base_rules', ['heads' => fn (Blueprint $t) => $t->json('heads')->nullable()->after('tp_bi_fuel_kit'),
            'tp_pa_owner' => fn (Blueprint $t) => $t->decimal('tp_pa_owner', 15, 2)->default(0)->after('tp_bi_fuel_kit')]);
        $this->addColumns('xlr8_vehicle_pricing_ins_addon_rates', ['base_rule_id' => fn (Blueprint $t) => $t->unsignedBigInteger('base_rule_id')->nullable()->index('idx_ins_addon_base_rule')->after('import_session_id'),
            'rate_text' => fn (Blueprint $t) => $t->string('rate_text', 60)->nullable()->after('rate_value')]);

        if (Schema::hasTable(self::HEADERS)) {
            foreach (self::REGISTRY as $sheet => $rows) {
                foreach ($rows as $i => [$field, $label, $aliases]) {
                    if (! DB::table(self::HEADERS)->where('sheet_code', $sheet)->where('field_code', $field)->whereNull('deleted_at')->exists()) {
                        DB::table(self::HEADERS)->insert([
                            'sheet_code' => $sheet, 'field_code' => $field, 'label' => $label, 'aliases' => $aliases === [] ? null : json_encode($aliases),
                            'data_type' => 'string', 'is_required' => in_array($field, ['permit', 'model', 'company', 'vehicle_permit'], true) ? 1 : 0,
                            'sort_order' => ($i + 1) * 10, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
            app(SheetHeaderService::class)->forgetCache();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::HEADERS)) {
            DB::table(self::HEADERS)->whereIn('sheet_code', array_keys(self::REGISTRY))->delete();
            app(SheetHeaderService::class)->forgetCache();
        }
        $this->dropColumns('xlr8_vehicle_pricing_ins_addon_rates', ['base_rule_id', 'rate_text'], 'idx_ins_addon_base_rule');
        $this->dropColumns('xlr8_vehicle_pricing_ins_base_rules', ['heads', 'tp_pa_owner']);
        $this->dropColumns('xlr8_vehicle_pricing_rto_rules', ['seater', 'assessable_range']);
    }

    /** @param array<string, Closure(Blueprint): mixed> $columns */
    private function addColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $column => $define) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $define($t));
            }
        }
    }

    /** @param list<string> $columns */
    private function dropColumns(string $table, array $columns, ?string $index = null): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        if ($index !== null && collect(Schema::getIndexes($table))->pluck('name')->contains($index)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
        }
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
