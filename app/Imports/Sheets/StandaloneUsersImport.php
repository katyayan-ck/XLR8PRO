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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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
            $userId = $this->createOrUpdateUser($row, $empCode, $personCode, $rowIndex, $desigCode);

            // 4. Scopes → xlr8_admin_user_scopes (primary + expanded ALL)
            $this->syncUserScopes($row, $userId, $rowIndex);

            // 5. person ↔ user_type link
            $this->syncPersonUserType($personCode, $userId, $desigCode);

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
            'dob' => $this->parseDate($this->getValue($row, ['date_of_birth', 'dob', 'D.O.B.'])),
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

        $exists = DB::table('xlr8_admin_employee')->where('code', $empCode)->exists();

        $data = [
            'code' => $empCode,
            'person_code' => $personCode,
            'updated_at' => $now,
        ];

        // An unresolvable designation never erases the stored one (some employees carry codes
        // missing from xlr8_admin_designation, BUG-090); the role step reports the row.
        if ($desigCode !== null || ! $exists) {
            $data['desig_code'] = $desigCode;          // legacy
            $data['designation_code'] = $desigCode;    // preferred
        } else {
            $desigCode = DB::table('xlr8_admin_employee')->where('code', $empCode)->value('designation_code');
        }

        // A column missing from the sheet leaves the stored value untouched; a present but
        // blank cell clears it (DEC-040 — the round-trip export carries every column).
        $optional = [
            'primary_branch_code' => [['primary_branch', 'Primary Branch*'], fn ($v) => $this->resolveOrgCode('branch', $v)],
            'primary_loc_code' => [['primary_location', 'Primary Location*'], fn ($v) => $this->resolveOrgCode('location', $v)],
            'primary_dept_code' => [['primary_department', 'Primary Department*'], fn ($v) => $this->resolveOrgCode('department', $v)],
            'primary_div_code' => [['primary_division', 'Primary Division'], fn ($v) => $this->resolveOrgCode('division', $v)],
            'vertical_code' => [['vertical', 'Vertical'], fn ($v) => $this->resolveVerticalCode($v)],
            'segment_code' => [['segment', 'Segment'], fn ($v) => $this->resolveOrgCode('segment', $v)],
            'sub_segment_code' => [['sub_segment', 'Sub Segment'], fn ($v) => $this->resolveOrgCode('sub_segment', $v)],
            'mile_id' => [['oem_mile_id', 'OEM Mile ID', 'mile_id', 'Mile ID'], fn ($v) => $this->n($v)],
            'father_name' => [['father_name', 'Father Name'], fn ($v) => $this->n($v)],
            'joining_date' => [['date_of_joining', 'Date of Joining'], fn ($v) => $this->parseDate($v)],
            'reporting_manager_code' => [['reporting_manager', 'Reporting Manager'], fn ($v) => $this->parseReportingManager($v)],
        ];
        $lookedUp = ['primary_branch_code', 'primary_loc_code', 'primary_dept_code', 'primary_div_code', 'vertical_code', 'segment_code', 'sub_segment_code'];
        foreach ($optional as $column => [$keys, $resolve]) {
            if (! $this->hasColumn($row, $keys)) {
                continue;
            }
            $raw = $this->getValue($row, $keys);
            $value = $resolve($raw);

            // A value that names no master row (e.g. a stale code) keeps the stored value
            // instead of clearing it; ALL/blank intentionally clear the primary.
            if ($value === null && in_array($column, $lookedUp, true)
                && $this->n($raw) !== null && ! in_array(strtoupper((string) $raw), ['ALL', 'ANY'], true)) {
                $this->logRow($rowIndex, '⚠️ VALUE SKIPPED', "{$empCode}: {$column} '{$raw}' not found".($exists ? ', stored value kept' : ', left empty'));
                if ($exists) {
                    continue;
                }
            }

            $data[$column] = $value;
        }

        // employment_type / employment_status are ENUM NOT NULL: set from a valid value,
        // default only for a new employee, otherwise keep what is stored.
        $empType = strtolower((string) $this->n($this->getValue($row, ['employment_type', 'Employment Type'])));
        if (in_array($empType, ['permanent', 'probation', 'apprentice', 'contract', 'temporary'], true)) {
            $data['employment_type'] = $empType;
        } elseif (! $exists) {
            $data['employment_type'] = 'permanent';
        }

        $empStatus = strtolower((string) $this->n($this->getValue($row, ['employee_status', 'Employee Status'])));
        if (in_array($empStatus, ['active', 'inactive', 'separated', 'terminated', 'absconded'], true)) {
            $data['employment_status'] = $empStatus;
        } elseif (! $exists) {
            $data['employment_status'] = 'active';
        }

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

    private function createOrUpdateUser(array $row, string $empCode, string $personCode, int $rowIndex, ?string $desigCode): int
    {
        $now = Carbon::now();
        $username = strtolower($empCode);
        // Resolved designation code, not the raw cell (an export label is "Name (CODE)").
        $userType = in_array(strtoupper((string) $desigCode), ['RTO', 'DSA'], true) ? 'Associate' : 'Emp';

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

        $loginActive = $this->yesNo($this->getValue($row, ['login_active', 'Login Active']));

        $existingId = DB::table('users')->where('username', $username)->value('id');
        if ($existingId) {
            // Do not overwrite password on update; keep the login flag unless the sheet sets it.
            unset($data['password']);
            $data['is_active'] = $loginActive ?? DB::table('users')->where('id', $existingId)->value('is_active');
            DB::table('users')->where('id', $existingId)->update($data);
            $this->logRow($rowIndex, '🔄 USER UPDATED', "username = {$username} | type = {$userType}");

            return (int) $existingId;
        }

        $data['created_at'] = $now;
        $data['is_active'] = $loginActive ?? 1;
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

        // Excel columns → scope_type. Every listed column adds codes (primary + addon): the old
        // lookup stopped at the first non-empty column and its addon keys never matched the
        // template's slugged headers (`addon_branch`, `add_on_divisions`…), so addons were lost (BUG-163).
        // OrgScopeService::expandCodes handles ALL/ANY → all active codes, and comma-lists.
        $scopeColumns = [
            'branch' => ['primary_branch', 'branches', 'addon_branch', 'addon_branches', 'add_on_branches'],
            'location' => ['primary_location', 'locations', 'addon_location', 'addon_locations', 'add_on_locations'],
            'department' => ['primary_department', 'departments', 'addon_department', 'addon_departments', 'add_on_departments'],
            'division' => ['primary_division', 'divisions', 'addon_division', 'addon_divisions', 'add_on_divisions'],
            'vertical' => ['vertical', 'verticals'],
            'segment' => ['segment', 'segments'],
            'sub_segment' => ['sub_segment', 'sub_segments'],
            'model' => ['model', 'models'],
            'variant' => ['variant', 'variants'],
        ];

        $inserted = 0;

        foreach ($scopeColumns as $type => $keys) {
            $codes = [];
            foreach ($keys as $key) {
                $raw = $this->getValue($row, [$key]);
                if ($raw !== null) {
                    $codes = array_merge($codes, $this->expandScopeCodes($type, $raw));
                }
            }

            foreach (array_unique($codes) as $code) {
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
     * Expand scope value (`ALL`, codes, names or `Name (CODE)` labels, comma-separated) → codes.
     */
    private function expandScopeCodes(string $type, ?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return OrgScopeService::expandCodes($type, $raw);
    }

    // ─────────────────────────────────────────────────────────────
    // PERSON USER TYPE  (xlr8_admin_person_user_types)
    // ─────────────────────────────────────────────────────────────

    private function syncPersonUserType(string $personCode, int $userId, ?string $desigCode): void
    {
        // Resolved designation code, not the raw cell (an export label is "Name (CODE)").
        $userType = in_array(strtoupper((string) $desigCode), ['RTO', 'DSA'], true) ? 'Associate' : 'Emp';
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

        // OrgScopeService supports: branch, location, department, division, vertical, segment, sub_segment, model, variant
        $resolved = OrgScopeService::resolveLabel($type, $input);

        if ($resolved && $resolved !== 'ALL') {
            return $resolved;
        }

        // No partial-name guessing: an unknown value is reported by the caller and never
        // silently mapped to a different master row (DEC-040).
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

        // Export label "Name (CODE)"
        if (preg_match('/\(([^()]+)\)\s*$/', $val, $m)
            && DB::table('xlr8_admin_designation')->where('code', trim($m[1]))->exists()) {
            return trim($m[1]);
        }

        // Exact code
        $exists = DB::table('xlr8_admin_designation')->where('code', $val)->exists();
        if ($exists) {
            return $val;
        }

        // Exact name only: the designation is the user's role, so a partial-name guess could
        // grant another role's permissions (a stale "MAN" matched "Accounts Manager").
        $code = DB::table('xlr8_admin_designation')
            ->whereRaw('UPPER(name) = ?', [$val])
            ->value('code');

        return $code ? strtoupper($code) : null;
    }

    private function resolveVerticalCode(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        return $this->resolveOrgCode('vertical', $input);
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

        // Otherwise the cell is the code itself (any stored format, e.g. "GS-0001").
        return strtoupper(trim($value));
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

    /** True when the sheet has any of these columns (a heading-row key exists even for a blank cell). */
    private function hasColumn(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return true;
            }
        }

        return false;
    }

    private function yesNo(?string $v): ?int
    {
        return match (strtolower(trim((string) $v))) {
            'yes', 'y', '1', 'true', 'active' => 1,
            'no', 'n', '0', 'false', 'inactive' => 0,
            default => null,
        };
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

    private function parseDate(mixed $v): ?string
    {
        if (! $v || trim((string) $v) === '') {
            return null;
        }
        try {
            // An edited Excel date cell arrives as a serial number (e.g. 45567).
            if (is_numeric($v) && (float) $v > 1000 && (float) $v < 100000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->format('Y-m-d');
            }

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
