<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Models\IAM\Permission;
use App\Models\IAM\Role;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * UI/UX mockup for the planned Module -> Process -> Permission role/user
 * management screens (demo/roles, demo/users). The permission TREE is built
 * from the real 225 rows in xlr8_iam_permissions (parsed via the
 * MODULE_PROCESS_ACTIVITY naming convention this rollout established) and
 * the ROLE list is the real 76 Designation-backed roles — only the
 * role->permission and user-override ASSIGNMENTS are spoofed, since no real
 * assignment data exists yet (nothing has been through the Role UI).
 *
 * Not wired to any persistence — every "Save" in these views is client-side
 * only, to demonstrate the intended interaction before the real backend
 * (migrations, controllers, JS wiring) is built.
 */
class DemoRbacController extends Controller
{
    /** Module code => friendly label, matching .ai/rules/module-structure.md */
    private const MODULE_LABELS = [
        'SLS' => 'Sales',
        'ACC' => 'Accounts',
        'FIN' => 'Finance',
        'INS' => 'Insurance',
        'RTO' => 'RTO',
        'SPR' => 'Spares',
        'VEH' => 'Vehicle',
        'ORG' => 'Organization',
        'IAM' => 'Access Control (IAM)',
        'PRC' => 'Pricing',
        'UTL' => 'Utilities',
        'LEGACY' => 'Legacy / Ungrouped',
    ];

    /** Process code => friendly label (best-effort; falls back to title-cased code) */
    private const PROCESS_LABELS = [
        'BKNG' => 'Booking',
        'ENQR' => 'Enquiry',
        'QUOT' => 'Quotation',
        'CMPN' => 'Campaign',
        'LEAD' => 'Lead',
        'LDSR' => 'Lead Source',
        'RCPT' => 'Receipt',
        'JRVCH' => 'Journal Voucher',
        'BRCH' => 'Branch',
        'DEPT' => 'Department',
        'DESG' => 'Designation',
        'DIVN' => 'Division',
        'EMPL' => 'Employee',
        'LOCN' => 'Location',
        'PRSN' => 'Person',
        'USER' => 'User',
        'HOLD' => 'Price Hold',
        'INSR' => 'Insurance Rules',
        'RESET' => 'Pricing Reset',
        'RTOR' => 'RTO Rules',
        'TCS' => 'TCS Config',
        'WKFL' => 'Pricing Workflow',
        'RBAC' => 'Access Control',
        'REQ' => 'Spare Request',
        'BRND' => 'Brand',
        'CLR' => 'Color',
        'MDL' => 'Vehicle Model',
        'SEG' => 'Segment',
        'VAR' => 'Variant',
        'SETTINGS' => 'Settings',
        'IMPORT' => 'Import',
        'GENERAL' => 'General',
    ];

    /** Activity code => friendly label */
    private const ACTIVITY_LABELS = [
        'VIEW' => 'View',
        'CREATE' => 'Create',
        'EDIT' => 'Edit',
        'DELETE' => 'Delete',
        'EXPORT' => 'Export',
        'IMPORT' => 'Import',
        'MANAGE' => 'Manage',
        'ORDER_VERIFY' => 'Order Verification',
    ];

    /** A few hand-picked roles get a realistic starting permission set for the demo. */
    private const DEMO_ROLE_PERMISSIONS = [
        'CEO' => ['*ALL*'],
        'Branch Manager' => [
            'SLS_BKNG_VIEW', 'SLS_BKNG_CREATE', 'SLS_BKNG_EDIT', 'SLS_BKNG_REPORT',
            'SLS_ENQR_VIEW', 'SLS_ENQR_CREATE', 'SLS_ENQR_EDIT',
            'SLS_QUOT_VIEW', 'SLS_QUOT_CREATE',
            'ORG_EMPL_VIEW', 'ORG_BRCH_VIEW',
            'ACC_RCPT_VIEW', 'ACC_RCPT_CREATE',
        ],
        'Sales Manager' => [
            'SLS_BKNG_VIEW', 'SLS_BKNG_CREATE', 'SLS_BKNG_EDIT',
            'SLS_ENQR_VIEW', 'SLS_ENQR_CREATE', 'SLS_ENQR_EDIT', 'SLS_ENQR_EXPORT',
            'SLS_QUOT_VIEW', 'SLS_QUOT_CREATE', 'SLS_QUOT_EDIT',
            'SLS_CMPN_VIEW', 'SLS_LEAD_VIEW', 'SLS_LEAD_CREATE', 'SLS_LDSR_VIEW',
        ],
        'Sales Consultant' => [
            'SLS_ENQR_VIEW', 'SLS_ENQR_CREATE',
            'SLS_QUOT_VIEW', 'SLS_QUOT_CREATE',
            'SLS_BKNG_VIEW', 'SLS_BKNG_CREATE',
            'SLS_LEAD_VIEW',
        ],
        'Accounts Manager' => [
            'ACC_RCPT_VIEW', 'ACC_RCPT_CREATE', 'ACC_RCPT_EDIT',
            'ACC_JRVCH_VIEW', 'ACC_JRVCH_CREATE', 'ACC_JRVCH_EDIT',
            'FIN_IMPORT', 'PRC_TCS_VIEW',
        ],
        'HR Manager' => [
            'ORG_EMPL_VIEW', 'ORG_EMPL_CREATE', 'ORG_EMPL_EDIT',
            'ORG_PRSN_VIEW', 'ORG_PRSN_CREATE', 'ORG_PRSN_EDIT',
            'ORG_DEPT_VIEW', 'ORG_DESG_VIEW', 'ORG_DIVN_VIEW', 'ORG_BRCH_VIEW',
        ],
        'Web Developer' => [
            'IAM_RBAC_VIEW', 'IAM_RBAC_MANAGE',
            'UTL_SETTINGS_VIEW', 'UTL_SETTINGS_MANAGE',
            'ORG_USER_VIEW', 'ORG_USER_CREATE', 'ORG_USER_EDIT',
        ],
        'Service Manager' => [
            'SPR_REQ_VIEW', 'SPR_REQ_CREATE', 'SPR_REQ_EDIT',
            'VEH_MDL_VIEW', 'VEH_VAR_VIEW',
        ],
    ];

    public function roles()
    {
        $tree = $this->buildPermissionTree();
        $allCodes = $this->flattenCodes($tree);

        $roles = Role::whereNotIn('name', ['superadmin'])
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($role) use ($allCodes) {
                $spoofed = self::DEMO_ROLE_PERMISSIONS[$role->name] ?? [];
                $perms = in_array('*ALL*', $spoofed, true) ? $allCodes : $spoofed;

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'permissions' => array_values(array_intersect($allCodes, $perms)),
                ];
            })
            ->values();

        return view('demo.roles', [
            'tree' => $tree,
            'roles' => $roles,
            'defaultRoleId' => optional($roles->firstWhere('name', 'Sales Manager'))['id'] ?? optional($roles->first())['id'],
        ]);
    }

    public function users()
    {
        $tree = $this->buildPermissionTree();
        $allCodes = $this->flattenCodes($tree);

        $roles = Role::whereNotIn('name', ['superadmin'])
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($role) use ($allCodes) {
                $spoofed = self::DEMO_ROLE_PERMISSIONS[$role->name] ?? [];
                $perms = in_array('*ALL*', $spoofed, true) ? $allCodes : $spoofed;

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => array_values(array_intersect($allCodes, $perms)),
                ];
            })
            ->values();

        $roleByName = $roles->keyBy('name');

        $realUsers = User::query()
            ->join('xlr8_admin_employee', 'xlr8_admin_employee.code', '=', 'users.employee_code')
            ->join('xlr8_admin_person', 'xlr8_admin_person.person_code', '=', 'xlr8_admin_employee.person_code')
            ->where('users.is_active', 1)
            ->whereNotNull('users.employee_code')
            ->orderBy('xlr8_admin_person.display_name')
            ->limit(12)
            ->get([
                'users.id', 'users.username',
                'xlr8_admin_person.display_name',
                'xlr8_admin_employee.designation_code',
            ]);

        // Give each demo user a role from the curated set (cycling through them) so the
        // override UI has real inherited permissions to work with, and hand-craft a
        // couple of override examples so the "added"/"removed" states are visible on load.
        $curatedRoleNames = array_keys(self::DEMO_ROLE_PERMISSIONS);
        $exampleOverrides = [
            0 => ['added' => ['IAM_RBAC_VIEW'], 'removed' => ['SLS_BKNG_EDIT']],
            2 => ['added' => ['ACC_RCPT_VIEW', 'ORG_EMPL_VIEW'], 'removed' => []],
            4 => ['added' => [], 'removed' => ['SLS_QUOT_CREATE', 'SLS_LEAD_VIEW']],
        ];

        $users = $realUsers->values()->map(function ($u, $i) use ($curatedRoleNames, $roleByName, $exampleOverrides) {
            $roleName = $curatedRoleNames[$i % count($curatedRoleNames)];
            $role = $roleByName->get($roleName);
            $overrides = $exampleOverrides[$i] ?? ['added' => [], 'removed' => []];

            return [
                'id' => $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name ?: $u->username,
                'designation_code' => $u->designation_code,
                'role_id' => $role['id'] ?? null,
                'role_name' => $roleName,
                'overrides' => $overrides,
            ];
        });

        return view('demo.users', [
            'tree' => $tree,
            'roles' => $roles,
            'users' => $users,
        ]);
    }

    /**
     * Parse the real xlr8_iam_permissions rows into a Module -> Process -> Permission
     * tree using the MODULE_PROCESS_ACTIVITY naming convention. Anything that doesn't
     * match (legacy lowercase resource.action names, the wildcard '*', odd one-offs)
     * is bucketed under a synthetic "LEGACY" module so no real data is silently dropped.
     */
    private function buildPermissionTree(): array
    {
        $names = Permission::orderBy('name')->pluck('name');

        $modules = [];

        foreach ($names as $name) {
            [$moduleCode, $processCode, $activity, $activityLabel] = $this->parsePermissionName($name);

            $modules[$moduleCode] ??= [
                'code' => $moduleCode,
                'label' => self::MODULE_LABELS[$moduleCode] ?? Str::title(str_replace('_', ' ', $moduleCode)),
                'processes' => [],
            ];

            $modules[$moduleCode]['processes'][$processCode] ??= [
                'code' => $processCode,
                'label' => self::PROCESS_LABELS[$processCode] ?? Str::title(str_replace('_', ' ', $processCode)),
                'permissions' => [],
            ];

            $modules[$moduleCode]['processes'][$processCode]['permissions'][] = [
                'code' => $name,
                'label' => $activityLabel,
            ];
        }

        // LEGACY last, everything else alphabetical by label.
        uksort($modules, fn ($a, $b) => $a === 'LEGACY' ? 1 : ($b === 'LEGACY' ? -1 : strcmp($modules[$a]['label'], $modules[$b]['label'])));

        foreach ($modules as &$module) {
            ksort($module['processes']);
            $module['processes'] = array_values($module['processes']);
        }

        return array_values($modules);
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} [moduleCode, processCode, activityCode, activityLabel] */
    private function parsePermissionName(string $name): array
    {
        // MODULE_PROCESS_ACTIVITY[_MORE], all-uppercase segments, e.g. SLS_BKNG_VIEW,
        // SLS_BKNG_ORDER_VERIFY, PRC_TCS_MANAGE.
        if (preg_match('/^([A-Z]+)_([A-Z0-9]+)_([A-Z_]+)$/', $name, $m)) {
            $activity = $m[3];

            return [$m[1], $m[2], $activity, self::ACTIVITY_LABELS[$activity] ?? Str::title(str_replace('_', ' ', $activity))];
        }

        // MODULE_ACTIVITY (2 segments, no process code — e.g. FIN_IMPORT, INS_IMPORT),
        // only when the first segment is a module this rollout actually minted.
        if (preg_match('/^([A-Z]+)_([A-Z_]+)$/', $name, $m) && isset(self::MODULE_LABELS[$m[1]])) {
            $activity = $m[2];

            return [$m[1], 'GENERAL', $activity, self::ACTIVITY_LABELS[$activity] ?? Str::title(str_replace('_', ' ', $activity))];
        }

        // Legacy "resource.action" convention (e.g. branch.view, campaign.create).
        if (str_contains($name, '.')) {
            [$resource, $action] = array_pad(explode('.', $name, 2), 2, 'manage');

            return ['LEGACY', Str::upper($resource), $action, Str::title(str_replace('_', ' ', $action))];
        }

        // Odd one-offs (the wildcard '*', anything else unmatched).
        return ['LEGACY', 'OTHER', $name, $name === '*' ? 'All (wildcard)' : Str::title(str_replace('_', ' ', $name))];
    }

    private function flattenCodes(array $tree): array
    {
        $codes = [];
        foreach ($tree as $module) {
            foreach ($module['processes'] as $process) {
                foreach ($process['permissions'] as $permission) {
                    $codes[] = $permission['code'];
                }
            }
        }

        return $codes;
    }
}
