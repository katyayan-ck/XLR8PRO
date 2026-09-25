<?php

namespace Database\Seeders;

use App\Models\IAM\Permission;
use App\Models\IAM\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Assigns a starting set of permissions to every real Designation-backed role
 * (role_has_permissions / xlr8_iam_role_has_permissions).
 *
 * IMPORTANT: no access matrix for these 75 roles has ever existed in this app
 * (confirmed — nothing has ever gone through a Role UI). This is a REASONABLE,
 * INFERRED STARTING POINT based on job-title semantics (Manager vs Executive
 * vs Consultant seniority, department keyword matching to modules), not a
 * business-verified access control policy. It is meant to be reviewed and
 * adjusted by the app owner (via the Role UI, or the planned demo/roles screen
 * once it persists) — not treated as final.
 *
 * Tiers per module:
 *  - 'full'  -> every permission under that module (all activities)
 *  - 'basic' -> only *_VIEW and *_CREATE permissions (day-to-day operational use)
 *  - 'view'  -> only *_VIEW permissions (read-only)
 *
 * 'superadmin' is deliberately excluded — it bypasses all checks via
 * Gate::before() in AppServiceProvider, independent of any assigned permission.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, array<string, string>> role name => [module code => tier] */
    private const ROLE_PROFILES = [
        'Accessories Executive' => ['SPR' => 'basic'],
        'Accessories Fitter' => ['SPR' => 'view'],
        'Accounts Executive' => ['ACC' => 'basic'],
        'Accounts Manager' => ['ACC' => 'full', 'PRC' => 'view'],
        'Admin Executive' => ['ORG' => 'basic', 'UTL' => 'view'],
        'Admin Manager' => ['ORG' => 'full', 'UTL' => 'full'],
        'Assistant Accounts Manager' => ['ACC' => 'full'],
        'Assistant Admin Manager' => ['ORG' => 'full'],
        'Assistant Bodyshop Manager' => ['VEH' => 'basic', 'SPR' => 'view'],
        'Assistant Infra Manager' => ['UTL' => 'view'],
        'Back Office Executive' => ['SLS' => 'view', 'ACC' => 'view'],
        'Back Office Manager' => ['SLS' => 'basic', 'ACC' => 'basic'],
        'Bodyshop Manager' => ['VEH' => 'full', 'SPR' => 'basic'],
        'Branch Manager' => ['SLS' => 'full', 'ORG' => 'view', 'ACC' => 'basic'],
        'Business Head - Service' => ['VEH' => 'full', 'SPR' => 'full', 'SLS' => 'view'],
        'CEO' => ['SLS' => 'full', 'ACC' => 'full', 'ORG' => 'full', 'PRC' => 'full', 'VEH' => 'full', 'SPR' => 'full', 'IAM' => 'full', 'UTL' => 'full', 'FIN' => 'view', 'INS' => 'view', 'RTO' => 'view'],
        'Customer Experience Manager' => ['SLS' => 'full'],
        'Customer Relationship Executive' => ['SLS' => 'basic'],
        'Customer Relationship Manager' => ['SLS' => 'full'],
        'Deputy General Manager' => ['SLS' => 'full', 'ACC' => 'full', 'ORG' => 'full', 'VEH' => 'full', 'SPR' => 'full', 'PRC' => 'view', 'FIN' => 'view', 'INS' => 'view', 'RTO' => 'view'],
        'Digital Marketing Manager' => ['SLS' => 'basic'],
        'Director' => ['SLS' => 'full', 'ACC' => 'full', 'ORG' => 'full', 'PRC' => 'full', 'VEH' => 'full', 'SPR' => 'full', 'IAM' => 'full', 'UTL' => 'full', 'FIN' => 'view', 'INS' => 'view', 'RTO' => 'view'],
        'EDP Executive' => ['UTL' => 'view', 'IAM' => 'view'],
        'Factory Manager' => ['VEH' => 'full'],
        'Floor Controller' => ['VEH' => 'view'],
        'Floor Incharge' => ['VEH' => 'view'],
        'General Manager' => ['SLS' => 'full', 'ACC' => 'full', 'ORG' => 'full', 'PRC' => 'view', 'VEH' => 'full', 'SPR' => 'full', 'IAM' => 'view', 'UTL' => 'view', 'FIN' => 'view', 'INS' => 'view', 'RTO' => 'view'],
        'HR Executive' => ['ORG' => 'basic'],
        'HR Manager' => ['ORG' => 'full'],
        'Infra Executive' => ['UTL' => 'view'],
        'Insurance Advisor - Field' => ['PRC' => 'view', 'SLS' => 'view'],
        'Insurance Advisor - Tele' => ['PRC' => 'view', 'SLS' => 'view'],
        'Insurance Coordinator' => ['PRC' => 'basic'],
        'Insurance Manager' => ['PRC' => 'full', 'SLS' => 'view'],
        'Internal Auditor' => ['SLS' => 'view', 'ACC' => 'view', 'ORG' => 'view', 'PRC' => 'view', 'VEH' => 'view', 'SPR' => 'view', 'FIN' => 'view', 'INS' => 'view', 'RTO' => 'view', 'IAM' => 'view', 'UTL' => 'view'],
        'Logistics Coordinator' => ['RTO' => 'view', 'VEH' => 'view'],
        'MIS Executive' => ['UTL' => 'view'],
        'Parts Executive' => ['SPR' => 'basic'],
        'Parts Manager' => ['SPR' => 'full'],
        'PDI Executive' => ['VEH' => 'view'],
        'PDI Incharge' => ['VEH' => 'basic'],
        'Project Incharge' => ['VEH' => 'view'],
        'Purchase Manager' => ['SPR' => 'full', 'PRC' => 'view'],
        'Quality Controller' => ['VEH' => 'view'],
        'Sales Cashier' => ['ACC' => 'basic', 'SLS' => 'view'],
        'Sales Consultant' => ['SLS' => 'basic'],
        'Sales Manager' => ['SLS' => 'full', 'ACC' => 'view'],
        'Service Advisor' => ['SPR' => 'basic', 'VEH' => 'view'],
        'Service Cashier' => ['ACC' => 'basic'],
        'Service Manager' => ['SPR' => 'full', 'VEH' => 'basic'],
        'Technical Manager' => ['VEH' => 'full'],
        'Technical Manager - Trainee' => ['VEH' => 'view'],
        'Technician' => ['VEH' => 'view'],
        'Used Car Manager' => ['VEH' => 'full', 'SLS' => 'full'],
        'Used Car Procurement Manager' => ['VEH' => 'full'],
        'Used Car Refurb Executive' => ['VEH' => 'basic'],
        'Used Car Sales Consultant' => ['SLS' => 'basic', 'VEH' => 'view'],
        'Vehicle Finance Executive' => ['PRC' => 'view', 'SLS' => 'view'],
        'Vehicle Finance Manager' => ['PRC' => 'full', 'SLS' => 'view'],
        'Warranty Assistant' => ['SPR' => 'view'],
        'Warranty Manager' => ['SPR' => 'full'],
        'Web Developer' => ['IAM' => 'full', 'UTL' => 'full', 'ORG' => 'view'],
        'Web Developer - Intern' => ['IAM' => 'view', 'UTL' => 'view'],
        // Deliberately no entry (== no permissions) for roles with no plausible admin-panel
        // use: Driver, Electrician, Hostess, Housekeeping Executive, Hygiene Supervisor,
        // Office Boy, Receptionist, Security Guard, Technician - Trainee, Test Drive
        // Executive, Tool Incharge, Washing Boy.
    ];

    public function run(): void
    {
        /** @var Collection<string, Collection<int, Permission>> */
        $byModule = Permission::whereNotNull('module_code')->get()->groupBy('module_code');

        $assigned = 0;
        $skippedNoProfile = 0;

        foreach (Role::where('guard_name', 'web')->where('name', '!=', 'superadmin')->get() as $role) {
            $profile = self::ROLE_PROFILES[$role->name] ?? null;

            if ($profile === null) {
                $skippedNoProfile++;

                continue;
            }

            $codes = [];
            foreach ($profile as $moduleCode => $tier) {
                $modulePerms = $byModule->get($moduleCode, collect());
                $codes = array_merge($codes, $this->permsForTier($modulePerms, $tier));
            }

            $role->syncPermissions(array_values(array_unique($codes)));
            $assigned++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $totalRoles = Role::where('guard_name', 'web')->count();
        $this->command?->info(
            "RolePermissionSeeder: {$assigned} roles assigned permissions, "
            .$skippedNoProfile.' left with no permissions (no plausible admin-panel use), '
            ."out of {$totalRoles} total roles (superadmin excluded — bypasses via Gate::before)."
        );
    }

    /** @param Collection<int, Permission> $modulePermissions */
    private function permsForTier(Collection $modulePermissions, string $tier): array
    {
        return match ($tier) {
            'full' => $modulePermissions->pluck('name')->all(),
            'basic' => $modulePermissions
                ->filter(fn (Permission $p) => str_ends_with($p->name, '_VIEW') || str_ends_with($p->name, '_CREATE'))
                ->pluck('name')->all(),
            'view' => $modulePermissions
                ->filter(fn (Permission $p) => str_ends_with($p->name, '_VIEW'))
                ->pluck('name')->all(),
            default => [],
        };
    }
}
