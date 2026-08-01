<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh accessory catalog tables.
 *
 * Rules (enforced in AccessoryService, not DB FKs):
 *  - No foreign keys (code-based soft refs only)
 *  - Soft deletes + 6 audit columns on both tables
 *  - Scope columns NULL = ANY (blank Excel cell → ANY on import)
 *  - permit '' or NULL = ANY (required only for GPS_VLTD / RTO_Tape rows that carry a value)
 *  - Fetch API: SEGMENT + MODEL + Variant + Permit are mandatory;
 *    either ALL are ANY, or ALL are concrete — no mixed combination
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('xlr8_vehicle_accessory_scopes');
        Schema::dropIfExists('xlr8_vehicle_accessories');

        Schema::create('xlr8_vehicle_accessories', function (Blueprint $table) {
            $table->id();

            $table->string('part_no', 25)->comment('OEM / internal part number (unique)');
            $table->string('type', 30)->default('Accessory')
                ->comment('Accessory|Ceramic|PPF|Maxicare|GPS_VLTD|RTO_Tape|Kazam');
            $table->string('display_name', 150)->nullable();
            $table->string('item', 150)->comment('Item / product name');
            $table->decimal('ndp', 12, 2)->nullable()->comment('Net dealer price');
            $table->decimal('mrp', 12, 2)->nullable()->comment('MRP rounded');
            $table->unsignedInteger('set_qty')->default(1);
            $table->decimal('discount', 8, 4)->nullable()->comment('Retail margin % (Retn %)');
            $table->string('details', 250)->nullable();
            $table->boolean('bundle')->default(false);
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');

            // 6 audit columns
            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique('part_no', 'xlr8_vehicle_accessories_part_no_unique');
            $table->index(['type', 'status'], 'idx_acc_type_status');
            $table->index(['status', 'part_no'], 'idx_acc_status_part');
            $table->index('created_by', 'idx_acc_created_by');
            $table->index('updated_by', 'idx_acc_updated_by');
            $table->index('deleted_by', 'idx_acc_deleted_by');
        });

        Schema::create('xlr8_vehicle_accessory_scopes', function (Blueprint $table) {
            $table->id();

            $table->string('part_no', 25)->comment('Soft ref → xlr8_vehicle_accessories.part_no');
            // NULL = ANY (matches any value at this level when fetching)
            $table->string('segment_code', 25)->nullable()
                ->comment('Soft ref → xlr8_vehicle_segment.code | NULL=ANY');
            $table->string('model_code', 25)->nullable()
                ->comment('Soft ref → xlr8_vehicle_model.code | NULL=ANY');
            $table->string('variant_code', 50)->nullable()
                ->comment('Soft ref → xlr8_vehicle_variant.code | NULL=ANY');
            $table->string('permit', 30)->nullable()
                ->comment('Passenger|Goods|… | NULL/empty=ANY');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');

            // 6 audit columns
            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Unique among non-deleted rows is enforced in app if needed;
            // DB unique allows multiple NULLs which is intentional for ANY scopes.
            $table->unique(
                ['part_no', 'segment_code', 'model_code', 'variant_code', 'permit'],
                'uq_acc_scope'
            );
            $table->index(['segment_code', 'model_code', 'variant_code', 'permit'], 'idx_acc_scope_match');
            $table->index(['part_no', 'status'], 'idx_acc_scope_part_status');
            $table->index('created_by', 'idx_acc_scope_created_by');
            $table->index('updated_by', 'idx_acc_scope_updated_by');
            $table->index('deleted_by', 'idx_acc_scope_deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_vehicle_accessory_scopes');
        Schema::dropIfExists('xlr8_vehicle_accessories');
    }
};
