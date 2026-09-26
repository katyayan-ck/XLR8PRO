<?php

namespace App\Services\IAM;

use App\Models\IAM\Permission;
use Illuminate\Support\Str;

/**
 * Single source of truth for building the Module -> Process -> Permission tree
 * used by every role/permission management screen (demo/roles, demo/users, and
 * the real per-entity "Permissions" panels such as Designation's). Parses the
 * real xlr8_iam_permissions rows via the MODULE_PROCESS_ACTIVITY naming
 * convention established in .ai/rules/module-structure.md.
 */
class PermissionTreeService
{
    /** Module code => friendly label. */
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

    /** Process code => friendly label (best-effort; falls back to title-cased code). */
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
        'ENTITY' => 'Reference Data',
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

    /** Activity code => friendly label. */
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

    /** Build the full Module -> Process -> Permission tree from real permission rows. */
    public function buildTree(): array
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

    /** Flatten a tree (as returned by buildTree()) into a plain list of permission codes. */
    public function flattenCodes(array $tree): array
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

    /** @return array{0: string, 1: string, 2: string, 3: string} [moduleCode, processCode, activityCode, activityLabel] */
    private function parsePermissionName(string $name): array
    {
        // MODULE_PROCESS_ACTIVITY[_MORE], all-uppercase segments, e.g. SLS_BKNG_VIEW,
        // SLS_BKNG_ORDER_VERIFY, PRC_TCS_MANAGE.
        if (preg_match('/^([A-Z]+)_([A-Z0-9]+)_([A-Z_]+)$/', $name, $m)) {
            $activity = $m[3];

            return [$m[1], $m[2], $activity, self::ACTIVITY_LABELS[$activity] ?? Str::title(str_replace('_', ' ', $activity))];
        }

        // MODULE_ACTIVITY (2 segments, no process code — e.g. FIN_IMPORT, INS_IMPORT,
        // ORG_ENTITY_MANAGE), only when the first segment is a module this rollout minted.
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
}
