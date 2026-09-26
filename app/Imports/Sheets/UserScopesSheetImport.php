<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Services\OrgScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * `User_Scopes` sheet of the user & RBAC workbook (DEC-040): one row per user + scope type +
 * value, where the value is `ALL`, a code, a name or an export label `Name (CODE)`.
 *
 * For every user listed here the sheet is authoritative: per scope type the listed codes (plus
 * the employee's primary code) become the active scopes; other active scopes of that type are
 * deactivated with a `to_date`, never deleted. Users not listed keep their scopes. A user with
 * any unresolvable row is skipped entirely, so a typo never removes access.
 */
final class UserScopesSheetImport implements ToCollection, WithHeadingRow
{
    public const SHEET = 'User_Scopes';

    /** Scope type → employee column holding the primary code that must stay in scope. */
    private const PRIMARY_COLUMNS = [
        'branch' => 'primary_branch_code',
        'location' => 'primary_loc_code',
        'department' => 'primary_dept_code',
        'division' => 'primary_div_code',
        'vertical' => 'vertical_code',
        'segment' => 'segment_code',
        'sub_segment' => 'sub_segment_code',
    ];

    /** @var array{users: int, activated: int, inserted: int, deactivated: int, skipped_users: int, failed_rows: int} */
    private array $summary = ['users' => 0, 'activated' => 0, 'inserted' => 0, 'deactivated' => 0, 'skipped_users' => 0, 'failed_rows' => 0];

    /**
     * @return array{users: int, activated: int, inserted: int, deactivated: int, skipped_users: int, failed_rows: int}
     */
    public function summary(): array
    {
        return $this->summary;
    }

    public function collection(Collection $rows): void
    {
        $types = OrgScopeService::types();

        /** @var array<string, array<string, list<string>>> $wanted emp code → type → codes */
        $wanted = [];
        /** @var array<string, true> $invalid emp codes with at least one bad row */
        $invalid = [];

        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;
            $empCode = strtoupper(trim((string) ($row['emp_code'] ?? '')));
            $type = strtolower(trim((string) ($row['scope_type'] ?? '')));
            $value = trim((string) ($row['scope_value'] ?? ''));

            if ($empCode === '' && $type === '' && $value === '') {
                continue;
            }

            $error = match (true) {
                $empCode === '' => 'Emp Code is empty',
                ! in_array($type, $types, true) => "unknown Scope Type '{$type}'",
                $value === '' => 'Scope Value is empty',
                default => null,
            };

            $codes = $error === null ? $this->resolve($type, $value) : [];
            if ($error === null && $codes === []) {
                $error = "'{$value}' is not a valid {$type}";
            }

            if ($error !== null) {
                $this->fail($rowNo, $empCode, $error);
                if ($empCode !== '') {
                    $invalid[$empCode] = true;
                }

                continue;
            }

            $wanted[$empCode][$type] = array_merge($wanted[$empCode][$type] ?? [], $codes);
        }

        foreach ($wanted as $empCode => $byType) {
            if (isset($invalid[$empCode])) {
                $this->summary['skipped_users']++;
                $this->log("⏭️ SCOPES SKIPPED | {$empCode}: fix the failed rows above, nothing was changed for this user");

                continue;
            }

            $employee = DB::table('xlr8_admin_employee')->where('code', $empCode)->first();
            $userId = DB::table('users')->where('employee_code', $empCode)->value('id')
                ?? DB::table('users')->where('username', strtolower($empCode))->value('id');

            if (! $userId) {
                $this->summary['skipped_users']++;
                $this->log("⏭️ SCOPES SKIPPED | {$empCode}: no user with this Emp Code");

                continue;
            }

            DB::transaction(fn () => $this->syncUser((int) $userId, $employee, $byType, $types));
            $this->summary['users']++;
        }

        $this->log('✅ User_Scopes: '.json_encode($this->summary));
    }

    /**
     * @param  array<string, list<string>>  $byType
     * @param  list<string>  $types
     */
    private function syncUser(int $userId, ?object $employee, array $byType, array $types): void
    {
        $today = now()->toDateString();

        foreach ($types as $type) {
            $codes = $byType[$type] ?? [];
            $primary = isset(self::PRIMARY_COLUMNS[$type]) ? ($employee->{self::PRIMARY_COLUMNS[$type]} ?? null) : null;
            if ($primary) {
                $codes[] = strtoupper((string) $primary);
            }
            $codes = array_values(array_unique($codes));

            $existing = DB::table('xlr8_admin_user_scopes')
                ->where('user_id', $userId)
                ->where('scope_type', $type)
                ->get(['id', 'scope_code', 'is_active', 'deleted_at']);

            foreach ($existing as $scope) {
                $keep = in_array(strtoupper((string) $scope->scope_code), $codes, true);
                // The unique key includes soft-deleted rows, so a re-granted code is restored.
                if ($keep && (! $scope->is_active || $scope->deleted_at !== null)) {
                    DB::table('xlr8_admin_user_scopes')->where('id', $scope->id)
                        ->update(['is_active' => 1, 'to_date' => null, 'deleted_at' => null, 'updated_at' => now()]);
                    $this->summary['activated']++;
                } elseif (! $keep && $scope->is_active && $scope->deleted_at === null) {
                    DB::table('xlr8_admin_user_scopes')->where('id', $scope->id)
                        ->update(['is_active' => 0, 'to_date' => $today, 'updated_at' => now()]);
                    $this->summary['deactivated']++;
                }
            }

            $known = $existing->pluck('scope_code')->map(fn ($c) => strtoupper((string) $c))->all();
            foreach (array_diff($codes, $known) as $code) {
                DB::table('xlr8_admin_user_scopes')->insert([
                    'user_id' => $userId,
                    'scope_type' => $type,
                    'scope_code' => $code,
                    'is_active' => 1,
                    'from_date' => $today,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->summary['inserted']++;
            }
        }
    }

    /** @return list<string> */
    private function resolve(string $type, string $value): array
    {
        if (in_array(strtoupper($value), ['ALL', 'ANY'], true)) {
            return OrgScopeService::expandCodes($type, 'ALL');
        }

        $code = OrgScopeService::resolveLabel($type, $value);

        return $code && $code !== 'ALL' ? [$code] : [];
    }

    private function fail(int $rowNo, string $empCode, string $message): void
    {
        $this->summary['failed_rows']++;
        $this->log("[User_Scopes row {$rowNo}] ❌ FAILED | {$empCode}: {$message}");
    }

    private function log(string $line): void
    {
        echo $line.PHP_EOL;
        Log::info($line);
    }
}
