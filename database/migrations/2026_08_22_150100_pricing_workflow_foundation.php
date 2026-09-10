<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Pricing workflow foundation (FRS v3.1)
 *
 * - Sheet header registry (field_code stable; label may change)
 * - Import session staged columns + discard support
 * - import_session_id on writable pricing tables
 * - vehicle_variant.shield_pack
 * - Seed headers for Price Lists, Vehicle Info, RSA, Shield, Dealer Charges
 *
 * Vehicle detection is from Price List sheets only (PV/CV Vehicle sheets out of scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. Sheet header registry
        // ------------------------------------------------------------------
        Schema::dropIfExists('xlr8_vehicle_pricing_sheet_headers');

        Schema::create('xlr8_vehicle_pricing_sheet_headers', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_code', 64)->comment('PRICE_LIST_PV, VEHICLE_INFO, RSA, SHIELD, ...');
            $table->string('field_code', 64)->comment('Stable code used in application logic');
            $table->string('label', 191)->comment('Current Excel header text');
            $table->json('aliases')->nullable()->comment('Alternate labels that still map to field_code');
            $table->string('data_type', 32)->default('string')->comment('string|decimal|int|date|bool');
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['sheet_code', 'field_code'], 'uq_sheet_field');
            $table->index(['sheet_code', 'is_active'], 'idx_sheet_active');
        });

        // ------------------------------------------------------------------
        // 2. Import session – staged workflow columns
        // ------------------------------------------------------------------
        if (Schema::hasTable('xlr8_vehicle_pricing_import_sessions')) {
            Schema::table('xlr8_vehicle_pricing_import_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'current_stage')) {
                    $table->string('current_stage', 32)->nullable()->default('idle')
                        ->comment('idle|detecting|awaiting_vehicle|importing_prices|awaiting_addons|importing_addons|awaiting_rules|calculating|summary|completed|cancelled')
                        ->after('status');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'selected_sheets')) {
                    $table->json('selected_sheets')->nullable()->after('current_stage');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'wef_date')) {
                    $table->date('wef_date')->nullable()->after('selected_sheets');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'hold_scopes')) {
                    $table->json('hold_scopes')->nullable()->after('wef_date');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'stats')) {
                    $table->json('stats')->nullable()->after('hold_scopes');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('stats');
                }
                if (!Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
                }
            });

            try {
                DB::statement("ALTER TABLE `xlr8_vehicle_pricing_import_sessions` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'idle'");
            } catch (\Throwable $e) {
                // already compatible
            }
        }

        // ------------------------------------------------------------------
        // 3. import_session_id on writable pricing tables (discard/rollback)
        // ------------------------------------------------------------------
        $sessionTaggedTables = [
            'xlr8_vehicle_pricing_profile',
            'xlr8_vehicle_pricing',
            'xlr8_vehicle_pricing_addons',
            'xlr8_vehicle_pricing_discounts',
            'xlr8_vehicle_pricing_dealer_charges',
            'xlr8_vehicle_pricing_rto_rules',
            'xlr8_vehicle_pricing_ins_defaults',
            'xlr8_vehicle_pricing_ins_base_rules',
            'xlr8_vehicle_pricing_ins_addon_rates',
            'xlr8_vehicle_pricing_change_flags',
            'xlr8_vehicle_pricing_draft',
            'xlr8_vehicle_pricing_affected',
        ];

        foreach ($sessionTaggedTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'import_session_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unsignedBigInteger('import_session_id')->nullable()->after('id');
                $table->index('import_session_id', 'idx_' . substr(md5($tableName), 0, 8) . '_sess');
            });
        }

        // ------------------------------------------------------------------
        // 4. Vehicle variant – shield_pack
        // ------------------------------------------------------------------
        if (Schema::hasTable('xlr8_vehicle_variant')
            && !Schema::hasColumn('xlr8_vehicle_variant', 'shield_pack')
        ) {
            Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
                $table->string('shield_pack', 25)->nullable()->default(null)->after('csd_index');
            });
        }

        // ------------------------------------------------------------------
        // 5. Synonym table (typo normalization project-wide)
        // ------------------------------------------------------------------
        Schema::dropIfExists('xlr8_utils_synonyms');

        Schema::create('xlr8_utils_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->comment('Branch, Fuel, Segment, Permit, City, ...');
            $table->string('canonical', 128)->comment('Stable code / master value');
            $table->string('synonym', 128)->comment('Alternate spelling');
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['entity_type', 'synonym'], 'uq_entity_synonym');
            $table->index(['entity_type', 'canonical'], 'idx_entity_canonical');
            $table->index(['entity_type', 'is_active'], 'idx_entity_active');
        });

        // ------------------------------------------------------------------
        // 6. Seed sheet headers (Price Lists + Vehicle Info + Addons)
        // ------------------------------------------------------------------
        $now = now();
        foreach ($this->seedHeaders() as $row) {
            DB::table('xlr8_vehicle_pricing_sheet_headers')->insert(array_merge($row, [
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Seed common Segment / Fuel synonyms (RSA typos etc.)
        foreach ($this->seedSynonyms() as $row) {
            DB::table('xlr8_utils_synonyms')->insert(array_merge($row, [
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_vehicle_pricing_sheet_headers');
        Schema::dropIfExists('xlr8_utils_synonyms');

        if (Schema::hasTable('xlr8_vehicle_variant')
            && Schema::hasColumn('xlr8_vehicle_variant', 'shield_pack')
        ) {
            Schema::table('xlr8_vehicle_variant', function (Blueprint $table) {
                $table->dropColumn('shield_pack');
            });
        }
    }

    private function seedHeaders(): array
    {
        $rows = [];

        // Vehicle Info (export for FRESH/incomplete + re-import)
        $vehicleInfo = [
            ['model_code', 'Model Code', null, 'string', 1, 10],
            ['oem_model', 'OEM Model', null, 'string', 0, 20],
            ['oem_variant', 'OEM Variant', null, 'string', 0, 30],
            ['segment', 'Segment', null, 'string', 0, 40],
            ['sub_segment', 'Sub Segment', null, 'string', 0, 50],
            ['fuel', 'Fuel', null, 'string', 0, 60],
            ['seating', 'Seating', null, 'int', 0, 70],
            ['wheels', 'Wheels', null, 'int', 0, 80],
            ['transmission', 'Transmission', null, 'string', 0, 90],
            ['drivetrain', 'Drivetrain', null, 'string', 0, 100],
            ['body_make', 'Body Make', null, 'string', 0, 110],
            ['body_type', 'Body Type', null, 'string', 0, 120],
            ['cc', 'CC', null, 'string', 0, 130],
            ['motor', 'Motor', null, 'string', 0, 140],
            ['gvw', 'GVW', null, 'string', 0, 150],
            ['gst_pct', 'GST%', json_encode(['GST', 'GST %']), 'decimal', 0, 160],
            ['permit', 'Permit', null, 'string', 0, 170],
            ['taxi_price', 'Taxi Price', null, 'string', 0, 180],
            ['custom_model', 'Custom Model', null, 'string', 0, 190],
            ['custom_variant', 'Custom Variant', null, 'string', 0, 200],
            ['display_name', 'Display Name', null, 'string', 0, 210],
            ['colour_name', 'Colour Name', null, 'string', 0, 220],
            ['status', 'Status', null, 'string', 0, 230],
            ['shield_pack', 'Shield Pack', null, 'string', 0, 240],
            ['is_incomplete', 'Is Incomplete', null, 'string', 0, 250],
        ];
        foreach ($vehicleInfo as [$code, $label, $aliases, $type, $req, $ord]) {
            $rows[] = [
                'sheet_code'  => 'VEHICLE_INFO',
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => $aliases,
                'data_type'   => $type,
                'is_required' => $req,
                'sort_order'  => $ord,
            ];
        }

        // Price lists — detection + pricing (same headers for PV/CV/BEV; LMM may share core)
        $priceListCore = [
            ['model_code', 'Model Code', null, 'string', 1, 10],
            ['oem_model', 'OEM Model', null, 'string', 0, 20],
            ['oem_variant', 'OEM Variant', null, 'string', 0, 30],
            ['asse_value_freight', 'Asse Value with Freight', json_encode(['AssVal With Freight', 'Assessable Value with Freight', 'Asse Value With Freight']), 'decimal', 0, 40],
            ['gst_pct', 'GST', json_encode(['GST%', 'GST %']), 'decimal', 0, 50],
            ['gst_amount', 'GST Amount', null, 'decimal', 0, 60],
            ['mm_inv_amt', 'MM Inv Amt', json_encode(['MM Invoice Amt', 'MM Inv Amount']), 'decimal', 0, 70],
            ['dealer_margin', 'Dealer Margin', null, 'decimal', 0, 80],
            ['dealer_handling', 'Dealer Handling', null, 'decimal', 0, 90],
            ['ex_showroom', 'Ex-Showroom Price ORG', json_encode(['Ex-Showroom Price', 'Ex Showroom Price', 'Ex Showroom Price(Org)', 'Ex-Showroom Price(Org)']), 'decimal', 1, 100],
            ['curr_oem_scheme', 'CV-OEM Scheme with GST', json_encode(['CV-OEM Scheme @ BNDP']), 'decimal', 0, 110],
            ['curr_dealer_cont', 'CV-Dealer Contribution with GST', json_encode(['CV-Dealer Cont @ BNDP']), 'decimal', 0, 120],
            ['curr_total_scheme', 'CV-Total Consumer Scheme with GST (OEM + Dealer)', null, 'decimal', 0, 130],
            ['curr_cash_discount', 'CV-Cash Discount', null, 'decimal', 0, 140],
            ['curr_acc_discount', 'CV-Accessories Discount', null, 'decimal', 0, 150],
            ['curr_shield_discount', 'CV-Shield Discount', null, 'decimal', 0, 160],
            ['old_oem_scheme', 'OV-OEM Scheme with GST', json_encode(['OV-OEM Scheme @ BNDP']), 'decimal', 0, 170],
            ['old_dealer_cont', 'OV-Dealer Contribution with GST', json_encode(['OV-Dealer Cont @ BNDP']), 'decimal', 0, 180],
            ['old_total_scheme', 'OV-Total Consumer Scheme with GST (OEM + Dealer)', null, 'decimal', 0, 190],
            ['old_cash_discount', 'OV-Cash Discount', null, 'decimal', 0, 200],
            ['old_acc_discount', 'OV-Accessories Discount', null, 'decimal', 0, 210],
            ['old_shield_discount', 'OV-Shield Discount', null, 'decimal', 0, 220],
        ];

        foreach (['PRICE_LIST_PV', 'PRICE_LIST_CV', 'PRICE_LIST_BEV', 'PRICE_LIST_LMM', 'PRICE_LIST_CSD'] as $sheet) {
            foreach ($priceListCore as [$code, $label, $aliases, $type, $req, $ord]) {
                $rows[] = [
                    'sheet_code'  => $sheet,
                    'field_code'  => $code,
                    'label'       => $label,
                    'aliases'     => $aliases,
                    'data_type'   => $type,
                    'is_required' => $req,
                    'sort_order'  => $ord,
                ];
            }
        }

        // RSA
        foreach ([
            ['segment', 'Segment', null, 'string', 0, 10],
            ['model', 'Model', null, 'string', 1, 20],
            ['std_coverage', 'Standard Coverage', null, 'string', 0, 30],
            ['std_plus_1', 'Std + 1 Year', null, 'decimal', 0, 40],
            ['std_plus_2', 'Std + 2 Years', null, 'decimal', 0, 50],
            ['std_plus_3', 'Std + 3 Years', null, 'decimal', 0, 60],
            ['std_plus_4', 'Std + 4 Years', null, 'decimal', 0, 70],
            ['std_plus_5', 'Std + 5 Years', null, 'decimal', 0, 80],
        ] as [$code, $label, $aliases, $type, $req, $ord]) {
            $rows[] = [
                'sheet_code'  => 'RSA',
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => $aliases,
                'data_type'   => $type,
                'is_required' => $req,
                'sort_order'  => $ord,
            ];
        }

        // Shield
        foreach ([
            ['oem_model', 'OEM Model', null, 'string', 1, 10],
            ['oem_variant', 'OEM Variant', null, 'string', 0, 20],
            ['shield_pack', 'Shield Pack', null, 'string', 0, 30],
            ['transmission', 'Transmission', null, 'string', 0, 40],
            ['fuel', 'Fuel', null, 'string', 0, 50],
            ['standard_warranty', 'Standard Warranty', null, 'string', 0, 60],
            ['scheme_1_name', 'Shield Scheme 1 Name', null, 'string', 0, 70],
            ['scheme_1_amt', 'Shield Scheme 1 Amt', null, 'decimal', 0, 80],
            ['scheme_2_name', 'Shield Scheme 2 Name', null, 'string', 0, 90],
            ['scheme_2_amt', 'Shield Scheme 2 Amt', null, 'decimal', 0, 100],
        ] as [$code, $label, $aliases, $type, $req, $ord]) {
            $rows[] = [
                'sheet_code'  => 'SHIELD',
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => $aliases,
                'data_type'   => $type,
                'is_required' => $req,
                'sort_order'  => $ord,
            ];
        }

        // Dealer Charges
        foreach ([
            ['segment', 'Segment', null, 'string', 1, 10],
            ['permit', 'Permit', null, 'string', 0, 20],
            ['model', 'Model', null, 'string', 0, 30],
            ['incidental_charges', 'Incidental Charges', null, 'decimal', 0, 40],
            ['fast_tag', 'FastTag', json_encode(['Fast Tag', 'Fasttag']), 'decimal', 0, 50],
            ['trc', 'TRC', null, 'decimal', 0, 60],
            ['rto_tape', 'RTO Tape', null, 'decimal', 0, 70],
            ['cod_charges', 'COD Charges', null, 'decimal', 0, 80],
        ] as [$code, $label, $aliases, $type, $req, $ord]) {
            $rows[] = [
                'sheet_code'  => 'DEALER_CHARGES',
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => $aliases,
                'data_type'   => $type,
                'is_required' => $req,
                'sort_order'  => $ord,
            ];
        }

        // CSD Index
        foreach ([
            ['oem_model', 'OEM Model', null, 'string', 1, 10],
            ['oem_variant', 'OEM Variant', null, 'string', 1, 20],
            ['csd_index', 'CSD Index Code', json_encode(['CSD Index', 'CSD Index Code']), 'string', 1, 30],
        ] as [$code, $label, $aliases, $type, $req, $ord]) {
            $rows[] = [
                'sheet_code'  => 'CSD_INDEX',
                'field_code'  => $code,
                'label'       => $label,
                'aliases'     => $aliases,
                'data_type'   => $type,
                'is_required' => $req,
                'sort_order'  => $ord,
            ];
        }

        return $rows;
    }

    private function seedSynonyms(): array
    {
        $rows = [];

        // Segment typos (RSA PERSONAL / PEROSNAL → PV)
        foreach (['PERSONAL', 'PEROSNAL', 'PERSONEL', 'P V', 'PASSENGER VEHICLE'] as $syn) {
            $rows[] = ['entity_type' => 'Segment', 'canonical' => 'PV', 'synonym' => $syn];
        }
        foreach (['COMMERCIAL', 'COMMERCIAL VEHICLE', 'C V'] as $syn) {
            $rows[] = ['entity_type' => 'Segment', 'canonical' => 'CV', 'synonym' => $syn];
        }

        // Fuel
        foreach (['DIESLE', 'DYSLE', 'D-JAL', 'DIESEL ', 'DSL'] as $syn) {
            $rows[] = ['entity_type' => 'Fuel', 'canonical' => 'DIESEL', 'synonym' => trim($syn)];
        }
        foreach (['PETROL ', 'PTRL', 'MS'] as $syn) {
            $rows[] = ['entity_type' => 'Fuel', 'canonical' => 'PETROL', 'synonym' => trim($syn)];
        }
        foreach (['ELECTRIC', 'EV ', 'BEV'] as $syn) {
            $rows[] = ['entity_type' => 'Fuel', 'canonical' => 'EV', 'synonym' => trim($syn)];
        }

        // Permit
        foreach (['PASSANGER', 'PASSENGER ', 'PAX'] as $syn) {
            $rows[] = ['entity_type' => 'Permit', 'canonical' => 'Passenger', 'synonym' => trim($syn)];
        }
        foreach (['PRIVATE ', 'PRIV'] as $syn) {
            $rows[] = ['entity_type' => 'Permit', 'canonical' => 'Private', 'synonym' => trim($syn)];
        }

        return $rows;
    }
};
