<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEC-083: IAM processes + permissions for the pricing masters (module PRC). `{code}_VIEW` lists / exports,
 * `{code}_MANAGE` writes / imports; the recalculation log is view-only. Idempotent; down() removes only these rows.
 * Grant them to designations from IAM → Designations (SuperAdmin bypasses).
 */
return new class extends Migration
{
    /** process code => [name, activities] */
    private const PROCESSES = [
        'DLRC' => ['Dealer Charges', ['VIEW', 'MANAGE']],
        'DBRK' => ['Discounting Breakup', ['VIEW', 'MANAGE']],
        'RSA' => ['RSA', ['VIEW', 'MANAGE']],
        'SHLD' => ['Shield', ['VIEW', 'MANAGE']],
        'CORP' => ['Corporate Discounts', ['VIEW', 'MANAGE']],
        'EXCH' => ['Exchange Discounts', ['VIEW', 'MANAGE']],
        'LYLT' => ['Loyalty Discounts', ['VIEW', 'MANAGE']],
        'ACCS' => ['Accessories', ['VIEW', 'MANAGE']],
        'INCO' => ['Insurance Companies', ['VIEW', 'MANAGE']],
        'INPF' => ['Insurance Preferences', ['VIEW', 'MANAGE']],
        'INAD' => ['Insurance Add-ons', ['VIEW', 'MANAGE']],
        'RCLC' => ['Pricing Recalculation Log', ['VIEW']],
        'INSR' => ['Insurance Rules', ['MANAGE']],   // process exists; VIEW exists
    ];

    private const EXISTING_PROCESSES = ['INSR'];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_iam_permissions') || ! Schema::hasTable('xlr8_iam_process')) {
            return;
        }
        $now = now();
        foreach (self::PROCESSES as $code => [$name, $activities]) {
            if (! DB::table('xlr8_iam_process')->where('module_code', 'PRC')->where('code', $code)->exists()) {
                DB::table('xlr8_iam_process')->insert(['module_code' => 'PRC', 'code' => $code, 'name' => $name,
                    'description' => $name.' process (PRC)', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($activities as $activity) {
                $permission = "PRC_{$code}_{$activity}";
                if (! DB::table('xlr8_iam_permissions')->where('name', $permission)->where('guard_name', 'web')->exists()) {
                    DB::table('xlr8_iam_permissions')->insert(['name' => $permission, 'guard_name' => 'web', 'module_code' => 'PRC',
                        'process_code' => $code, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_iam_permissions')) {
            return;
        }
        foreach (self::PROCESSES as $code => [, $activities]) {
            foreach ($activities as $activity) {
                $id = DB::table('xlr8_iam_permissions')->where('name', "PRC_{$code}_{$activity}")->value('id');
                if ($id) {
                    DB::table('xlr8_iam_role_has_permissions')->where('permission_id', $id)->delete();
                    DB::table('xlr8_iam_model_has_permissions')->where('permission_id', $id)->delete();
                    DB::table('xlr8_iam_permissions')->where('id', $id)->delete();
                }
            }
            if (! in_array($code, self::EXISTING_PROCESSES, true)) {
                DB::table('xlr8_iam_process')->where('module_code', 'PRC')->where('code', $code)->delete();
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
