<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEC-092 (to-do W14): vehicle content — specification items (master) and each model's values, feature items (master)
 * and each trim's (variant code's) values, and a trim record that owns the trim-level gallery. Code-based relations,
 * six audit columns, soft deletes. Also the VEH processes / permissions: VEH_CONT_VIEW / VEH_CONT_EDIT (content),
 * VEH_CMPR_VIEW (compare). Guarded and idempotent; down() drops only these tables and rows.
 */
return new class extends Migration
{
    /** process code => [name, activities] */
    private const PROCESSES = [
        'CONT' => ['Vehicle Content (specifications, features, galleries)', ['VIEW', 'EDIT']],
        'CMPR' => ['Vehicle Compare', ['VIEW']],
    ];

    public function up(): void
    {
        $audit = function (Blueprint $t): void {
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->unsignedBigInteger('deleted_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        };

        if (! Schema::hasTable('xlr8_vehicle_spec_item')) {
            Schema::create('xlr8_vehicle_spec_item', function (Blueprint $t) use ($audit) {
                $t->id();
                $t->string('code', 60)->unique();
                $t->string('category', 100)->index();
                $t->string('name', 150);
                $t->string('unit', 30)->nullable();
                $t->unsignedInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $audit($t);
            });
        }
        if (! Schema::hasTable('xlr8_vehicle_model_spec')) {
            Schema::create('xlr8_vehicle_model_spec', function (Blueprint $t) use ($audit) {
                $t->id();
                $t->string('model_code', 50)->index();
                $t->string('spec_item_code', 60)->index();
                $t->string('value', 500)->nullable();
                $audit($t);
                $t->unique(['model_code', 'spec_item_code'], 'veh_model_spec_model_item_unique');
            });
        }
        if (! Schema::hasTable('xlr8_vehicle_feature_item')) {
            Schema::create('xlr8_vehicle_feature_item', function (Blueprint $t) use ($audit) {
                $t->id();
                $t->string('code', 60)->unique();
                $t->string('feature_group', 100)->index();
                $t->string('name', 255);
                $t->unsignedInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $audit($t);
            });
        }
        if (! Schema::hasTable('xlr8_vehicle_trim')) {
            Schema::create('xlr8_vehicle_trim', function (Blueprint $t) use ($audit) {
                $t->id();
                $t->string('variant_code', 50)->unique();
                $t->string('model_code', 50)->index();
                $t->text('notes')->nullable();
                $audit($t);
            });
        }
        if (! Schema::hasTable('xlr8_vehicle_trim_feature')) {
            Schema::create('xlr8_vehicle_trim_feature', function (Blueprint $t) use ($audit) {
                $t->id();
                $t->string('variant_code', 50)->index();
                $t->string('feature_item_code', 60)->index();
                $t->string('value', 255)->nullable();
                $audit($t);
                $t->unique(['variant_code', 'feature_item_code'], 'veh_trim_feature_variant_item_unique');
            });
        }

        if (Schema::hasTable('xlr8_iam_permissions') && Schema::hasTable('xlr8_iam_process')) {
            $now = now();
            foreach (self::PROCESSES as $code => [$name, $activities]) {
                if (! DB::table('xlr8_iam_process')->where('module_code', 'VEH')->where('code', $code)->exists()) {
                    DB::table('xlr8_iam_process')->insert(['module_code' => 'VEH', 'code' => $code, 'name' => $name,
                        'description' => $name.' (VEH, DEC-092)', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                }
                foreach ($activities as $activity) {
                    $permission = "VEH_{$code}_{$activity}";
                    if (! DB::table('xlr8_iam_permissions')->where('name', $permission)->where('guard_name', 'web')->exists()) {
                        DB::table('xlr8_iam_permissions')->insert(['name' => $permission, 'guard_name' => 'web', 'module_code' => 'VEH',
                            'process_code' => $code, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_iam_permissions')) {
            foreach (self::PROCESSES as $code => [, $activities]) {
                foreach ($activities as $activity) {
                    $id = DB::table('xlr8_iam_permissions')->where('name', "VEH_{$code}_{$activity}")->value('id');
                    if ($id) {
                        DB::table('xlr8_iam_role_has_permissions')->where('permission_id', $id)->delete();
                        DB::table('xlr8_iam_model_has_permissions')->where('permission_id', $id)->delete();
                        DB::table('xlr8_iam_permissions')->where('id', $id)->delete();
                    }
                }
                DB::table('xlr8_iam_process')->where('module_code', 'VEH')->where('code', $code)->delete();
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        // New tables of this feature only (never live data tables of other features).
        Schema::dropIfExists('xlr8_vehicle_trim_feature');
        Schema::dropIfExists('xlr8_vehicle_trim');
        Schema::dropIfExists('xlr8_vehicle_feature_item');
        Schema::dropIfExists('xlr8_vehicle_model_spec');
        Schema::dropIfExists('xlr8_vehicle_spec_item');
    }
};
