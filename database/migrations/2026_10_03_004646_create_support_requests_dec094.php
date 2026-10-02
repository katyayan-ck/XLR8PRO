<?php

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * DEC-094 (W16e) support requests: permissions `UTL_SUPP_ADMIN` (receives new support tickets) and `UTL_SUPP_EXEC`
 * (assignable) under process UTL / SUPP, granted to superadmin only (the owner attaches them to designations); the
 * support request table (one row per request, the diagnostic zip kept in private storage); ticket categories for the
 * "What do you need?" choices. Idempotent; down() removes exactly what up() added.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['UTL_SUPP_ADMIN', 'UTL_SUPP_EXEC'];

    /** TICKET_CATEGORY value code => label */
    private const CATEGORIES = [
        'SUP_HOWTO' => 'Support — How do I…?',
        'SUP_NOT_WORKING' => 'Support — Something is not working',
        'SUP_WRONG_DATA' => 'Support — Wrong data',
        'SUP_ACCESS' => 'Support — Access or permission',
        'SUP_SUGGESTION' => 'Support — Suggestion',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_support_request')) {
            Schema::create('xlr8_utils_support_request', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->unsignedBigInteger('requester_id')->index();
                $table->string('category', 40);
                $table->string('route', 190)->nullable();
                $table->string('bundle_path')->nullable();
                $table->unsignedInteger('bundle_bytes')->nullable();
                $table->timestamp('purged_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('xlr8_iam_permissions') && Schema::hasTable('xlr8_iam_process')) {
            $now = now();
            if (! DB::table('xlr8_iam_process')->where('module_code', 'UTL')->where('code', 'SUPP')->exists()) {
                DB::table('xlr8_iam_process')->insert(['module_code' => 'UTL', 'code' => 'SUPP', 'name' => 'Support Requests',
                    'description' => 'Support requests (help & support, DEC-094)', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            $roleId = DB::table('xlr8_admin_designation')->where('name', 'superadmin')->where('guard_name', 'web')->value('id');
            foreach (self::PERMISSIONS as $name) {
                $id = DB::table('xlr8_iam_permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                    ?? DB::table('xlr8_iam_permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'module_code' => 'UTL',
                        'process_code' => 'SUPP', 'created_at' => $now, 'updated_at' => $now]);
                if ($roleId && ! DB::table('xlr8_iam_role_has_permissions')->where('permission_id', $id)->where('role_id', $roleId)->exists()) {
                    DB::table('xlr8_iam_role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            foreach (self::CATEGORIES as $code => $label) {
                if (! Keyvalue::withTrashed()->where('keyword_code', 'TICKET_CATEGORY')->where('code', $code)->exists()) {
                    app(KeyvalueService::class)->create(['keyword_code' => 'TICKET_CATEGORY', 'code' => $code, 'value' => $label]);
                }
            }
            KeywordValueService::clearCache('TICKET_CATEGORY');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            Keyvalue::withTrashed()->where('keyword_code', 'TICKET_CATEGORY')->whereIn('code', array_keys(self::CATEGORIES))->forceDelete();
            KeywordValueService::clearCache('TICKET_CATEGORY');
        }
        if (Schema::hasTable('xlr8_iam_permissions')) {
            foreach (self::PERMISSIONS as $name) {
                $id = DB::table('xlr8_iam_permissions')->where('name', $name)->value('id');
                if ($id) {
                    DB::table('xlr8_iam_role_has_permissions')->where('permission_id', $id)->delete();
                    DB::table('xlr8_iam_model_has_permissions')->where('permission_id', $id)->delete();
                    DB::table('xlr8_iam_permissions')->where('id', $id)->delete();
                }
            }
            DB::table('xlr8_iam_process')->where('module_code', 'UTL')->where('code', 'SUPP')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        // only an empty table is dropped: rows point at bundles still kept for the retention period
        if (Schema::hasTable('xlr8_utils_support_request') && ! DB::table('xlr8_utils_support_request')->exists()) {
            Schema::drop('xlr8_utils_support_request');
        }
    }
};
