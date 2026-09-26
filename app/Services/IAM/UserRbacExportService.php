<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\User;
use App\Services\OrgScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the user & RBAC workbook (DEC-040): permissions by module/process, designations
 * (Spatie roles) with their permissions, users with identity, role, effective permissions and
 * scopes, one row per scope, and the dropdown lists. The workbook's `Users_Import` and
 * `User_Scopes` sheets use the user importer's own headers, so an edited export can be imported.
 *
 * Dropdown values are labels `Name (CODE)`; the importer resolves them back to codes.
 */
final class UserRbacExportService
{
    public const ALL = 'ALL';

    public const SUPERADMIN_ROLE = 'superadmin';

    /**
     * Editable Users_Import columns (importer headers) → dropdown list key (null = free text).
     *
     * @var array<string, string|null>
     */
    public const USER_COLUMNS = [
        'Emp Code*' => null,
        'Employee Name*' => null,
        'Employee Status' => 'employee_status',
        'Employment Type' => 'employment_type',
        'Login Active' => 'yes_no',
        'Designation*' => 'designation',
        'Reporting Manager' => 'reporting_manager',
        'Primary Branch*' => 'branch',
        'Primary Location*' => 'location',
        'Primary Department*' => 'department',
        'Primary Division' => 'division',
        'Vertical' => 'vertical',
        'Segment' => 'segment',
        'Sub Segment' => 'sub_segment',
        'OEM Mile ID' => null,
        'Personal Contact Number*' => null,
        'Official Contact Number' => null,
        'Official Mail ID' => null,
        'Personal Mail Id' => null,
        'Gender' => 'gender',
        'Date of Birth' => null,
        'Date of Joining' => null,
        'Father Name' => null,
        'PAN No.' => null,
        'Aadhaar No' => null,
        'Address Line 1' => null,
        'Address Line 2' => null,
        'City' => null,
        'State' => null,
        'Pincode' => null,
        'Bank Name' => null,
        'Account Number' => null,
        'IFSC Code' => null,
    ];

    /** Informational Users_Import columns; the prefix keeps their slugged keys away from importer keys. */
    public const READ_ONLY_PREFIX = '[Read-only] ';

    /** @var array<string, string> scope type → Users_Import read-only column title */
    public const SCOPE_COLUMNS = [
        'branch' => 'Branches',
        'location' => 'Locations',
        'department' => 'Departments',
        'division' => 'Divisions',
        'vertical' => 'Verticals',
        'segment' => 'Segments',
        'sub_segment' => 'Sub Segments',
        'model' => 'Vehicle Models',
        'variant' => 'Vehicle Variants',
    ];

    public const INFO_COLUMNS = ['User ID', 'Username', 'User Type', 'Last Login', 'Roles', 'Permission Count', 'Permissions', 'Denied Permissions'];

    /** @var array<string, array<string, string>>|null type → code → label */
    private ?array $labels = null;

    /** @return list<array<int, string|int>> */
    public function permissionRows(): array
    {
        $roleCounts = DB::table('xlr8_iam_role_has_permissions')
            ->select('permission_id', DB::raw('COUNT(*) as n'))
            ->groupBy('permission_id')
            ->pluck('n', 'permission_id');

        return DB::table('xlr8_iam_permissions as p')
            ->leftJoin('xlr8_iam_process as pr', 'pr.code', '=', 'p.process_code')
            ->leftJoin('xlr8_iam_module as m', 'm.code', '=', DB::raw('COALESCE(p.module_code, pr.module_code)'))
            ->whereNull('p.deleted_at')
            ->orderByRaw('m.name IS NULL, m.name, pr.name, p.name')
            ->get(['p.id', 'p.name', 'p.guard_name', 'm.code as module_code', 'm.name as module_name', 'pr.code as process_code', 'pr.name as process_name'])
            ->map(fn ($p) => [
                $p->module_code ?? '(unassigned)',
                $p->module_name ?? '',
                $p->process_code ?? '(unassigned)',
                $p->process_name ?? '',
                $p->name,
                $p->guard_name,
                (int) ($roleCounts[$p->id] ?? 0),
            ])
            ->all();
    }

    /** @return list<array<int, string|int>> */
    public function roleRows(): array
    {
        $permissions = $this->rolePermissionNames();
        $users = DB::table('xlr8_iam_model_has_roles')
            ->select('role_id', DB::raw('COUNT(*) as n'))
            ->groupBy('role_id')
            ->pluck('n', 'role_id');

        return DB::table('xlr8_admin_designation')
            ->whereNull('deleted_at')
            ->orderBy('hierarchy_level')->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                $d->code,
                $d->name,
                (string) ($d->category ?? ''),
                (int) $d->hierarchy_level,
                (string) ($d->parent_desig_code ?? ''),
                $d->is_active ? 'Yes' : 'No',
                (int) ($users[$d->id] ?? 0),
                count($permissions[$d->id] ?? []),
                implode(', ', $permissions[$d->id] ?? []),
            ])
            ->all();
    }

    /**
     * One row per user: editable importer columns followed by the read-only columns.
     *
     * @return list<array<int, string|int|null>>
     */
    public function userRows(): array
    {
        $users = DB::table('users as u')
            ->leftJoin('xlr8_admin_employee as e', 'e.code', '=', 'u.employee_code')
            ->leftJoin('xlr8_admin_person as p', 'p.person_code', '=', DB::raw('COALESCE(e.person_code, u.person_code)'))
            ->whereNull('u.deleted_at')
            ->orderByRaw('u.employee_code IS NULL, u.employee_code')
            ->get([
                'u.id', 'u.username', 'u.user_type', 'u.is_active', 'u.last_login_at', 'u.employee_code',
                'p.person_code', 'p.display_name', 'p.first_name', 'p.last_name', 'p.gender', 'p.dob', 'p.pan_no', 'p.aadhaar_no',
                'e.employment_status', 'e.employment_type', 'e.designation_code', 'e.desig_code', 'e.reporting_manager_code',
                'e.primary_branch_code', 'e.primary_loc_code', 'e.primary_dept_code', 'e.primary_div_code',
                'e.vertical_code', 'e.segment_code', 'e.sub_segment_code', 'e.mile_id', 'e.joining_date', 'e.father_name',
            ]);

        $personCodes = $users->pluck('person_code')->filter()->unique()->values();
        $contacts = DB::table('xlr8_admin_person_contacts')->whereIn('person_code', $personCodes)->whereNull('deleted_at')
            ->get()->groupBy('person_code');
        $addresses = DB::table('xlr8_admin_person_addresses')->whereIn('person_code', $personCodes)->whereNull('deleted_at')
            ->where('address_type', 'Primary')->get()->keyBy('person_code');
        $banks = DB::table('xlr8_admin_person_banking_details')->whereIn('person_code', $personCodes)->whereNull('deleted_at')
            ->where('account_type', 'Primary')->get()->keyBy('person_code');

        $roles = $this->userRoles();
        $effective = $this->effectivePermissions($roles);
        $denied = DB::table('xlr8_iam_user_permission_denials as d')
            ->join('xlr8_iam_permissions as p', 'p.id', '=', 'd.permission_id')
            ->get(['d.user_id', 'p.name'])->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->pluck('name')->sort()->implode(', '));
        $scopes = $this->activeScopes();
        $employeeNames = $this->employeeLabels();

        return $users->map(function ($u) use ($contacts, $addresses, $banks, $roles, $effective, $denied, $scopes, $employeeNames) {
            $contact = fn (string $dataType, string $contactType) => $contacts->get($u->person_code, collect())
                ->first(fn ($c) => $c->data_type === $dataType && $c->contact_type === $contactType)?->contact_detail;
            $address = $addresses->get($u->person_code);
            $bank = $banks->get($u->person_code);
            $userRoles = $roles[$u->id] ?? [];
            $isSuperAdmin = in_array(self::SUPERADMIN_ROLE, array_column($userRoles, 'name'), true);
            $permissions = $effective[$u->id] ?? [];
            $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));

            $row = [
                $u->employee_code,
                $name,
                $u->employment_status,
                $u->employment_type,
                $u->is_active ? 'Yes' : 'No',
                $this->label('designation', $u->designation_code ?: $u->desig_code),
                $u->reporting_manager_code ? ($employeeNames[strtoupper($u->reporting_manager_code)] ?? $u->reporting_manager_code) : null,
                $this->label('branch', $u->primary_branch_code),
                $this->label('location', $u->primary_loc_code),
                $this->label('department', $u->primary_dept_code),
                $this->label('division', $u->primary_div_code),
                $this->label('vertical', $u->vertical_code),
                $this->label('segment', $u->segment_code),
                $this->label('sub_segment', $u->sub_segment_code),
                $u->mile_id,
                $contact('Mobile', 'Primary'),
                $contact('Mobile', 'Office'),
                $contact('Email', 'Primary'),
                $contact('Email', 'Alternate'),
                $u->gender,
                $u->dob ? substr((string) $u->dob, 0, 10) : null,
                $u->joining_date ? substr((string) $u->joining_date, 0, 10) : null,
                $u->father_name,
                $u->pan_no,
                $u->aadhaar_no,
                $address?->address_line_1,
                $address?->address_line_2,
                $address?->city,
                $address?->state,
                $address?->pincode,
                $bank?->bank_name,
                $bank?->account_number,
                $bank?->ifsc_code,
                // read-only
                $u->id,
                $u->username,
                $u->user_type,
                $u->last_login_at ? substr((string) $u->last_login_at, 0, 16) : null,
                implode(', ', array_map(fn ($r) => "{$r['name']} ({$r['code']})", $userRoles)),
                $isSuperAdmin ? 'ALL' : count($permissions),
                $isSuperAdmin ? 'ALL (superadmin bypass)' : implode(', ', $permissions),
                $denied[$u->id] ?? '',
            ];

            foreach (array_keys(self::SCOPE_COLUMNS) as $type) {
                $row[] = implode(', ', $this->compactScope($type, $scopes[$u->id][$type] ?? []));
            }

            return $row;
        })->all();
    }

    /**
     * One row per user + scope type + value (codes compacted to `ALL` when a user holds every
     * active code of a type), for users that have an Emp Code (the importer's key).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function scopeRows(): array
    {
        $empCodes = DB::table('users')->whereNull('deleted_at')->whereNotNull('employee_code')->pluck('employee_code', 'id');
        $rows = [];

        foreach ($this->activeScopes() as $userId => $byType) {
            $empCode = $empCodes[$userId] ?? null;
            if (! $empCode) {
                continue;
            }
            foreach (array_keys(self::SCOPE_COLUMNS) as $type) {
                foreach ($this->compactScope($type, $byType[$type] ?? []) as $label) {
                    $rows[] = [$empCode, $type, $label];
                }
            }
        }

        usort($rows, fn ($a, $b) => [$a[0], array_search($a[1], array_keys(self::SCOPE_COLUMNS), true), $a[2]]
            <=> [$b[0], array_search($b[1], array_keys(self::SCOPE_COLUMNS), true), $b[2]]);

        return $rows;
    }

    /**
     * Dropdown lists: key → values. Scope-type lists start with ALL.
     *
     * @return array<string, list<string>>
     */
    public function lists(): array
    {
        $lists = ['scope_type' => array_keys(self::SCOPE_COLUMNS)];
        foreach (array_keys(self::SCOPE_COLUMNS) as $type) {
            $lists[$type] = array_merge([self::ALL], array_values($this->labels()[$type]));
        }
        $lists['designation'] = array_values($this->labels()['designation']);
        $lists['reporting_manager'] = array_values($this->employeeLabels());
        $lists['employee_status'] = ['active', 'inactive', 'separated', 'terminated', 'absconded'];
        $lists['employment_type'] = ['permanent', 'probation', 'apprentice', 'contract', 'temporary'];
        $lists['gender'] = ['Male', 'Female', 'Other', 'Prefer not to say'];
        $lists['yes_no'] = ['Yes', 'No'];

        return $lists;
    }

    /** @return list<string> Users_Import headers: editable, then read-only. */
    public function userHeaders(): array
    {
        return array_merge(
            array_keys(self::USER_COLUMNS),
            array_map(fn ($h) => self::READ_ONLY_PREFIX.$h, self::INFO_COLUMNS),
            array_map(fn ($h) => self::READ_ONLY_PREFIX.$h, array_values(self::SCOPE_COLUMNS)),
        );
    }

    public function label(string $type, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->labels()[$type][strtoupper($code)] ?? $code;
    }

    /**
     * Codes a user holds for a type as labels; `ALL` when they cover every active code.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function compactScope(string $type, array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $all = array_keys($this->labels()[$type]);
        if ($all !== [] && array_diff($all, $codes) === []) {
            return [self::ALL];
        }
        $labels = array_map(fn ($c) => $this->label($type, $c), $codes);
        sort($labels);

        return $labels;
    }

    /** @return array<int, array<string, list<string>>> user id → type → codes (active, not deleted) */
    private function activeScopes(): array
    {
        $scopes = [];
        DB::table('xlr8_admin_user_scopes')->where('is_active', 1)->whereNull('deleted_at')
            ->orderBy('scope_code')
            ->get(['user_id', 'scope_type', 'scope_code'])
            ->each(function ($s) use (&$scopes) {
                $scopes[$s->user_id][strtolower($s->scope_type)][] = strtoupper($s->scope_code);
            });

        return $scopes;
    }

    /** @return array<int, list<array{id: int, code: string, name: string}>> user id → roles */
    private function userRoles(): array
    {
        return DB::table('xlr8_iam_model_has_roles as mr')
            ->join('xlr8_admin_designation as d', 'd.id', '=', 'mr.role_id')
            ->where('mr.model_type', User::class)
            ->get(['mr.model_id', 'd.id', 'd.code', 'd.name'])
            ->groupBy('model_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($r) => ['id' => (int) $r->id, 'code' => $r->code, 'name' => $r->name])->all())
            ->all();
    }

    /** @return array<int, list<string>> role id → sorted permission names */
    private function rolePermissionNames(): array
    {
        return DB::table('xlr8_iam_role_has_permissions as rp')
            ->join('xlr8_iam_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereNull('p.deleted_at')
            ->orderBy('p.name')
            ->get(['rp.role_id', 'p.name'])
            ->groupBy('role_id')
            ->map(fn (Collection $rows) => $rows->pluck('name')->all())
            ->all();
    }

    /**
     * Role permissions + direct permissions − per-user denials.
     *
     * @param  array<int, list<array{id: int, code: string, name: string}>>  $roles
     * @return array<int, list<string>>
     */
    private function effectivePermissions(array $roles): array
    {
        $byRole = $this->rolePermissionNames();
        $direct = DB::table('xlr8_iam_model_has_permissions as mp')
            ->join('xlr8_iam_permissions as p', 'p.id', '=', 'mp.permission_id')
            ->where('mp.model_type', User::class)
            ->get(['mp.model_id', 'p.name'])->groupBy('model_id');
        $denied = DB::table('xlr8_iam_user_permission_denials as d')
            ->join('xlr8_iam_permissions as p', 'p.id', '=', 'd.permission_id')
            ->get(['d.user_id', 'p.name'])->groupBy('user_id');

        $result = [];
        $userIds = array_unique(array_merge(array_keys($roles), $direct->keys()->all()));
        foreach ($userIds as $userId) {
            $names = [];
            foreach ($roles[$userId] ?? [] as $role) {
                $names = array_merge($names, $byRole[$role['id']] ?? []);
            }
            $names = array_merge($names, $direct->get($userId, collect())->pluck('name')->all());
            $names = array_diff(array_unique($names), $denied->get($userId, collect())->pluck('name')->all());
            sort($names);
            $result[$userId] = $names;
        }

        return $result;
    }

    /** @return array<string, string> EMP CODE → "Name (EMP CODE)" for active employees */
    private function employeeLabels(): array
    {
        return DB::table('xlr8_admin_employee as e')
            ->leftJoin('xlr8_admin_person as p', 'p.person_code', '=', 'e.person_code')
            ->whereNull('e.deleted_at')
            ->where('e.employment_status', 'active')
            ->orderBy('p.display_name')
            ->get(['e.code', 'p.display_name', 'p.first_name', 'p.last_name'])
            ->mapWithKeys(fn ($e) => [strtoupper($e->code) => trim(($e->display_name ?: trim(($e->first_name ?? '').' '.($e->last_name ?? ''))) ?: $e->code).' ('.$e->code.')'])
            ->all();
    }

    /** @return array<string, array<string, string>> type → CODE → label (active rows only) */
    private function labels(): array
    {
        if ($this->labels !== null) {
            return $this->labels;
        }

        $tables = [
            'branch' => ['xlr8_admin_branch', 'name', 'name'],
            'location' => ['xlr8_admin_location', 'name', 'name'],
            'department' => ['xlr8_admin_department', 'name', 'name'],
            'division' => ['xlr8_admin_division', 'name', 'name'],
            'vertical' => ['xlr8_admin_vertical', 'name', 'name'],
            'segment' => ['xlr8_vehicle_segment', 'name', 'name'],
            'sub_segment' => ['xlr8_vehicle_subsegment', 'name', 'name'],
            'model' => ['xlr8_vehicle_model', 'name', 'name'],
            'variant' => ['xlr8_vehicle_variant', "COALESCE(NULLIF(display_name, ''), oem_name)", 'model_code'],
            'designation' => ['xlr8_admin_designation', 'name', 'name'],
        ];

        foreach ($tables as $type => [$table, $nameExpr, $order]) {
            $this->labels[$type] = DB::table($table)
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->orderBy($order)->orderBy('code')
                ->get(['code', DB::raw("{$nameExpr} as label_name")])
                ->unique(fn ($r) => strtoupper($r->code))
                ->mapWithKeys(fn ($r) => [strtoupper($r->code) => trim((string) $r->label_name).' ('.$r->code.')'])
                ->all();
        }

        // Types without master rows still get an (empty) list.
        foreach (OrgScopeService::types() as $type) {
            $this->labels[$type] ??= [];
        }

        return $this->labels;
    }
}
