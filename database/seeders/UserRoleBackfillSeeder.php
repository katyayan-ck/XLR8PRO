<?php

namespace Database\Seeders;

use App\Models\IAM\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Backfills xlr8_iam_model_has_roles for every existing user, using the same
 * "the Employee's Designation IS the Spatie role" principle applied going
 * forward in StandaloneUsersImport/UserImporter (see known-bugs-report.md
 * BUG-071). That fix only covered new/re-imported rows; this seeder is the
 * one-time backfill for users who already existed before it.
 *
 * A user is skipped (not touched) when:
 *  - they have no employee_code (not an Employee-linked account, e.g. SUP001), or
 *  - their Employee record has no resolvable designation_code, or
 *  - no Role row exists with that designation's code.
 * Skips are counted and logged, never silently guessed.
 */
class UserRoleBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $rows = DB::table('users')
            ->join('xlr8_admin_employee', 'xlr8_admin_employee.code', '=', 'users.employee_code')
            ->whereNotNull('users.employee_code')
            ->where('users.is_active', 1)
            ->select('users.id as user_id', 'users.username', 'xlr8_admin_employee.designation_code')
            ->get();

        $roleCache = Role::where('guard_name', 'web')->get()->keyBy('code');

        $updated = 0;
        $unchanged = 0;
        $skippedNoDesignation = 0;
        $skippedNoRole = 0;

        foreach ($rows as $row) {
            if (! $row->designation_code) {
                $skippedNoDesignation++;

                continue;
            }

            $role = $roleCache->get(strtoupper($row->designation_code));
            if (! $role) {
                $skippedNoRole++;
                $this->command?->warn("  no role found for designation code '{$row->designation_code}' (user {$row->username})");

                continue;
            }

            $user = User::find($row->user_id);
            $currentRoleNames = $user->roles->pluck('name')->all();

            if ($currentRoleNames === [$role->name]) {
                $unchanged++;

                continue;
            }

            $user->syncRoles([$role]);
            $updated++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            "UserRoleBackfillSeeder: {$updated} updated, {$unchanged} already correct, "
            .$skippedNoDesignation.' skipped (no designation), '
            .$skippedNoRole.' skipped (no matching role).'
        );
    }
}
