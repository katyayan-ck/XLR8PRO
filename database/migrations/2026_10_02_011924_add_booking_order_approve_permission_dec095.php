<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEC-095 #9 / D16 (BT-014, BUG-095): `SLS_BKNG_ORDER_APPROVE` replaces the hard-coded user-id list that decided who may
 * Accept / Reject on Order Verification. Granted to no designation here (SuperAdmin bypasses) — grant it from
 * IAM → Designations. Idempotent; down() removes only this permission and its grants.
 */
return new class extends Migration
{
    private const PERMISSION = 'SLS_BKNG_ORDER_APPROVE';

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_iam_permissions')) {
            return;
        }
        if (! DB::table('xlr8_iam_permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->exists()) {
            DB::table('xlr8_iam_permissions')->insert(['name' => self::PERMISSION, 'guard_name' => 'web', 'module_code' => 'SLS',
                'process_code' => 'BKNG', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_iam_permissions')) {
            return;
        }
        $id = DB::table('xlr8_iam_permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');
        if ($id) {
            DB::table('xlr8_iam_role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('xlr8_iam_model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('xlr8_iam_permissions')->where('id', $id)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
