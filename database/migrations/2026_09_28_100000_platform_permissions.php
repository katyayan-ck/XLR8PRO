<?php

use App\Models\IAM\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEC-061: processes and permissions of the platform utilities (module UTL), web guard.
 * Everyday use (own inbox, tasks, tickets, docs, approval requests) is granted to every
 * designation; administration stays with SuperAdmin until assigned on the Designation screen.
 */
return new class extends Migration
{
    /** @var array<string, string> process code => name */
    private const PROCESSES = [
        'NOTY' => 'Notifications',
        'CHAT' => 'Chat & history',
        'DOCS' => 'Documents',
        'TASK' => 'Tasks',
        'TCKT' => 'Tickets',
        'APPR' => 'Approvals',
        'TPL' => 'Message templates',
        'COMM' => 'Communication channels',
    ];

    /** @var array<string, bool> permission => granted to every designation */
    private const PERMISSIONS = [
        'UTL_NOTY_BROADCAST' => false,
        'UTL_CHAT_MODERATE' => false,
        'UTL_DOCS_VIEW' => true,
        'UTL_DOCS_UPLOAD' => true,
        'UTL_DOCS_MANAGE' => false,
        'UTL_TASK_VIEW' => true,
        'UTL_TASK_CREATE' => true,
        'UTL_TASK_ADMIN' => false,
        'UTL_TCKT_VIEW' => true,
        'UTL_TCKT_CREATE' => true,
        'UTL_TCKT_DESK' => false,
        'UTL_TCKT_REPORT' => false,
        'UTL_APPR_VIEW' => true,
        'UTL_APPR_REQUEST' => true,
        'UTL_APPR_ADMIN' => false,
        'UTL_APPR_REPORT' => false,
        'UTL_TPL_VIEW' => false,
        'UTL_TPL_EDIT' => false,
        'UTL_TPL_ACTIVATE' => false,
        'UTL_COMM_VIEW' => false,
        'UTL_COMM_SEND' => false,
        'UTL_COMM_SMS_RAW' => false,
        'UTL_COMM_WA_INBOX' => false,
        'UTL_COMM_CALL' => false,
        'UTL_COMM_RECORDING_DOWNLOAD' => false,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_iam_process') || ! Schema::hasTable('xlr8_iam_permissions')) {
            return;
        }

        foreach (self::PROCESSES as $code => $name) {
            if (! DB::table('xlr8_iam_process')->where('module_code', 'UTL')->where('code', $code)->exists()) {
                DB::table('xlr8_iam_process')->insert([
                    'module_code' => 'UTL', 'code' => $code, 'name' => $name, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach (self::PERMISSIONS as $name => $everyone) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            [, $process] = explode('_', $name, 3);
            DB::table('xlr8_iam_permissions')->where('id', $permission->id)->update(['module_code' => 'UTL', 'process_code' => $process]);

            if ($everyone) {
                // One bulk insert per permission: givePermissionTo() per role flushes the permission cache each time.
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore(
                    Role::query()->where('guard_name', 'web')->pluck('id')
                        ->map(fn ($roleId) => ['permission_id' => $permission->id, 'role_id' => $roleId])->all()
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_iam_permissions')) {
            return;
        }

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::where('name', $name)->where('guard_name', 'web')->first()?->delete();
        }
        DB::table('xlr8_iam_process')->where('module_code', 'UTL')->whereIn('code', array_keys(self::PROCESSES))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
