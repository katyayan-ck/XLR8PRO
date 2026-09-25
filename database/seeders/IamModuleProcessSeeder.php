<?php

namespace Database\Seeders;

use App\Models\IAM\Module;
use App\Models\IAM\Permission;
use App\Models\IAM\Process;
use Illuminate\Database\Seeder;

/**
 * Populates xlr8_iam_module / xlr8_iam_process with the real module/process
 * structure this app's permission-naming convention (MODULE_PROCESS_ACTIVITY)
 * has used since the Module/Process/Activity migration rollout, and backfills
 * module_code/process_code on every row in xlr8_iam_permissions.
 *
 * Removes the 2 placeholder "Demo Module"/"Demo Process" rows first — confirmed
 * via grep that nothing in app/ or resources/ references them by code.
 *
 * Permission classification:
 *  - New-convention names (MODULE_PROCESS_ACTIVITY or MODULE_ACTIVITY, e.g.
 *    SLS_BKNG_VIEW, FIN_IMPORT) are parsed directly.
 *  - Old lowercase "resource.action" names that were superseded by a new-
 *    convention equivalent during the earlier rollout (e.g. branch.view ->
 *    ORG_BRCH_VIEW) are mapped to that SAME module/process, since they're the
 *    same real-world resource, just the pre-rename permission row — these rows
 *    are orphaned (nothing checks them anymore) but still get categorized
 *    rather than left as loose ends.
 *  - Genuinely unmapped/dead ones (the wildcard '*', 'foundation.*', 'post.*',
 *    'user_type.*' — all confirmed dead/unreachable controllers per
 *    known-bugs-report.md BUG-015/024) land in a synthetic LEGACY module.
 */
class IamModuleProcessSeeder extends Seeder
{
    /** @var array<string, string> module code => name */
    private const MODULES = [
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
        'SYS' => 'System',
        'LEGACY' => 'Legacy / Ungrouped',
    ];

    /** @var array<string, array<string, string>> module code => [process code => name] */
    private const PROCESSES = [
        'SLS' => [
            'BKNG' => 'Booking',
            'ENQR' => 'Enquiry',
            'QUOT' => 'Quotation',
            'CMPN' => 'Campaign',
            'LEAD' => 'Lead',
            'LDSR' => 'Lead Source',
        ],
        'ACC' => [
            'RCPT' => 'Receipt',
            'JRVCH' => 'Journal Voucher',
        ],
        'FIN' => ['FIN_GEN' => 'General'],
        'INS' => ['INS_GEN' => 'General'],
        'RTO' => ['RTO_GEN' => 'General'],
        'SPR' => ['REQ' => 'Spare Request'],
        'VEH' => [
            'BRND' => 'Brand',
            'CLR' => 'Color',
            'MDL' => 'Vehicle Model',
            'SEG' => 'Segment',
            'VAR' => 'Variant',
            'VEH_GEN' => 'General',
        ],
        'ORG' => [
            'BRCH' => 'Branch',
            'DEPT' => 'Department',
            'DESG' => 'Designation',
            'DIVN' => 'Division',
            'EMPL' => 'Employee',
            'LOCN' => 'Location',
            'PRSN' => 'Person',
            'USER' => 'User',
        ],
        'IAM' => ['RBAC' => 'Access Control'],
        'PRC' => [
            'HOLD' => 'Price Hold',
            'INSR' => 'Insurance Rules',
            'RESET' => 'Pricing Reset',
            'RTOR' => 'RTO Rules',
            'TCS' => 'TCS Config',
            'WKFL' => 'Pricing Workflow',
        ],
        'UTL' => ['SETTINGS' => 'Settings'],
        'SYS' => ['SYS_GEN' => 'General'],
        'LEGACY' => ['OTHER' => 'Other'],
    ];

    /** Old "resource.action" name -> [module, process] it was superseded by. */
    private const LEGACY_RESOURCE_MAP = [
        'branch' => ['ORG', 'BRCH'],
        'department' => ['ORG', 'DEPT'],
        'designation' => ['ORG', 'DESG'],
        'division' => ['ORG', 'DIVN'],
        'employee' => ['ORG', 'EMPL'],
        'location' => ['ORG', 'LOCN'],
        'person' => ['ORG', 'PRSN'],
        'users' => ['ORG', 'USER'],
        'brand' => ['VEH', 'BRND'],
        'color' => ['VEH', 'CLR'],
        'model' => ['VEH', 'MDL'],
        'segment' => ['VEH', 'SEG'],
        'variant' => ['VEH', 'VAR'],
        'vehicles' => ['VEH', 'VEH_GEN'],
        'campaign' => ['SLS', 'CMPN'],
        'lead' => ['SLS', 'LEAD'],
        'lead_source' => ['SLS', 'LDSR'],
        'journal_voucher' => ['ACC', 'JRVCH'],
        'receipt' => ['ACC', 'RCPT'],
        'spare_request' => ['SPR', 'REQ'],
        'finance' => ['FIN', 'FIN_GEN'],
        'insurance' => ['INS', 'INS_GEN'],
        'rto' => ['RTO', 'RTO_GEN'],
        'rbac' => ['IAM', 'RBAC'],
        'settings' => ['UTL', 'SETTINGS'],
        'admin' => ['SYS', 'SYS_GEN'],
        'audit' => ['SYS', 'SYS_GEN'],
    ];

    public function run(): void
    {
        Module::where('code', 'DEMO_MODULE')->orWhere('code', 'DEMOMODULE2')->delete();
        Process::where('code', 'DEMO_PROCESS')->delete();

        foreach (self::MODULES as $code => $name) {
            Module::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $name.' module', 'is_active' => true]
            );
        }

        foreach (self::PROCESSES as $moduleCode => $processes) {
            foreach ($processes as $code => $name) {
                Process::updateOrCreate(
                    ['module_code' => $moduleCode, 'code' => $code],
                    ['name' => $name, 'description' => $name.' process ('.$moduleCode.')', 'is_active' => true]
                );
            }
        }

        $updated = 0;
        $legacy = 0;

        Permission::all()->each(function (Permission $permission) use (&$updated, &$legacy) {
            [$moduleCode, $processCode] = $this->classify($permission->name);

            if ($moduleCode === 'LEGACY') {
                $legacy++;
            }

            $permission->forceFill([
                'module_code' => $moduleCode,
                'process_code' => $processCode,
            ])->saveQuietly();

            $updated++;
        });

        $this->command?->info("IamModuleProcessSeeder: {$updated} permissions classified ({$legacy} landed in LEGACY).");
    }

    /** @return array{0: string, 1: string} [moduleCode, processCode] */
    private function classify(string $name): array
    {
        if (preg_match('/^([A-Z]+)_([A-Z0-9]+)_([A-Z_]+)$/', $name, $m) && isset(self::MODULES[$m[1]])) {
            return [$m[1], $m[2]];
        }

        if (preg_match('/^([A-Z]+)_([A-Z_]+)$/', $name, $m) && isset(self::MODULES[$m[1]])) {
            return [$m[1], $m[1].'_GEN'];
        }

        if (str_contains($name, '.')) {
            [$resource] = explode('.', $name, 2);
            if (isset(self::LEGACY_RESOURCE_MAP[$resource])) {
                return self::LEGACY_RESOURCE_MAP[$resource];
            }
        }

        return ['LEGACY', 'OTHER'];
    }
}
