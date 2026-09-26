<?php

namespace App\Imports\Sheets;

use App\Models\Admin\Person;
use App\Models\IAM\Role;
use App\Models\User;
use App\Services\IdentifierService;
use App\Services\OrgScopeService;
use App\Services\PersonService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Spatie\Permission\PermissionRegistrar;

/**
 * Standalone Users Import – uses PersonService + OrgScopeService
 *
 * Architecture: person → employee (scopes) → user (Designation as role)
 */
class StandaloneUsersImport implements ToCollection, WithHeadingRow
{
    private int $success = 0;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private int $rowIndex = 1;

    /**
     * @return array{success: int, created: int, updated: int, skipped: int, failed: int}
     */
    public function summary(): array
    {
        return [
            'success' => $this->success,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
        ];
    }

    public function collection(Collection $rows)
    {
        echo "\n🚀 Starting standalone Users_Import (PersonService + OrgScopeService)...\n\n";

        foreach ($rows as $row) {
            $this->rowIndex++;
            $this->processRow($row->toArray(), $this->rowIndex);
        }

        // Cleared once at the end, not per-row, to avoid needless cache churn on large imports.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        echo "\n✅ Standalone Users Import Completed! Success: {$this->success} "
            ."(created {$this->created}, updated {$this->updated}, skipped {$this->skipped}, failed {$this->failed})\n";
    }

    private function processRow(array $row, int $rowIndex): void
    {
        $empCode = $this->getValue($row, ['emp_code', 'Emp Code*']);
        if (! $empCode) {
            return;
        }

        // Employee Name is mandatory: a row without it can't identify a person (BUG-162 —
        // such rows used to create nameless persons and re-point existing employees).
        if ($this->s($this->getValue($row, ['employee_name', 'Employee Name*'])) === '') {
            $this->skipped++;
            $this->logRow($rowIndex, '⏭️ SKIPPED', "{$empCode}: missing Employee Name");

            return;
        }

        try {
            // person_code is immutable: an existing employee keeps its person; only a new
            // employee gets a derived code (Aadhaar → PAN → PERS-###### sequence).
            $existingPersonCode = DB::table('xlr8_admin_employee')->where('code', $empCode)->value('person_code');
            $personCode = $existingPersonCode ?: $this->derivePersonCode($row);
            $isNew = $existingPersonCode === null;

            // 1. Person (core + contacts + addresses + banking) via PersonService
            $this->createOrUpdatePerson($row, $personCode, $rowIndex);

            // 2. Employee (primary_* columns only — no pivot tables)
            $desigCode = $this->createOrUpdateEmployee($row, $empCode, $personCode, $rowIndex);

            // 3. User account
            $userId = $this->createOrUpdateUser($row, $empCode, $personCode, $rowIndex);

            // 4. Scopes → xlr8_admin_user_scopes (primary + expanded ALL)
            $this->syncUserScopes($row, $userId, $rowIndex);

            // 5. person ↔ user_type link
            $this->syncPersonUserType($personCode, $userId, $row);

            // 6. Designation → Spatie role (the Employee's Designation IS the role — see
            // config/permission.php's 'roles' table mapping to xlr8_admin_designation)
            $this->syncUserRole($userId, $desigCode, $rowIndex);

            $this->success++;
            $isNew ? $this->created++ : $this->updated++;
            echo "[Row {$rowIndex}] ✅ SUCCESS - {$empCode}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $this->logRow($rowIndex, '❌ FAILED', $e->getMessage());
            Log::error("StandaloneUsersImport row {$rowIndex} failed", [
                'emp_code' => $empCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PERSON (PersonService)
    // ─────────────────────────────────────────────────────────────

    private function createOrUpdatePerson(array $row, string $personCode, int $rowIndex): void
    {
        $fullName = $this->s($this->getValue($row, ['employee_name', 'Employee Name*']));

        $contacts = [];
        $mobile = $this->cleanPhone($this->getValue($row, ['personal_contact_number', 'Personal Contact Number*']));
        if ($mobile) {
            $contacts[] = [
                'data_type' => 'Mobile',
                'contact_type' => 'Primary',
                'contact_detail' => $mobile,
                'is_primary' => true,
            ];
        }

        $officialMobile = $this->cleanPhone($this->getValue($row, ['official_contact_number', 'Official Contact Number']));
        if ($officialMobile && $officialMobile !== $mobile) {
            $contacts[] = [
                'data_type' => 'Mobile',
                'contact_type' => 'Office',
                'contact_detail' => $officialMobile,
            ];
        }

        $email = $this->n($this->getValue($row, ['official_mail_id', 'Official Mail ID', 'personal_mail_id', 'Personal Mail Id']));
        if ($email) {
            $contacts[] = [
                'data_type' => 'Email',
                'contact_type' => 'Primary',
                'contact_detail' => strtolower($email),
                'is_primary' => true,
            ];
        }

        $personalEmail = $this->n($this->getValue($row, ['personal_mail_id', 'Personal Mail Id']));
        if ($personalEmail && strtolower($personalEmail) !== strtolower((string) $email)) {
            $contacts[] = [
                'data_type' => 'Email',
                'contact_type' => 'Alternate',
                'contact_detail' => strtolower($personalEmail),
            ];
        }

        $addresses = [];
        $addr1 = $this->getValue($row, ['address_line_1', 'Address Line 1']);
        if ($addr1 || $this->getValue($row, ['city', 'City'])) {
            $addresses[] = [
                'address_type' => 'Primary',
                'address_line_1' => $addr1,
                'address_line_2' => $this->getValue($row, ['address_line_2', 'Address Line 2']),
                'city' => $this->getValue($row, ['city', 'City']),
                'state' => $this->getValue($row, ['state', 'State']),
                'pincode' => $this->getValue($row, ['pincode', 'Pincode']),
                'country' => 'India',
                'is_primary' => true,
            ];
        }

        $banking = [];
        $bankName = $this->getValue($row, ['bank_name', 'Bank Name']);
        $accountNo = $this->getValue($row, ['account_number', 'Account Number']);
        if ($bankName && $accountNo) {
            $banking[] = [
                'account_type' => 'Primary',
                'bank_name' => $bankName,
                'account_number' => $accountNo,
                'ifsc_code' => $this->getValue($row, ['ifsc_code', 'IFSC Code']),
                'account_holder_name' => $fullName,
                'account_nature' => 'Savings',
                'is_primary' => true,
            ];
        }

        $payload = [
            'person_code' => $personCode,
            'entity_type' => 'individual',
            'display_name' => $fullName,
            'first_name' => null, // PersonService will split from display_name
            'gender' => $this->s($this->getValue($row, ['gender', 'Gender'])),
            'dob' => $this->getValue($row, ['date_of_birth', 'D.O.B.']),
            'marital_status' => $this->s($this->getValue($row, ['marital_status', 'Marital Status'])),
            'pan_no' => $this->n($this->getValue($row, ['pan_no', 'PAN No.'])),
            'aadhaar_no' => $this->n($this->getValue($row, ['aadhaar_no', 'Aadhaar No'])),
            'contacts' => $contacts,
            'addresses' => $addresses,
            'banking' => $banking,
        ];

        $person = PersonService::upsert($payload, [
            'restore' => true,
            'with' => ['contacts', 'addresses', 'bankingDetails'],
        ]);

        $this->logRow($rowIndex, '✅ PERSON', "person_code = {$person->person_code}");
    }

    // ─────────────────────────────────────────────────────────────
    // EMPLOYEE + PRIMARY SCOPES
    // ─────────────────────────────────────────────────────────────

    private function createOrUpdateEmployee(array $row, string $empCode, string $personCode, int $rowIndex): ?string
    {
        $now = Carbon::now();

        $desigCode = $this->resolveDesignationCode(
            $this->getValue($row, ['designation', 'Designation*'])
        );

        $branchCode = $this->resolveOrgCode('branch', $this->getValue($row, ['primary_branch', 'Primary Branch*']));
        $locCode = $this->resolveOrgCode('location', $this->getValue($row, ['primary_location', 'Primary Location*']));
        $deptCode = $this->resolveOrgCode('department', $this->getValue($row, ['primary_department', 'Primary Department*']));
        $divCode = $this->resolveOrgCode('division', $this->getValue($row, ['primary_division', 'Primary Division']));
        $vertical = $this->resolveVerticalCode($this->getValue($row, ['vertical', 'Vertical']));
        $segment = $this->resolveOrgCode('segment', $this->getValue($row, ['segment', 'Segment']));
        $subSegment = $this->resolveOrgCode('sub_segment', $this->getValue($row, ['sub_segment', 'Sub Segment']));

        $reportingManager = $this->parseReportingManager(
            $this->getValue($row, ['reporting_manager', 'Reporting Manager'])
        );

        // employment_type is ENUM NOT NULL — never send empty string
        $empTypeRaw = strtolower((string) ($this->n($this->getValue($row, ['employment_type', 'Employment Type'])) ?? ''));
        $employmentType = match (true) {
            in_array($empTypeRaw, ['permanent', 'probation', 'apprentice', 'contract', 'temporary'], true) => $empTypeRaw,
            default => 'permanent',
        };

        $data = [
            'code' => $empCode,
            'person_code' => $personCode,
            'desig_code' => $desigCode,          // legacy
            'designation_code' => $desigCode,          // preferred
            'primary_branch_code' => $branchCode,
            'primary_loc_code' => $locCode,
            'primary_dept_code' => $deptCode,
            'primary_div_code' => $divCode,
            'vertical_code' => $vertical,
            'segment_code' => $segment,
            'sub_segment_code' => $subSegment,
            'mile_id' => $this->n($this->getValue($row, ['oem_mile_id', 'OEM Mile ID', 'mile_id', 'Mile ID'])),
            'father_name' => $this->n($this->getValue($row, ['father_name', 'Father Name'])),
            'employment_type' => $employmentType,
            'employment_status' => 'active',
            'joining_date' => $this->parseDate($this->getValue($row, ['date_of_joining', 'Date of Joining'])),
            'reporting_manager_code' => $reportingManager,
            'updated_at' => $now,
        ];

        $exists = DB::table('xlr8_admin_employee')->where('code', $empCode)->exists();
        if ($exists) {
            DB::table('xlr8_admin_employee')->where('code', $empCode)->update($data);
        } else {
            $data['created_at'] = $now;
            DB::table('xlr8_admin_employee')->insert($data);
        }

        // NOTE: No emp_*_pivot tables in this schema.
        // Primary values live on employee columns; all scopes (primary + addon) go to xlr8_admin_user_scopes after user is created.

        $this->logRow($rowIndex, '✅ EMPLOYEE', "code = {$empCode} | desig = {$desigCode}");

        return $desigCode;
    }

    // ─────────────────────────────────────────────────────────────
    // USER
    // ─────────────────────────────────────────────────────────────

    private function createOrUpdateUser(array $row, string $empCode, string $personCode, int $rowIndex): int
    {
        $now = Carbon::now();
        $username = strtolower($empCode);
        $desig = strtoupper((string) $this->code($this->getValue($row, ['designation', 'Designation*'])));
        $userType = in_array($desig, ['RTO', 'DSA'], true) ? 'Associate' : 'Emp';

        $mobile = $this->cleanPhone($this->getValue($row, ['personal_contact_number', 'Personal Contact Number*'])) ?? '1234567890';
        $password = Hash::make($mobile);

        $data = [
            'username' => $username,
            'password' => $password,
            'user_type' => $userType,
            'person_code' => $personCode,
            'employee_code' => $empCode,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        $existingId = DB::table('users')->where('username', $username)->value('id');
        if ($existingId) {
            // Do not overwrite password on update
            unset($data['password']);
            DB::table('users')->where('id', $existingId)->update($data);
            $this->logRow($rowIndex, '🔄 USER UPDATED', "username = {$username} | type = {$userType}");

            return (int) $existingId;
        }

        $data['created_at'] = $now;
        $userId = DB::table('users')->insertGetId($data);
        $this->logRow($rowIndex, '✅ USER CREATED', "username = {$username} | type = {$userType}");

        return (int) $userId;
    }

    // ─────────────────────────────────────────────────────────────
    // USER SCOPES  (xlr8_admin_user_scopes) — matches successful DB
    // ─────────────────────────────────────────────────────────────

    private function syncUserScopes(array $row, int $userId, int $rowIndex): void
    {
        if (! $userId) {
            return;
        }

        $fromDate = now()->toDateString();
        $now = now();

        // Map Excel columns → scope_type. Supports primary + addon / multi-value.
        // OrgScopeService::expandCodes handles ALL/ANY → all active codes, and comma-lists.
        $scopeInputs = [
            'branch' => $this->getValue($row, ['primary_branch', 'Primary Branch*', 'branches', 'Branches', 'addon_branches']),
            'location' => $this->getValue($row, ['primary_location', 'Primary Location*', 'locations', 'Locations', 'addon_locations']),
            'department' => $this->getValue($row, ['primary_department', 'Primary Department*', 'departments', 'Departments', 'addon_departments']),
            'division' => $this->getValue($row, ['primary_division', 'Primary Division', 'divisions', 'Divisions', 'addon_divisions']),
            'vertical' => $this->getValue($row, ['vertical', 'Vertical', 'verticals', 'Verticals']),
            'segment' => $this->getValue($row, ['segment', 'Segment', 'segments', 'Segments']),
            'sub_segment' => $this->getValue($row, ['sub_segment', 'Sub Segment', 'sub_segments', 'Sub Segments']),
            'model' => $this->getValue($row, ['model', 'Model', 'models', 'Models']),
            'variant' => $this->getValue($row, ['variant', 'Variant', 'variants', 'Variants']),
        ];

        $inserted = 0;

        foreach ($scopeInputs as $type => $raw) {
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }

            $codes = $this->expandScopeCodes($type, $raw);
            foreach ($codes as $code) {
                if (! $code || strtoupper($code) === 'ALL') {
                    continue;
                }

                $exists = DB::table('xlr8_admin_user_scopes')
                    ->where('user_id', $userId)
                    ->where('scope_type', $type)
                    ->where('scope_code', $code)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    DB::table('xlr8_admin_user_scopes')
                        ->where('user_id', $userId)
                        ->where('scope_type', $type)
                        ->where('scope_code', $code)
                        ->whereNull('deleted_at')
                        ->update([
                            'is_active' => 1,
                            'to_date' => null,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('xlr8_admin_user_scopes')->insert([
                        'user_id' => $userId,
                        'scope_type' => $type,
                        'scope_code' => $code,
                        'is_active' => 1,
                        'from_date' => $fromDate,
                        'to_date' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                }
            }
        }

        $this->logRow($rowIndex, '✅ SCOPES', "user_id={$userId} | new={$inserted}");
    }

    /**
     * Expand scope value → list of codes.
     * Uses OrgScopeService when type is in its hierarchy; falls back for vertical.
     */
    private function expandScopeCodes(string $type, ?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        $upper = strtoupper(trim($raw));

        // Vertical is not in OrgScopeService::$hierarchy — handle directly
        if ($type === 'vertical') {
            if (in_array($upper, ['ALL', 'ANY'], true)) {
                return DB::table('xlr8_admin_vertical')
                    ->where('is_active', 1)
                    ->pluck('code')
                    ->map(fn ($c) => strtoupper($c))
                    ->toArray();
            }

            $parts = array_filter(array_map('trim', explode(',', $raw)));
            $codes = [];
            foreach ($parts as $part) {
                $resolved = $this->resolveVerticalCode($part);
                if ($resolved) {
                    $codes[] = $resolved;
                }
            }

            return array_unique($codes);
        }

        return OrgScopeService::expandCodes($type, $raw);
    }

    // ─────────────────────────────────────────────────────────────
    // PERSON USER TYPE  (xlr8_admin_person_user_types)
    // ─────────────────────────────────────────────────────────────

    private function syncPersonUserType(string $personCode, int $userId, array $row): void
    {
        $desig = strtoupper((string) $this->code($this->getValue($row, ['designation', 'Designation*'])));
        $userType = in_array($desig, ['RTO', 'DSA'], true) ? 'Associate' : 'Emp';
        $now = now();

        $exists = DB::table('xlr8_admin_person_user_types')
            ->where('person_code', $personCode)
            ->where('user_type', $userType)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            DB::table('xlr8_admin_person_user_types')
                ->where('person_code', $personCode)
                ->where('user_type', $userType)
                ->whereNull('deleted_at')
                ->update([
                    'user_id' => $userId,
                    'is_primary' => 1,
                    'is_active' => 1,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('xlr8_admin_person_user_types')->insert([
                'person_code' => $personCode,
                'user_id' => $userId,
                'user_type' => $userType,
                'is_primary' => 1,
                'is_active' => 1,
                'meta' => json_encode(['source' => 'standalone_users_import']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ROLE (Designation IS the Spatie role — config/permission.php maps the
    // 'roles' table to xlr8_admin_designation, so a Designation row already
    // doubles as a Role; this step is what actually creates the
    // xlr8_iam_model_has_roles link so backpack_user()->can(...) checks see it)
    // ─────────────────────────────────────────────────────────────

    private function syncUserRole(int $userId, ?string $desigCode, int $rowIndex): void
    {
        if (! $desigCode) {
            $this->logRow($rowIndex, '⚠️ ROLE SKIPPED', 'no resolved designation code');

            return;
        }

        $role = Role::where('code', $desigCode)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->logRow($rowIndex, '⚠️ ROLE SKIPPED', "no designation/role found for code = {$desigCode}");

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->logRow($rowIndex, '⚠️ ROLE SKIPPED', "user_id = {$userId} not found");

            return;
        }

        $user->syncRoles([$role]);

        $this->logRow($rowIndex, '✅ ROLE', "user_id = {$userId} | role = {$role->name} ({$role->code})");
    }

    // ─────────────────────────────────────────────────────────────
    // RESOLVERS (OrgScopeService + Designation / Vertical fallbacks)
    // ─────────────────────────────────────────────────────────────

    /**
     * Resolve via OrgScopeService for supported hierarchy types.
     * Returns null for ALL/ANY/empty.
     */
    private function resolveOrgCode(string $type, ?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $val = strtoupper(trim($input));
        if (in_array($val, ['ALL', 'ANY', '0', '', 'NULL', 'N/A', '-'], true)) {
            return null;
        }

        // OrgScopeService supports: branch, location, department, division, segment, sub_segment, model, variant
        $resolved = OrgScopeService::resolveCode($type, $input);

        if ($resolved && $resolved !== 'ALL') {
            return $resolved;
        }

        // Fallback: try name LIKE (for slightly dirty Excel values)
        $tableMap = [
            'branch' => ['xlr8_admin_branch', 'code', 'name'],
            'location' => ['xlr8_admin_location', 'code', 'name'],
            'department' => ['xlr8_admin_department', 'code', 'name'],
            'division' => ['xlr8_admin_division', 'code', 'name'],
            'segment' => ['xlr8_vehicle_segment', 'code', 'name'],
            'sub_segment' => ['xlr8_vehicle_subsegment', 'code', 'name'],
        ];

        if (isset($tableMap[$type])) {
            [$table, $codeCol, $nameCol] = $tableMap[$type];
            $code = DB::table($table)
                ->where($nameCol, 'LIKE', '%'.$val.'%')
                ->value($codeCol);

            return $code ? strtoupper($code) : null;
        }

        return null;
    }

    private function resolveDesignationCode(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $val = strtoupper(trim($input));
        if (in_array($val, ['ALL', 'ANY', '0', '', 'NULL', 'N/A', '-'], true)) {
            return null;
        }

        // Exact code
        $exists = DB::table('xlr8_admin_designation')->where('code', $val)->exists();
        if ($exists) {
            return $val;
        }

        // Name match (exact then LIKE)
        $code = DB::table('xlr8_admin_designation')
            ->whereRaw('UPPER(name) = ?', [$val])
            ->value('code');

        if ($code) {
            return strtoupper($code);
        }

        $code = DB::table('xlr8_admin_designation')
            ->where('name', 'LIKE', '%'.$val.'%')
            ->value('code');

        return $code ? strtoupper($code) : null;
    }

    private function resolveVerticalCode(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $val = strtoupper(trim($input));
        if (in_array($val, ['ALL', 'ANY', '0', '', 'NULL', 'N/A', '-'], true)) {
            return null;
        }

        $exists = DB::table('xlr8_admin_vertical')->where('code', $val)->exists();
        if ($exists) {
            return $val;
        }

        $code = DB::table('xlr8_admin_vertical')
            ->whereRaw('UPPER(name) = ?', [$val])
            ->value('code');

        if ($code) {
            return strtoupper($code);
        }

        $code = DB::table('xlr8_admin_vertical')
            ->where('name', 'LIKE', '%'.$val.'%')
            ->value('code');

        return $code ? strtoupper($code) : null;
    }

    /**
     * Parse "Irfan Juneja (BMPL-0014)" → "BMPL-0014"
     */
    private function parseReportingManager(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $value, $m)) {
            return strtoupper(trim($m[1]));
        }

        // Already a code
        if (preg_match('/^BMPL-\d+$/i', trim($value))) {
            return strtoupper(trim($value));
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // PERSON CODE + HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Delegates to Person::deriveCode() - the model-level SSOT (Aadhaar-first,
     * PAN-second, PERS-###### fallback) - instead of reimplementing the
     * priority order and fallback shape independently. See BUG-088 in
     * known-bugs-report.md: this file previously used its own PAN-first order
     * and a non-durable in-memory PRSN##### fallback that disagreed with the
     * model and could collide across separate import runs.
     */
    private function derivePersonCode(array $row): string
    {
        $pan = $this->n($this->getValue($row, ['pan_no', 'PAN No.']));
        $aadhaar = $this->n($this->getValue($row, ['aadhaar_no', 'Aadhaar No']));

        $person = new Person([
            'aadhaar_no' => $aadhaar,
            'pan_no' => $pan,
        ]);

        return Person::deriveCode($person);
    }

    private function getValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function s(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    private function n(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));

        return in_array(strtolower($v), ['', 'null', 'n/a', 'na', '-', '?'], true) ? null : $v;
    }

    private function code(mixed $v, int $max = 0): ?string
    {
        $v = strtoupper(trim((string) ($v ?? '')));
        if (in_array($v, ['', 'NULL', 'N/A', 'NA', '-'], true)) {
            return null;
        }

        return $max > 0 ? substr($v, 0, $max) : $v;
    }

    private function parseDate(mixed $v): ?string
    {
        if (! $v || trim((string) $v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanPhone(?string $v): ?string
    {
        return app(IdentifierService::class)->cleanMobile($v);
    }

    private function logRow(int $rowIndex, string $status, string $msg = ''): void
    {
        $log = "[Row {$rowIndex}] {$status}".($msg ? " | {$msg}" : '');
        echo $log.PHP_EOL;
        Log::info($log);
    }
}
