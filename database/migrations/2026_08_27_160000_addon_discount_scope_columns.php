<?php

/**
 * Path: database/migrations/2026_08_27_160000_addon_discount_scope_columns.php
 *
 * Live tables were created earlier than the importer contract.
 * Add the missing matching columns WITHOUT dropping data.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('xlr8_vehicle_pricing_addons')) {
            Schema::table('xlr8_vehicle_pricing_addons', function (Blueprint $table) {
                if (! Schema::hasColumn('xlr8_vehicle_pricing_addons', 'segment')) {
                    $table->string('segment', 20)->nullable()->after('import_session_id');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_addons', 'shield_pack')) {
                    $table->string('shield_pack', 40)->nullable()->after('scheme_name');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_addons', 'transmission')) {
                    $table->string('transmission', 30)->nullable()->after('shield_pack');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_addons', 'fuel')) {
                    $table->string('fuel', 30)->nullable()->after('transmission');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_addons', 'permit')) {
                    $table->string('permit', 30)->nullable()->after('fuel');
                }
            });
        }

        if (Schema::hasTable('xlr8_vehicle_pricing_discounts')) {
            Schema::table('xlr8_vehicle_pricing_discounts', function (Blueprint $table) {
                if (! Schema::hasColumn('xlr8_vehicle_pricing_discounts', 'scheme_name')) {
                    $table->string('scheme_name', 120)->nullable()->after('discount_type');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_discounts', 'category')) {
                    $table->string('category', 80)->nullable()->after('scheme_name');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_discounts', 'amount')) {
                    $table->decimal('amount', 14, 2)->nullable()->after('dealer_share');
                }
            });
        }

        if (Schema::hasTable('xlr8_vehicle_pricing_dealer_charges')) {
            Schema::table('xlr8_vehicle_pricing_dealer_charges', function (Blueprint $table) {
                if (! Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'charge_code')) {
                    $table->string('charge_code', 40)->nullable()->after('model_code');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'charge_name')) {
                    $table->string('charge_name', 80)->nullable()->after('charge_code');
                }
                if (! Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'amount')) {
                    $table->decimal('amount', 12, 2)->nullable()->after('charge_name');
                }
            });
        }
    }

    public function down(): void
    {
        // Keep columns — they are additive.
    }
};
