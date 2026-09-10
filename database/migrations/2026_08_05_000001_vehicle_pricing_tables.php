<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. Import Sessions
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_import_sessions');
        Schema::create('xlr8_vehicle_pricing_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('wef_date')->nullable();
            $table->string('status', 40)->default('pending'); // pending|in_progress|completed|calculating|published|completed_with_errors|failed
            $table->json('selected_segments')->nullable();
            $table->json('stats')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('status', 'idx_impsess_status');
            $table->index('wef_date', 'idx_impsess_wef');
        });

        // =====================================================================
        // 2. Change Flags
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_change_flags');
        Schema::create('xlr8_vehicle_pricing_change_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->string('segment', 20)->nullable();
            $table->string('change_type', 40); // oem_price|rsa|shield|discount|dealer_charge|vehicle_master|ins_rule|rto_rule|...
            $table->string('model_code', 40)->nullable();
            $table->string('variant_code', 40)->nullable();
            $table->string('field_name', 60)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('is_processed')->default(false);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['import_session_id', 'is_processed'], 'idx_cf_session_processed');
            $table->index('change_type', 'idx_cf_type');
            $table->index('model_code', 'idx_cf_model');
        });

        // =====================================================================
        // 3. Affected Vehicles
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_affected');
        Schema::create('xlr8_vehicle_pricing_affected', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->string('model_code', 40);
            $table->string('variant_code', 40)->nullable();
            $table->string('status', 20)->default('pending'); // pending|processing|done|error
            $table->string('error_message', 500)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['import_session_id', 'model_code', 'variant_code'], 'uk_aff_session_model');
            $table->index('status', 'idx_aff_status');
        });

        // =====================================================================
        // 4. Pricing Profile (flattened vehicle identity for pricing)
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_profile');
        Schema::create('xlr8_vehicle_pricing_profile', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);          // full OEM code (variant+color)
            $table->string('variant_code', 40)->nullable();
            $table->string('segment', 20)->nullable();
            $table->string('permit', 30)->nullable();
            $table->boolean('taxi_price')->default(false);
            $table->string('fuel_type', 30)->nullable();
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->unsignedSmallInteger('seating')->nullable();
            $table->string('cc_or_power', 30)->nullable();
            $table->string('gvw', 30)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('transmission', 30)->nullable();

            $table->boolean('is_vehicle_master_complete')->default(false);
            $table->boolean('is_pricing_template_complete')->default(false);
            $table->boolean('is_rule_profile_complete')->default(false);
            $table->boolean('is_publishable')->default(false);
            $table->boolean('is_disabled')->default(false);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique('model_code', 'uk_profile_model');
            $table->index('segment', 'idx_profile_segment');
            $table->index('is_publishable', 'idx_profile_publishable');
        });

        // =====================================================================
        // 5. Main Pricing (OEM + schemes)
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing');
        Schema::create('xlr8_vehicle_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('channel', 20)->default('normal'); // normal|csd
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->decimal('ex_showroom_price', 14, 2)->default(0);
            $table->decimal('assessable_value_with_freight', 14, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('mm_invoice_amount', 14, 2)->default(0);
            $table->decimal('dealer_margin', 14, 2)->default(0);

            // Current VIN
            $table->decimal('curr_oem_scheme', 14, 2)->default(0);
            $table->decimal('curr_dealer_cont', 14, 2)->default(0);
            $table->decimal('curr_cash_discount', 14, 2)->default(0);
            $table->decimal('curr_acc_discount', 14, 2)->default(0);
            $table->decimal('curr_shield_discount', 14, 2)->default(0);

            // Old VIN
            $table->decimal('old_oem_scheme', 14, 2)->default(0);
            $table->decimal('old_dealer_cont', 14, 2)->default(0);
            $table->decimal('old_cash_discount', 14, 2)->default(0);
            $table->decimal('old_acc_discount', 14, 2)->default(0);
            $table->decimal('old_shield_discount', 14, 2)->default(0);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'channel', 'wef_date'], 'uk_pricing_model_ch_wef');
            $table->index(['model_code', 'is_active'], 'idx_pricing_model_active');
        });

        // =====================================================================
        // 6. Pricing History
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_history');
        Schema::create('xlr8_vehicle_pricing_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pricing_id')->nullable();
            $table->string('model_code', 40);
            $table->string('channel', 20)->default('normal');
            $table->date('wef_date')->nullable();
            $table->json('payload')->nullable();
            $table->string('action', 30)->nullable(); // created|updated|expired

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('model_code', 'idx_phist_model');
        });

        // =====================================================================
        // 7. Draft
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_draft');
        Schema::create('xlr8_vehicle_pricing_draft', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('permit', 30)->default('Private');
            $table->string('vin_type', 20)->default('current');
            $table->string('channel', 20)->default('normal');
            $table->json('payload');
            $table->decimal('ex_showroom', 14, 2)->nullable();
            $table->decimal('on_road_price', 14, 2)->nullable();
            $table->unsignedBigInteger('import_session_id')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'permit', 'vin_type', 'channel'], 'uk_draft_key');
        });

        // =====================================================================
        // 8. Snapshot (published)
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_snapshot');
        Schema::create('xlr8_vehicle_pricing_snapshot', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('permit', 30)->default('Private');
            $table->string('vin_type', 20)->default('current');
            $table->string('channel', 20)->default('normal');
            $table->json('full_pricing_data');
            $table->date('effective_date')->nullable();
            $table->decimal('ex_showroom', 14, 2)->nullable();
            $table->decimal('on_road_price', 14, 2)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'permit', 'vin_type', 'channel'], 'uk_snap_key');
            $table->index('effective_date', 'idx_snap_eff');
        });

        // =====================================================================
        // 9. Addons (RSA / Shield)
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_addons');
        Schema::create('xlr8_vehicle_pricing_addons', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('variant_code', 40)->nullable();
            $table->string('addon_type', 20); // rsa|shield
            $table->string('scheme_name', 100)->nullable();
            $table->string('name', 100)->nullable();
            $table->unsignedTinyInteger('tenure_years')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('oem_share', 14, 2)->default(0);
            $table->decimal('dealer_share', 14, 2)->default(0);
            $table->string('default_allocation', 5)->default('B'); // I|C|C1|C2|B
            $table->boolean('is_default')->default(false);
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'addon_type', 'is_active'], 'idx_addon_model_type');
        });

        // =====================================================================
        // 10. Addon History
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_addon_history');
        Schema::create('xlr8_vehicle_pricing_addon_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addon_id')->nullable();
            $table->string('model_code', 40)->nullable();
            $table->string('addon_type', 20)->nullable();
            $table->json('payload')->nullable();
            $table->string('action', 30)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // =====================================================================
        // 11. Discounts
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_discounts');
        Schema::create('xlr8_vehicle_pricing_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('variant_code', 40)->nullable();
            $table->string('discount_type', 40); // corporate|exchange|welcome|scrap|fame|spl_cash|charger_swapping|other
            $table->string('discount_category', 40)->nullable();
            $table->string('name', 120);
            $table->decimal('oem_share', 14, 2)->default(0);
            $table->decimal('dealer_share', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            $table->string('allocation_type', 5)->default('B'); // I|C|C1|C2|B
            $table->boolean('is_conditional')->default(false);
            $table->string('linked_to', 60)->nullable();
            $table->json('extra_json')->nullable(); // lowerbound, upperbound, default etc.
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'discount_type', 'is_active'], 'idx_disc_model_type');
            $table->index('allocation_type', 'idx_disc_alloc');
        });

        // =====================================================================
        // 12. Discount History
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_discount_history');
        Schema::create('xlr8_vehicle_pricing_discount_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->string('model_code', 40)->nullable();
            $table->json('payload')->nullable();
            $table->string('action', 30)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // =====================================================================
        // 13. Dealer Charges
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_dealer_charges');
        Schema::create('xlr8_vehicle_pricing_dealer_charges', function (Blueprint $table) {
            $table->id();
            $table->string('segment', 20);
            $table->string('permit', 30)->nullable();
            $table->string('model_code', 40)->nullable(); // null = segment default
            $table->decimal('incidental', 12, 2)->default(0);
            $table->decimal('fastag', 12, 2)->default(0);
            $table->decimal('trc', 12, 2)->default(0);
            $table->decimal('rto_tape', 12, 2)->default(0);
            $table->decimal('cod', 12, 2)->default(0);
            $table->decimal('kazam', 12, 2)->default(0);
            $table->json('extra_json')->nullable();
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['segment', 'is_active'], 'idx_dc_seg_active');
        });

        // =====================================================================
        // 14. Insurance Defaults
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_defaults');
        Schema::create('xlr8_vehicle_pricing_ins_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->string('permit', 30)->default('Private');
            $table->string('insurance_company', 40);
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'permit'], 'idx_insdef_model_permit');
        });

        // =====================================================================
        // 15. Insurance Base Rules
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_base_rules');
        Schema::create('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            $table->id();
            $table->string('permit', 30);
            $table->string('fuel_type', 30)->nullable();
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->unsignedSmallInteger('seating')->nullable();
            $table->string('cc_range', 30)->nullable();
            $table->string('gvw_range', 30)->nullable();
            $table->decimal('od_factor', 10, 6)->default(0);
            $table->decimal('od_surcharge', 8, 4)->default(0);
            $table->decimal('od_discount_rate', 8, 2)->default(0);
            $table->decimal('tp_basic', 12, 2)->default(0);
            $table->decimal('tp_per_passenger', 12, 2)->default(0);
            $table->decimal('tp_legal_driver', 12, 2)->default(0);
            $table->decimal('tp_non_fare_passenger', 12, 2)->default(0);
            $table->decimal('tp_bi_fuel_kit', 12, 2)->default(0);
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['permit', 'is_active'], 'idx_insbase_permit');
        });

        // =====================================================================
        // 16. Insurance Addon Rates
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_ins_addon_rates');
        Schema::create('xlr8_vehicle_pricing_ins_addon_rates', function (Blueprint $table) {
            $table->id();
            $table->string('insurance_company', 40);
            $table->string('permit', 30)->default('Private');
            $table->string('addon_slug', 40); // nil_dep|consumables|engine_protect|rti|...
            $table->string('addon_name', 80)->nullable();
            $table->string('rate_type', 20)->default('fixed'); // fixed|percentage
            $table->decimal('rate_value', 12, 4)->default(0);
            $table->string('applies_on', 20)->default('od'); // od|idv
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['insurance_company', 'permit', 'is_active'], 'idx_insaddon_co_permit');
        });

        // =====================================================================
        // 17. RTO Rules
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_rto_rules');
        Schema::create('xlr8_vehicle_pricing_rto_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable();
            $table->string('permit', 30);
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->string('reg_type', 20)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('gvw_range', 30)->nullable();
            $table->string('seater', 20)->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('cc_range', 30)->nullable();
            $table->decimal('tax_factor', 10, 6)->default(0);
            $table->string('tax_slab', 50)->nullable();
            $table->decimal('surcharge', 12, 2)->default(0);
            $table->decimal('hypothecation', 12, 2)->default(0);
            $table->decimal('green_tax', 12, 2)->default(0);
            $table->decimal('registration_fee', 12, 2)->default(0);
            $table->decimal('duplicate_tax_card', 12, 2)->default(0);
            $table->decimal('fitness', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('rto_tape', 12, 2)->default(0);
            $table->date('wef_date')->nullable();
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['permit', 'is_active'], 'idx_rto_permit');
        });

        // =====================================================================
        // 18. CSD Pricing (separate channel)
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_csd');
        Schema::create('xlr8_vehicle_pricing_csd', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 40);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('ex_showroom_price', 14, 2)->default(0);
            $table->json('extra_json')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'wef_date'], 'uk_csd_model_wef');
        });

        // =====================================================================
        // 19. TCS Config
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_tcs_config');
        Schema::create('xlr8_vehicle_pricing_tcs_config', function (Blueprint $table) {
            $table->id();
            $table->decimal('limit_amount', 14, 2)->default(1000000);
            $table->decimal('rate_pct', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // =====================================================================
        // 20. Price Holds
        // =====================================================================
        Schema::dropIfExists('xlr8_vehicle_pricing_holds');
        Schema::create('xlr8_vehicle_pricing_holds', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 30); // ALL|PV|CV|LMM|BEV|CSD|TAXI
            $table->boolean('is_held')->default(false);
            $table->timestamp('held_at')->nullable();
            $table->unsignedBigInteger('held_by')->nullable();
            $table->string('hold_reason', 255)->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->string('reopen_reason', 255)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique('scope', 'uk_hold_scope');
        });
    }

    public function down(): void
    {
        $tables = [
            'xlr8_vehicle_pricing_holds',
            'xlr8_vehicle_pricing_tcs_config',
            'xlr8_vehicle_pricing_csd',
            'xlr8_vehicle_pricing_rto_rules',
            'xlr8_vehicle_pricing_ins_addon_rates',
            'xlr8_vehicle_pricing_ins_base_rules',
            'xlr8_vehicle_pricing_ins_defaults',
            'xlr8_vehicle_pricing_dealer_charges',
            'xlr8_vehicle_pricing_discount_history',
            'xlr8_vehicle_pricing_discounts',
            'xlr8_vehicle_pricing_addon_history',
            'xlr8_vehicle_pricing_addons',
            'xlr8_vehicle_pricing_snapshot',
            'xlr8_vehicle_pricing_draft',
            'xlr8_vehicle_pricing_history',
            'xlr8_vehicle_pricing',
            'xlr8_vehicle_pricing_profile',
            'xlr8_vehicle_pricing_affected',
            'xlr8_vehicle_pricing_change_flags',
            'xlr8_vehicle_pricing_import_sessions',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};