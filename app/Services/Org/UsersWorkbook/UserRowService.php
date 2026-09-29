<?php

declare(strict_types=1);

namespace App\Services\Org\UsersWorkbook;

use App\Models\Admin\Employee;
use App\Models\IAM\Role;
use App\Models\User;
use App\Services\HR\EmployeeJourneyService;
use App\Services\IAM\UserScopeService;
use App\Services\IAM\UserService;
use App\Services\IdentifierService;
use App\Services\Org\EmployeeService;
use App\Services\PersonService;
use App\Services\PersonUserTypeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One user row of the users workbook or the bulk screen (DEC-089) → person, employee, user, role, scopes and employee
 * history, all-or-nothing in one transaction. The single write path for both (W10 / W11); field rules stay in the
 * entity services (DEC-050 / DEC-054), this class only applies the workbook's cell rules:
 *
 *  - values are master codes (`Name (CODE)` is accepted too); a single-value cell left blank keeps the stored value;
 *  - multi-value cells hold comma-separated codes, `ALL` or `NONE`; blank keeps what is stored (owner, 30-09).
 *    `ALL` = unrestricted, stored as no scope rows (the resolver reads no rows as everything, DEC-071); `NONE` = the
 *    primary only (org add-ons) or no restriction (vehicle levels). Primaries never take `ALL` / `NONE`; vertical never
 *    `NONE`, and a new employee lists vertical codes;
 *  - an org scope type is stored as the primary + its add-ons; add-on locations must be the primary branch's other
 *    locations or locations of the add-on branches (divisions ← departments likewise);
 *  - a value the employee already has (stored or held) is never re-checked, and a cell repeating what is stored changes
 *    nothing — legacy values never block an edit and an unchanged export re-imports as a no-op (DEC-054);
 *  - Aadhaar: a full 12-digit value replaces the stored one; a masked (`XXXXXXXX1234`) or blank value keeps it;
 *  - a new employee or a change of designation, primaries, vertical, manager or scopes writes an employee history row.
 */
final class UserRowService
{
    /** @var array<string, string> scope type => workbook key of its add-on (org) or value (vehicle, vertical) cell */
    private const SCOPE_CELLS = [
        'branch' => 'addon_branch', 'location' => 'addon_location', 'department' => 'addon_department',
        'division' => 'addon_division', 'vertical' => 'vertical', 'segment' => 'segment', 'sub_segment' => 'sub_segment',
        'model' => 'models',
    ];

    /** @var array<string, string> workbook key => employee column (single-value master cells) */
    private const PRIMARY_CELLS = [
        'primary_branch' => 'primary_branch_code', 'primary_location' => 'primary_loc_code',
        'primary_department' => 'primary_dept_code', 'primary_division' => 'primary_div_code',
    ];

    /** @var array<string, string> workbook key => master type */
    private const PRIMARY_TYPES = [
        'primary_branch' => 'branch', 'primary_location' => 'location', 'primary_department' => 'department',
        'primary_division' => 'division',
    ];

    /** @var array<string, list<string>> key => messages for the row being saved */
    private array $errors = [];

    /** @var list<string> notes for the row being saved (e.g. stale add-ons dropped) */
    private array $notes = [];

    private readonly UsersWorkbookMasters $masters;

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly UserService $users,
        private readonly UserScopeService $scopes,
        private readonly EmployeeJourneyService $journey,
        private readonly IdentifierService $identifiers,
        ?UsersWorkbookMasters $masters = null,
    ) {
        $this->masters = $masters ?? new UsersWorkbookMasters;
    }

    public function masters(): UsersWorkbookMasters
    {
        return $this->masters;
    }

    /**
     * Create or update one user from a workbook / screen row (keys: `UsersWorkbookColumns::HEADERS`).
     *
     * @param  array<string, mixed>  $row
     * @return array{status: 'created'|'updated'|'failed', emp_code: string, messages: list<string>}
     */
    public function save(array $row, ?int $actorId = null): array
    {
        $this->errors = [];
        $this->notes = [];
        $empCode = strtoupper($this->text($row['emp_code'] ?? null) ?? '');

        if ($empCode === '') {
            return ['status' => 'failed', 'emp_code' => '', 'messages' => [$this->header('emp_code').': required']];
        }

        $employee = Employee::withTrashed()->where('code', $empCode)->first();
        $isNew = $employee === null;

        try {
            DB::transaction(fn () => $this->write($row, $empCode, $employee, $actorId));
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $key => $list) {
                foreach ((array) $list as $message) {
                    $messages[] = (isset(UsersWorkbookColumns::HEADERS[$key]) ? $this->header($key).': ' : '').$message;
                }
            }

            return ['status' => 'failed', 'emp_code' => $empCode, 'messages' => $messages];
        }

        return ['status' => $isNew ? 'created' : 'updated', 'emp_code' => $empCode, 'messages' => $this->notes];
    }

    /** @param  array<string, mixed>  $row */
    private function write(array $row, string $empCode, ?Employee $employee, ?int $actorId): void
    {
        $isNew = $employee === null;
        $user = $isNew ? null : (User::where('employee_code', $empCode)->first() ?? User::where('username', strtolower($empCode))->first());

        $currentScopes = $user === null ? [] : $user->getAllScopes();
        $employeeData = $this->employeeInput($row, $employee, $isNew, $currentScopes);
        $personData = $this->personInput($row, $employee?->getAttribute('person_code'), $isNew);
        $this->throwIfErrors();
        $personCode = (string) PersonService::upsert($personData, ['with' => []])->getAttribute('person_code');

        $before = $isNew ? [] : $this->snapshot($employee);
        $employeeData['code'] = $empCode;
        $employeeData['person_code'] = $personCode;
        $employee = $isNew
            ? $this->employees->create($employeeData + ['employment_status' => 'active', 'employment_type' => 'permanent', 'joining_date' => now()->toDateString()])
            : $this->employees->update($employee, $employeeData);

        $userType = in_array(strtoupper((string) $employee->designation_code), ['RTO', 'DSA'], true) ? 'Associate' : 'Emp';
        $account = ['user_type' => $userType, 'person_code' => $personCode, 'employee_code' => $empCode];
        if ($user === null) {
            $user = $this->users->create($account + [
                'username' => strtolower($empCode),
                'password' => $this->identifiers->cleanMobile($this->text($row['personal_mobile'] ?? null)) ?? $empCode,
                'is_active' => true,
            ]);
        } else {
            $user = $this->users->update($user, $account);
        }

        $role = Role::where('code', $employee->designation_code)->where('guard_name', 'web')->first();
        if ($role !== null) {
            $user->syncRoles([$role]);
        }
        PersonUserTypeService::assign($personCode, $userType, (int) $user->id, true, ['source' => 'users_workbook']);

        $finalScopes = $this->scopeInput($row, $employee, $before, $currentScopes, $isNew);
        $this->throwIfErrors();
        foreach ($finalScopes as $type => $codes) {
            $this->scopes->sync((int) $user->id, $type, $codes);
        }

        $this->recordHistory($employee, $before, $currentScopes, $finalScopes, $isNew, $actorId);
    }

    /**
     * Person fields: name, contacts and Aadhaar. Blank cells are left out, so they keep what is stored.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function personInput(array $row, ?string $personCode, bool $isNew): array
    {
        $name = $this->text($row['name'] ?? null);
        $mobile = $this->identifiers->cleanMobile($this->text($row['personal_mobile'] ?? null));
        if ($isNew && $name === null) {
            $this->error('name', 'required for a new employee');
        }
        if ($isNew && $mobile === null) {
            $this->error('personal_mobile', 'required for a new employee');
        }

        $contacts = [];
        if ($mobile !== null) {
            $contacts[] = ['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => $mobile];
        }
        $office = $this->identifiers->cleanMobile($this->text($row['official_mobile'] ?? null));
        if ($office !== null && $office !== $mobile) {
            $contacts[] = ['data_type' => 'Mobile', 'contact_type' => 'Office', 'contact_detail' => $office];
        }
        // The official address is the primary e-mail; a personal one is the alternate (as the DEC-040 import).
        $official = $this->text($row['official_email'] ?? null);
        $personal = $this->text($row['personal_email'] ?? null);
        $primaryEmail = $official ?? $personal;
        if ($primaryEmail !== null) {
            $contacts[] = ['data_type' => 'Email', 'contact_type' => 'Primary', 'contact_detail' => strtolower($primaryEmail)];
        }
        if ($official !== null && $personal !== null && strtolower($personal) !== strtolower($official)) {
            $contacts[] = ['data_type' => 'Email', 'contact_type' => 'Alternate', 'contact_detail' => strtolower($personal)];
        }

        $payload = ['person_code' => $personCode, 'entity_type' => 'individual'];
        if ($name !== null) {
            $payload['display_name'] = $name;
        }
        $aadhaar = $this->text($row['aadhaar'] ?? null);
        if ($aadhaar !== null && ! preg_match('/[x*]/i', $aadhaar)) {
            $digits = (string) preg_replace('/[\s-]/', '', $aadhaar);
            preg_match('/^\d{12}$/', $digits)
                ? $payload['aadhaar_no'] = $digits
                : $this->error('aadhaar', 'must be 12 digits (a masked value keeps the stored number)');
        }

        return array_filter($payload, fn ($v) => $v !== null) + ['contacts' => $contacts, 'addresses' => [], 'banking' => []];
    }

    /**
     * Employee fields from the single-value cells (designation, primaries, vertical, manager, Mile ID) and the
     * employee's own segment / sub-segment (set when the cell names exactly one code). A value equal to the stored one
     * is not re-checked (legacy values never block an edit, DEC-054).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, list<string>>  $current  active scope codes before the save
     * @return array<string, mixed>
     */
    private function employeeInput(array $row, ?Employee $employee, bool $isNew, array $current): array
    {
        $data = [];
        $stored = fn (string $column) => strtoupper((string) $employee?->getAttribute($column));

        $designation = $this->code($row['designation'] ?? null);
        if ($designation === null) {
            if ($isNew) {
                $this->error('designation', 'required for a new employee');
            }
        } elseif ($designation !== $stored('designation_code')) {
            $this->masters->exists('designation', $designation)
                ? $data['designation_code'] = $designation
                : $this->error('designation', "{$designation} is not an active designation");
        }

        foreach (self::PRIMARY_CELLS as $key => $column) {
            $code = $this->code($row[$key] ?? null);
            if ($code === null || $code === $stored($column)) {
                continue;   // blank / unchanged keeps; a new employee is checked by EmployeeService (required, same-code default)
            }
            if (in_array($code, [UsersWorkbookColumns::ALL, UsersWorkbookColumns::NONE], true)) {
                $this->error($key, 'ALL / NONE are not allowed for a primary; pick one code');
            } elseif (! $this->masters->exists(self::PRIMARY_TYPES[$key], $code)) {
                $this->error($key, "{$code} is not an active ".str_replace('_', ' ', self::PRIMARY_TYPES[$key]));
            } else {
                $data[$column] = $code;
            }
        }

        // Vertical: required; codes (the stored primary stays when listed, else the first becomes primary) or ALL.
        $heldVerticals = array_values(array_filter([...($current['vertical'] ?? []), $stored('vertical_code')]));
        $verticals = $this->multi('vertical', $row['vertical'] ?? null, $this->masters->codes('vertical'), $heldVerticals);
        if ($verticals === UsersWorkbookColumns::NONE) {
            $this->error('vertical', 'NONE is not allowed: every employee has a vertical');
        } elseif ($verticals === UsersWorkbookColumns::ALL && $isNew) {
            $this->error('vertical', 'list the vertical codes for a new employee (the first is the primary vertical)');
        } elseif (is_array($verticals) && ! in_array($stored('vertical_code'), $verticals, true) && ! $this->sameCodes($verticals, $current['vertical'] ?? [])) {
            $data['vertical_code'] = $verticals[0];
        }

        foreach (['segment' => 'segment_code', 'sub_segment' => 'sub_segment_code'] as $key => $column) {
            $cell = $this->multi($key, $row[$key] ?? null, $this->masters->codes($key), array_filter([...($current[$key] ?? []), $stored($column)]));
            $unchanged = is_array($cell) ? $this->sameCodes($cell, $current[$key] ?? []) : ($cell === UsersWorkbookColumns::ALL && ($current[$key] ?? []) === []);
            if ($cell === null || $unchanged) {
                continue;   // the export writes ALL for no scope rows; that keeps the employee's own code
            }
            $one = is_array($cell) && count($cell) === 1 ? $cell[0] : null;
            if ($one !== $stored($column)) {
                $data[$column] = $one;
            }
        }

        $manager = $this->code($row['reporting_manager'] ?? null);
        if ($manager === UsersWorkbookColumns::NONE) {
            $data['reporting_manager_code'] = null;
        } elseif ($manager !== null && $manager !== $stored('reporting_manager_code')) {
            Employee::where('code', $manager)->exists()
                ? $data['reporting_manager_code'] = $manager
                : $this->error('reporting_manager', "{$manager} is not an employee code");
        }

        $mile = $this->text($row['mile_id'] ?? null);
        if ($mile !== null) {
            $data['mile_id'] = strtoupper($mile) === UsersWorkbookColumns::NONE ? null : $mile;
        }

        return $data;
    }

    /**
     * The scope codes to store per type after this row. Org types: the primary + its add-ons (`ALL` = no rows, i.e.
     * unrestricted); vertical: its codes (`ALL` = no rows); vehicle levels: their codes (none = unrestricted).
     * A cell that repeats what is stored changes nothing.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $before  employee snapshot before the save ([] when new)
     * @param  array<string, list<string>>  $current  active scope codes before the save
     * @return array<string, list<string>>
     */
    private function scopeInput(array $row, Employee $employee, array $before, array $current, bool $isNew): array
    {
        $final = [];

        foreach ([['branch', 'primary_branch_code', 'location', 'primary_loc_code'], ['department', 'primary_dept_code', 'division', 'primary_div_code']] as [$parentType, $parentCol, $childType, $childCol]) {
            $parent = strtoupper((string) $employee->getAttribute($parentCol));
            $child = strtoupper((string) $employee->getAttribute($childCol));
            $oldParent = (string) ($before[$parentCol] ?? '');
            $oldChild = (string) ($before[$childCol] ?? '');

            $parentRows = $this->orgRows($parentType, $row, $current[$parentType] ?? [], $oldParent, $parent,
                array_values(array_diff($this->masters->codes($parentType), [$parent])), $isNew, false);
            $parentsChanged = ! $this->sameCodes($parentRows, $current[$parentType] ?? []) || $oldParent !== $parent;

            // Add-on children: the primary parent's other children + every child of the add-on parents (owner, 30-09);
            // with no parent rows (unrestricted) any child.
            $allowed = $parentRows === []
                ? array_values(array_diff($this->masters->codes($childType), [$child]))
                : array_values(array_unique(array_merge(
                    array_diff($this->masters->childrenOf($childType, $parentType, [$parent]), [$child]),
                    $this->masters->childrenOf($childType, $parentType, array_values(array_diff($parentRows, [$parent]))),
                )));

            $final[$parentType] = $parentRows;
            $final[$childType] = $this->orgRows($childType, $row, $current[$childType] ?? [], $oldChild, $child, $allowed, $isNew,
                $parentsChanged || $oldChild !== $child);
        }

        $verticals = $this->multi('vertical', $row['vertical'] ?? null, $this->masters->codes('vertical'),
            array_values(array_filter([...($current['vertical'] ?? []), strtoupper((string) $employee->getAttribute('vertical_code'))])));
        $final['vertical'] = match (true) {
            is_array($verticals) => $verticals,
            $verticals === UsersWorkbookColumns::ALL => [],
            default => $current['vertical'] ?? [],
        };

        $segments = $this->vehicleRows('segment', $row, $current['segment'] ?? [], $this->masters->codes('segment'), false);
        $segmentsChanged = ! $this->sameCodes($segments, $current['segment'] ?? []);
        $subAllowed = $segments === [] ? $this->masters->codes('sub_segment') : $this->masters->childrenOf('sub_segment', 'segment', $segments);
        $subSegments = $this->vehicleRows('sub_segment', $row, $current['sub_segment'] ?? [], $subAllowed, $segmentsChanged);
        $subChanged = ! $this->sameCodes($subSegments, $current['sub_segment'] ?? []);
        $modelAllowed = $subSegments !== [] ? $this->masters->childrenOf('model', 'sub_segment', $subSegments)
            : ($segments !== [] ? $this->masters->childrenOf('model', 'segment', $segments) : $this->masters->codes('model'));

        $final['segment'] = $segments;
        $final['sub_segment'] = $subSegments;
        $final['model'] = $this->vehicleRows('model', $row, $current['model'] ?? [], $modelAllowed, $segmentsChanged || $subChanged);

        return $final;
    }

    /**
     * One org type's scope rows. Blank keeps the stored rows (the primary swapped when it changed; add-ons no longer
     * under the parents dropped when $dropStale); a new employee gets just the primary. `ALL` = no rows (unrestricted);
     * `NONE` = the primary only; codes = the primary + those add-ons.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $currentRows
     * @param  list<string>  $allowed  add-on codes the column allows
     * @return list<string>
     */
    private function orgRows(string $type, array $row, array $currentRows, string $oldPrimary, string $newPrimary, array $allowed, bool $isNew, bool $dropStale): array
    {
        $key = self::SCOPE_CELLS[$type];
        $cell = $this->multi($key, $row[$key] ?? null, $allowed, $currentRows);
        $oldPrimary = strtoupper($oldPrimary);
        $currentAddons = array_values(array_diff($currentRows, [$oldPrimary, $newPrimary]));

        if ($cell === null) {
            if ($isNew) {
                return $newPrimary === '' ? [] : [$newPrimary];
            }
            if ($currentRows === []) {
                return [];   // unrestricted stays unrestricted
            }
            if ($dropStale) {
                $stale = array_diff($currentAddons, $allowed);
                if ($stale !== []) {
                    $this->notes[] = $this->header($key).': '.implode(', ', $stale).' removed (no longer under the primary / add-on parents)';
                }
                $currentAddons = array_values(array_intersect($currentAddons, $allowed));
            }
            // Users set up on the User screen may hold add-ons without their primary; that stays as it was.
            $keepPrimary = $oldPrimary === '' || in_array($oldPrimary, $currentRows, true) || in_array($newPrimary, $currentRows, true);

            return array_values(array_unique(array_filter([$keepPrimary ? $newPrimary : '', ...$currentAddons])));
        }

        if ($cell === UsersWorkbookColumns::ALL) {
            return [];
        }
        if ($cell === UsersWorkbookColumns::NONE) {
            return $newPrimary === '' ? [] : [$newPrimary];
        }
        if (! $isNew && $oldPrimary === $newPrimary && $this->sameCodes($cell, $currentAddons)) {
            return $currentRows;   // the cell repeats what is stored
        }

        return array_values(array_unique(array_filter([$newPrimary, ...$cell])));
    }

    /**
     * One vehicle level's scope rows: blank keeps the stored codes (dropping those outside the chosen parents when
     * $dropStale); `ALL` / `NONE` = none (unrestricted); codes = those codes.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $currentRows
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function vehicleRows(string $type, array $row, array $currentRows, array $allowed, bool $dropStale): array
    {
        $key = self::SCOPE_CELLS[$type];
        $cell = $this->multi($key, $row[$key] ?? null, $allowed, $currentRows);

        if ($cell === null) {
            if (! $dropStale) {
                return $currentRows;
            }
            $stale = array_diff($currentRows, $allowed);
            if ($stale !== []) {
                $this->notes[] = $this->header($key).': '.implode(', ', $stale).' removed (outside the chosen parents)';
            }

            return array_values(array_intersect($currentRows, $allowed));
        }

        return is_array($cell) ? $cell : [];
    }

    /**
     * Parse a multi-value cell (comma / semicolon / new-line separated, or an array from the screen): null = blank,
     * 'ALL', 'NONE' or the codes. Codes outside $allowed are errors unless the user already holds them ($held — legacy
     * values never block an edit, DEC-054).
     *
     * @param  list<string>  $allowed
     * @param  array<int, string>  $held
     * @return list<string>|'ALL'|'NONE'|null
     */
    private function multi(string $key, mixed $value, array $allowed, array $held = []): array|string|null
    {
        $parts = is_array($value) ? $value : preg_split('/[,;\r\n]+/', (string) $value);
        $codes = [];
        foreach ((array) $parts as $part) {
            $code = $this->code($part);
            if ($code !== null) {
                $codes[] = $code;
            }
        }
        $codes = array_values(array_unique($codes));

        if ($codes === []) {
            return null;
        }
        foreach ([UsersWorkbookColumns::ALL, UsersWorkbookColumns::NONE] as $word) {
            if (in_array($word, $codes, true)) {
                if (count($codes) > 1) {
                    $this->error($key, "{$word} cannot be combined with codes");
                }

                return $word;
            }
        }

        $invalid = array_diff($codes, $allowed, $held);
        if ($invalid !== []) {
            $this->error($key, implode(', ', $invalid).' not allowed here (see the Lists sheet)');
        }

        return array_values(array_diff($codes, $invalid));
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function sameCodes(array $a, array $b): bool
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, list<string>>  $currentScopes
     * @param  array<string, list<string>>  $finalScopes
     */
    private function recordHistory(Employee $employee, array $before, array $currentScopes, array $finalScopes, bool $isNew, ?int $actorId): void
    {
        $after = $this->snapshot($employee);
        $scopesChanged = false;
        foreach ($finalScopes as $type => $codes) {
            $old = $currentScopes[$type] ?? [];
            sort($old);
            sort($codes);
            $scopesChanged = $scopesChanged || $old !== $codes;
        }

        if (! $isNew && $after === $before && ! $scopesChanged) {
            return;
        }

        $reason = match (true) {
            $isNew => 'other',
            ($before['designation_code'] ?? null) !== $after['designation_code'] => 'designation_change',
            ($before['primary_branch_code'] ?? null) !== $after['primary_branch_code']
                || ($before['primary_loc_code'] ?? null) !== $after['primary_loc_code'] => 'transfer',
            $after === $before => 'scope_change',
            default => 'other',
        };

        $this->journey->recordChange(
            employee: $employee,
            primary: $after,
            changeReason: $reason,
            effectiveFrom: $isNew ? ($employee->getAttribute('joining_date') ?? now()) : now(),
            notes: $isNew ? 'Created via the users workbook' : 'Updated via the users workbook',
            addonScopes: $finalScopes,
            actorId: $actorId,
        );
    }

    /** @return array<string, string|null> the employee's history columns */
    private function snapshot(Employee $employee): array
    {
        $out = [];
        foreach (['designation_code', 'primary_branch_code', 'primary_loc_code', 'primary_dept_code', 'primary_div_code', 'vertical_code', 'segment_code', 'sub_segment_code', 'reporting_manager_code'] as $column) {
            $value = $employee->getAttribute($column);
            $out[$column] = $value === null || $value === '' ? null : strtoupper((string) $value);
        }

        return $out;
    }

    /** A master code from a cell: trimmed, upper-case; `Name (CODE)` gives CODE; blank gives null. */
    private function code(mixed $value): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }
        if (preg_match('/\(([^()]+)\)\s*$/', $text, $m)) {
            $text = $m[1];
        }

        return strtoupper(trim($text));
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function error(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    private function throwIfErrors(): void
    {
        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }
    }

    private function header(string $key): string
    {
        return rtrim(UsersWorkbookColumns::HEADERS[$key] ?? $key, '*');
    }
}
