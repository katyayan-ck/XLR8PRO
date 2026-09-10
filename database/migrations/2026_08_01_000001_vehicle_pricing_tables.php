<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
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

        // --------------------------------------------------
        // 1. Import Sessions
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('wef_date');
            $table->string('status', 30)->default('in_progress');
            $table->json('selected_segments')->nullable();
            $table->text('remarks')->nullable();
            $table->json('stats')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // --------------------------------------------------
        // 2. Change Flags
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_change_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->string('segment', 20)->nullable();
            $table->string('change_type', 50);
            $table->string('model_code', 50)->nullable();
            $table->string('variant_code', 50)->nullable();
            $table->string('field_name', 100)->nullable();
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
            $table->index(['change_type', 'model_code'], 'idx_cf_type_model');
        });

        // --------------------------------------------------
        // 3. Affected
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_affected', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['import_session_id', 'model_code', 'variant_code'], 'uk_aff_session_model');
        });

        // --------------------------------------------------
        // 4. Profile
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_profile', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('segment', 30)->nullable();
            $table->string('permit', 30)->nullable();
            $table->boolean('taxi_price')->default(false);
            $table->string('fuel_type', 20)->nullable();
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->unsignedTinyInteger('seating')->nullable();
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
            $table->index(['permit', 'fuel_type', 'wheels', 'is_publishable'], 'idx_profile_attrs');
        });

        // --------------------------------------------------
        // 5. Main Pricing
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->enum('channel', ['normal', 'csd'])->default('normal');
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(false);

            $table->decimal('assessable_value_with_freight', 15, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('mm_invoice_amount', 15, 2)->default(0);
            $table->decimal('dealer_margin', 15, 2)->default(0);
            $table->decimal('ex_showroom_price', 15, 2)->default(0);

            // Current VIN
            $table->decimal('curr_oem_scheme', 15, 2)->default(0);
            $table->decimal('curr_dealer_cont', 15, 2)->default(0);
            $table->decimal('curr_cash_discount', 15, 2)->default(0);
            $table->decimal('curr_acc_discount', 15, 2)->default(0);
            $table->decimal('curr_shield_discount', 15, 2)->default(0);
            $table->decimal('curr_acc_elg', 5, 4)->nullable();
            $table->decimal('curr_shield_elg', 5, 4)->nullable();

            // Old VIN
            $table->decimal('old_oem_scheme', 15, 2)->default(0);
            $table->decimal('old_dealer_cont', 15, 2)->default(0);
            $table->decimal('old_cash_discount', 15, 2)->default(0);
            $table->decimal('old_acc_discount', 15, 2)->default(0);
            $table->decimal('old_shield_discount', 15, 2)->default(0);
            $table->decimal('old_acc_elg', 5, 4)->nullable();
            $table->decimal('old_shield_elg', 5, 4)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'channel', 'is_active', 'wef_date'], 'idx_pricing_active');
        });

        // --------------------------------------------------
        // 6. Pricing History
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pricing_id');
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // --------------------------------------------------
        // 7. Draft
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_draft', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('permit', 30);
            $table->enum('vin_type', ['current', 'old'])->default('current');
            $table->enum('channel', ['normal', 'csd'])->default('normal');
            $table->json('payload');
            $table->decimal('ex_showroom', 15, 2)->nullable();
            $table->decimal('on_road_price', 15, 2)->nullable();
            $table->unsignedBigInteger('import_session_id')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'permit', 'vin_type', 'channel'], 'uk_draft');
        });

        // --------------------------------------------------
        // 8. Snapshot
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_snapshot', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('permit', 30)->nullable();
            $table->enum('vin_type', ['current', 'old'])->default('current');
            $table->enum('channel', ['normal', 'csd'])->default('normal');
            $table->json('full_pricing_data');
            $table->date('effective_date');
            $table->decimal('ex_showroom', 15, 2)->nullable();
            $table->decimal('on_road_price', 15, 2)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'permit', 'vin_type', 'channel'], 'uk_snapshot');
        });

        // --------------------------------------------------
        // 9. Addons
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_addons', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('addon_type', 30);
            $table->string('scheme_name', 150)->nullable();
            $table->unsignedTinyInteger('tenure_years')->nullable();
            $table->string('name', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('amount_type', 20)->default('fixed');
            $table->decimal('oem_share', 12, 2)->default(0);
            $table->decimal('dealer_share', 12, 2)->default(0);
            $table->enum('default_allocation', ['I', 'C', 'C1', 'C2', 'B'])->default('B');
            $table->json('conditions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'addon_type', 'is_active'], 'idx_addon_model_type');
            $table->index(['addon_type', 'is_default'], 'idx_addon_type_default');
        });

        Schema::create('xlr8_vehicle_pricing_addon_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addon_id');
            $table->string('model_code', 50)->nullable();
            $table->string('variant_code', 50)->nullable();
            $table->string('addon_type', 30)->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // --------------------------------------------------
        // 10. Discounts
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('variant_code', 50)->nullable();
            $table->string('discount_type', 40);
            $table->string('discount_category', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('oem_share', 12, 2)->default(0);
            $table->decimal('dealer_share', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->enum('allocation_type', ['I', 'C', 'C1', 'C2', 'B'])->default('B');
            $table->json('conditions')->nullable();
            $table->boolean('is_conditional')->default(false);
            $table->string('linked_to', 30)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['model_code', 'discount_type', 'is_active'], 'idx_disc_model_type');
            $table->index(['allocation_type', 'is_active'], 'idx_disc_alloc');
        });

        Schema::create('xlr8_vehicle_pricing_discount_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discount_id');
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('change_type', 30);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // --------------------------------------------------
        // 11. Dealer Charges
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_dealer_charges', function (Blueprint $table) {
            $table->id();
            $table->string('segment', 30);
            $table->string('permit', 30)->nullable();
            $table->string('model_code', 50)->nullable();
            $table->decimal('incidental', 12, 2)->default(0);
            $table->decimal('fastag', 12, 2)->default(0);
            $table->decimal('trc', 12, 2)->default(0);
            $table->decimal('rto_tape', 12, 2)->default(0);
            $table->decimal('cod', 12, 2)->default(0);
            $table->json('extra_json')->nullable();
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['segment', 'model_code', 'is_active'], 'idx_dlr_seg_model');
        });

        // --------------------------------------------------
        // 12. Insurance Defaults
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_ins_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('permit', 30);
            $table->string('default_company', 50);
            $table->string('company_priority_2', 50)->nullable();
            $table->string('company_priority_3', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('wef_date')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'permit'], 'uk_ins_def');
        });

        // --------------------------------------------------
        // 13. Insurance Base Rules
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_ins_base_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique('uk_ins_base_code');
            $table->string('model_code', 50)->nullable();
            $table->string('variant_code', 50)->nullable();
            $table->string('permit', 30);
            $table->string('fuel_type', 20);
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->unsignedTinyInteger('seating')->nullable();
            $table->string('cc_range', 30)->nullable();
            $table->string('gvw_range', 30)->nullable();
            $table->string('plan', 20)->nullable();

            $table->decimal('od_factor', 10, 6)->default(0);
            $table->decimal('od_surcharge', 10, 6)->default(0);
            $table->decimal('od_discount_rate', 10, 6)->default(0);
            $table->decimal('imt_23_rate', 10, 6)->default(0);
            $table->decimal('tp_basic', 12, 2)->default(0);
            $table->decimal('tp_per_passenger', 12, 2)->default(0);
            $table->decimal('tp_legal_driver', 12, 2)->default(0);
            $table->decimal('tp_non_fare_passenger', 12, 2)->default(0);
            $table->decimal('tp_bi_fuel_kit', 12, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['permit', 'fuel_type', 'is_active'], 'idx_ins_base_match');
        });

        // --------------------------------------------------
        // 14. Insurance Addon Rates
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_ins_addon_rates', function (Blueprint $table) {
            $table->id();
            $table->string('insurance_company', 50);
            $table->string('permit', 30);
            $table->string('addon_slug', 60);
            $table->string('addon_name', 100);
            $table->enum('rate_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('rate_value', 10, 4)->default(0);
            $table->string('applies_on', 50)->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['insurance_company', 'permit', 'is_active'], 'idx_ins_addon_comp');
            $table->unique(['insurance_company', 'permit', 'addon_slug', 'wef_date'], 'uk_ins_addon');
        });

        // --------------------------------------------------
        // 15. RTO Rules
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_rto_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique('uk_rto_code');
            $table->string('permit', 30);
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->string('reg_type', 20)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('gvw_range', 30)->nullable();
            $table->string('seater', 20)->nullable();
            $table->string('fuel_type', 20)->nullable();
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

            $table->boolean('is_active')->default(true);
            $table->date('wef_date');
            $table->date('expired_on')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['permit', 'fuel_type', 'wheels', 'is_active'], 'idx_rto_match');
        });

        // --------------------------------------------------
        // 16. CSD
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_csd', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 50);
            $table->string('oem_model', 100)->nullable();
            $table->string('oem_variant', 150)->nullable();
            $table->string('display_name', 200)->nullable();
            $table->decimal('assessable_value', 15, 2)->nullable();
            $table->decimal('retail_dealer_margin', 15, 2)->nullable();
            $table->decimal('basic_price_incl_csd', 15, 2)->nullable();
            $table->decimal('csd_discount', 15, 2)->nullable();
            $table->decimal('csd_final_price', 15, 2)->nullable();
            $table->decimal('gst_rate', 5, 2)->nullable();
            $table->decimal('gst_amount', 15, 2)->nullable();
            $table->decimal('csd_profit_050', 15, 2)->nullable();
            $table->date('wef_date');
            $table->date('expired_on')->nullable();
            $table->boolean('is_active')->default(false);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['model_code', 'wef_date'], 'uk_csd_model_wef');
        });

        // --------------------------------------------------
        // 17. TCS Config
        // --------------------------------------------------
        Schema::create('xlr8_vehicle_pricing_tcs_config', function (Blueprint $table) {
            $table->id();
            $table->decimal('limit_amount', 15, 2)->default(1000000.00);
            $table->decimal('rate_pct', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        $tables = [
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