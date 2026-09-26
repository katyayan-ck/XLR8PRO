<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IAM\Permission;
use App\Models\IAM\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grants `admin.dashboard` to every designation-backed role (BUG-160, DEC-022).
 *
 * Only 1 of 201 users held it, so the post-login dashboard returned 403 for everyone
 * else. Idempotent: roles that already have it are left alone. Every role actually
 * granted is written to storage/logs/grant-dashboard-permission-{database}.json so the change can
 * be reversed exactly:
 *
 *   php artisan tinker --execute="collect(json_decode(file_get_contents(storage_path('logs/grant-dashboard-permission-'.config('database.connections.'.config('database.default').'.database').'.json')), true)['granted_role_ids'])->each(fn (\$id) => App\Models\IAM\Role::find(\$id)?->revokePermissionTo('admin.dashboard'));"
 *
 * Run: php artisan db:seed --class=GrantDashboardPermissionSeeder
 */
class GrantDashboardPermissionSeeder extends Seeder
{
    private const PERMISSION = 'admin.dashboard';

    public function run(): void
    {
        $permission = Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->first();

        if (! $permission) {
            $this->command?->error('Permission '.self::PERMISSION.' does not exist; nothing granted.');

            return;
        }

        $granted = [];

        Role::where('guard_name', 'web')->orderBy('id')->each(function (Role $role) use ($permission, &$granted) {
            if ($role->name === 'superadmin' || $role->hasPermissionTo($permission)) {
                return;
            }

            $role->givePermissionTo($permission);
            $granted[] = $role->id;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        File::put(storage_path('logs/grant-dashboard-permission-'.config('database.connections.'.config('database.default').'.database').'.json'), json_encode([
            'permission' => self::PERMISSION,
            'granted_at' => now()->toIso8601String(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'granted_role_ids' => $granted,
        ], JSON_PRETTY_PRINT));

        $this->command?->info('Granted '.self::PERMISSION.' to '.count($granted).' roles.');
    }
}
