<?php

namespace App\Imports\Sheets;

use App\Imports\Concerns\MasterDataSeeder;
use App\Imports\Concerns\CodeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeeSheetImport implements
    ToCollection,
    WithHeadingRow,
    SkipsEmptyRows
{
    use MasterDataSeeder, CodeGenerator;

    private bool   $dryRun;
    private array  $errors  = [];
    private array  $results = [];
    private int    $skipped = 0;
    private int    $created = 0;
    private int    $updated = 0;

    // ── Heading key map — Excel heading → normalized key ─────────
    // Adjust left-side values to match your EXACT Excel column headings
    // (run: php artisan tinker, then dump the first row keys to verify)

    private const COL = [
        'emp_code'          => ['emp_code', 'employee_code', 'code', 'emp code', 'employee_id'],
        'name'              => ['employee_name', 'name', 'full_name', 'emp_name'],
        'designation'       => ['designation', 'desig', 'designation_name'],
        'department'        => ['department', 'dept', 'department_name'],
        'division'          => ['division', 'sub_department', 'sub_dept', 'division_name'],
        'branch'            => ['branch', 'branch_name', 'location_branch'],
        'work_location'     => ['work_location', 'work_location_name', 'office_location', 'location'],
        'doj'               => ['date_of_joining', 'doj', 'joining_date', 'date_of_join'],
        'dob'               => ['date_of_birth', 'dob', 'birth_date'],
        'gender'            => ['gender', 'sex'],
        'mobile'            => ['mobile', 'mobile_no', 'phone', 'contact_no', 'mobile_number'],
        'email'             => ['email', 'email_id', 'emailid', 'email_address'],
        'employment_type'   => ['employment_type', 'emp_type', 'employee_type'],
        'pan'               => ['pan', 'pan_no', 'pan_number'],
        'aadhaar'           => ['aadhaar', 'aadhaar_no', 'aadhar', 'aadhar_no', 'aadhaar_number'],
        'uan'               => ['uan', 'uan_no', 'uan_number'],
        'pf_number'         => ['pf_number', 'pf_no', 'epf_no'],
        'esi_number'        => ['esi_number', 'esi_no'],
        'biometric_id'      => ['biometric_id', 'biometric_no', 'bio_id'],
        'bank_name'         => ['bank_name', 'bank'],
        'account_number'    => ['account_number', 'account_no', 'bank_account', 'bank_account_no'],
        'ifsc'              => ['ifsc', 'ifsc_code', 'ifsc_no'],
        'father_name'       => ['father_name', "father's_name", 'fathers_name'],
        'blood_group'       => ['blood_group', 'blood'],
        'vertical'          => ['vertical', 'vertical_name'],
        'segment'           => ['segment', 'segment_name'],
        'sub_segment'       => ['sub_segment', 'sub_segment_name', 'subsegment'],
        'reporting_manager' => ['reporting_manager', 'reporting_to', 'manager_code', 'rm_code'],
        'shift'             => ['shift', 'shift_name', 'shift_type'],
        'pf_eligible'       => ['pf_eligible', 'pf', 'pf_applicable'],
        'esi_eligible'      => ['esi_eligible', 'esi', 'esi_applicable'],
    ];

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    // ── Main entry point ─────────────────────────────────────────

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $rawRow) {
            $row = $this->normalize($rawRow->toArray());

            // ── Skip guard: name is the only hard requirement ────
            $name = $row['name'];
            if (empty($name)) {
                $this->skipped++;
                continue;
            }

            // ── Skip guard: looks like a header repeated mid-sheet
            if (strtolower($name) === 'employee name'
                || strtolower($name) === 'name'
                || strtolower($name) === 's.no'
                || is_numeric($name)) {
                $this->skipped++;
                continue;
            }

            try {
                $this->processRow($row, $index + 2); // +2: 1-indexed + header row
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row'     => $index + 2,
                    'name'    => $name,
                    'code'    => $row['emp_code'] ?? 'N/A',
                    'message' => $e->getMessage(),
                ];
                Log::error("Employee import row " . ($index + 2) . " failed: " . $e->getMessage(), [
                    'row_data' => $row,
                ]);
            }
        }
    }

    // ── Process a single valid row ────────────────────────────────

    private function processRow(array $row, int $rowNum): void
    {
        // ── Phase 0: Derive / generate emp_code if missing ───────
        $empCode = $row['emp_code'];
        if (empty($empCode)) {
            // Generate from name — BMPL-XXXX format using last used code + 1
            $empCode = $this->generateEmpCode();
            $this->warn($rowNum, $row['name'], "emp_code missing — auto-generated: {$empCode}");
        }
        $empCode = strtoupper(trim($empCode));

        // ── Phase 0: Org master upserts ───────────────────────────
        $branch = !empty($row['branch'])
            ? $this->upsertBranch($row['branch'])
            : null;

        $dept = !empty($row['department'])
            ? $this->upsertDepartment($row['department'])
            : null;

        $division = (!empty($row['division']) && $dept)
            ? $this->upsertDivision($dept->code, $row['division'])
            : null;

        $desig = !empty($row['designation'])
            ? $this->upsertDesignation($row['designation'])
            : null;

        $workLocation = (!empty($row['work_location']) && $branch)
            ? $this->upsertLocation($row['work_location'], $branch->code)
            : null;

        $vertical = !empty($row['vertical'])
            ? $this->upsertVertical($row['vertical'])
            : null;

        $segment = !empty($row['segment'])
            ? $this->upsertSegment($row['segment'])
            : null;

        $subSegment = (!empty($row['sub_segment']) && $segment)
            ? $this->upsertSubSegment($row['sub_segment'], $segment->code)
            : null;

        // ── Phase 1: Post upsert ──────────────────────────────────
        $post = ($desig && $branch && $dept)
            ? $this->upsertPost(
                $desig->code,
                $branch->code,
                $dept->code,
                $division?->code
            )
            : null;

        // ── Phase 2: Person ───────────────────────────────────────
        $personCode = $this->resolvePersonCode($row);
        [$firstName, $lastName] = $this->splitName($row['name']);

        $personData = [
            'entity_type'  => 'individual',
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'display_name' => trim($row['name']),
            'gender'       => $this->normalizeGender($row['gender'] ?? null),
            'dob'          => $this->parseDate($row['dob'] ?? null),
            'pan_no'       => $row['pan'] ?? null,
            'aadhaar_no'   => $row['aadhaar'] ?? null,
            'father_name'  => $row['father_name'] ?? null,
            'blood_group'  => $this->normalizeBloodGroup($row['blood_group'] ?? null),
            'updated_at'   => now(),
        ];

        if (!$this->dryRun) {
            DB::table('xlr8_admin_person')->updateOrInsert(
                ['person_code' => $personCode],
                array_merge($personData, ['created_at' => now()])
            );
        }

        $personId = $this->dryRun
            ? 0
            : DB::table('xlr8_admin_person')->where('person_code', $personCode)->value('id');

        // ── Phase 3: Employee ─────────────────────────────────────
        $employeeData = [
            'person_id'           => $personId,
            'person_code'         => $personCode,
            'desig_code'          => $desig?->code,
            'primary_branch_code' => $branch?->code,
            'primary_dept_code'   => $dept?->code,
            'division_code'       => $division?->code,
            'primary_work_location_code' => $workLocation?->code,
            'employment_type'     => $this->normalizeEmpType($row['employment_type'] ?? null),
            'employment_status'   => 'active',
            'doj'                 => $this->parseDate($row['doj'] ?? null),
            'uan_number'          => $row['uan'] ?? null,
            'pf_number'           => $row['pf_number'] ?? null,
            'esi_number'          => $row['esi_number'] ?? null,
            'biometric_id'        => $row['biometric_id'] ?? null,
            'pf_eligible'         => $this->parseBool($row['pf_eligible'] ?? null),
            'esi_eligible'        => $this->parseBool($row['esi_eligible'] ?? null),
            'shift_name'          => $row['shift'] ?? null,
            'official_mobile'     => $this->cleanPhone($row['mobile'] ?? null),
            'official_email'      => $row['email'] ?? null,
            'reporting_emp_code'  => $row['reporting_manager'] ?? null, // resolved in Phase 5
            'updated_at'          => now(),
        ];

        if (!$this->dryRun) {
            DB::table('xlr8_admin_employee')->updateOrInsert(
                ['code' => $empCode],
                array_merge($employeeData, ['created_at' => now()])
            );
        }

        // ── Phase 4a: Person Contacts ─────────────────────────────
        if (!$this->dryRun) {
            $mobile = $this->cleanPhone($row['mobile'] ?? null);
            $email  = trim($row['email'] ?? '');

            if ($mobile) {
                DB::table('xlr8_admin_person_contacts')->updateOrInsert(
                    ['person_id' => $personId, 'data_type' => 'Mobile', 'contact_type' => 'Primary'],
                    ['contact_detail' => $mobile, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($email) {
                DB::table('xlr8_admin_person_contacts')->updateOrInsert(
                    ['person_id' => $personId, 'data_type' => 'Email', 'contact_type' => 'Primary'],
                    ['contact_detail' => $email, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        // ── Phase 4b: Banking ─────────────────────────────────────
        if (!$this->dryRun) {
            $bankName  = $row['bank_name'] ?? null;
            $accountNo = $row['account_number'] ?? null;
            $ifsc      = $row['ifsc'] ?? null;

            if ($accountNo && $bankName) {
                DB::table('xlr8_admin_person_banking_details')->updateOrInsert(
                    ['person_id' => $personId, 'account_type' => 'Primary'],
                    [
                        'bank_name'           => $bankName,
                        'account_number'      => $accountNo,
                        'ifsc_code'           => $ifsc,
                        'account_holder_name' => trim($row['name']),
                        'updated_at'          => now(),
                        'created_at'          => now(),
                    ]
                );
            }
        }

        // ── Phase 4c: User account ────────────────────────────────
        if (!$this->dryRun) {
            DB::table('users')->updateOrInsert(
                ['username' => strtolower($empCode)],
                [
                    'user_type'     => 'Emp',
                    'person_code'   => $personCode,
                    'employee_code' => $empCode,
                    'password'      => Hash::make('Welcome@' . substr($empCode, -4)),
                    'is_active'     => 1,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
        }

        // ── Phase 4d: Pivots ──────────────────────────────────────
        if (!$this->dryRun) {
            if ($branch) {
                DB::table('xlr8_admin_emp_branch_pivot')->updateOrInsert(
                    ['employee_code' => $empCode, 'branch_code' => $branch->code],
                    ['assignment_type' => 'primary', 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($dept) {
                DB::table('xlr8_admin_emp_dept_pivot')->updateOrInsert(
                    ['employee_code' => $empCode, 'dept_code' => $dept->code],
                    ['is_primary' => 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($workLocation) {
                DB::table('xlr8_admin_emp_location_pivot')->updateOrInsert(
                    ['employee_code' => $empCode, 'location_code' => $workLocation->code],
                    ['is_primary_work' => 1, 'assignment_type' => 'explicit', 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($vertical) {
                DB::table('xlr8_admin_emp_vertical_pivot')->updateOrInsert(
                    ['employee_code' => $empCode, 'vertical_code' => $vertical->code],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($segment) {
                DB::table('xlr8_admin_emp_segment_pivot')->updateOrInsert(
                    ['employee_code' => $empCode, 'segment_code' => $segment->code],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $this->results[] = [
            'row'       => $rowNum,
            'emp_code'  => $empCode,
            'name'      => trim($row['name']),
            'branch'    => $branch?->code ?? '—',
            'dept'      => $dept?->code ?? '—',
            'desig'     => $desig?->code ?? '—',
            'post'      => $post?->code ?? '—',
            'dry_run'   => $this->dryRun,
        ];

        $this->dryRun ? $this->created++ : ($this->isNew($empCode) ? $this->created++ : $this->updated++);
    }

    // ── Normalize: map any heading variant → our internal key ────

    private function normalize(array $raw): array
    {
        // Build lookup: lowered_heading → value
        $lowered = [];
        foreach ($raw as $k => $v) {
            $lowered[strtolower(trim(str_replace([' ', '-', '/'], '_', $k)))] = $v;
        }

        $out = [];
        foreach (self::COL as $internalKey => $variants) {
            $out[$internalKey] = null;
            foreach ($variants as $variant) {
                $variantKey = strtolower(trim(str_replace([' ', '-', '/'], '_', $variant)));
                if (array_key_exists($variantKey, $lowered)) {
                    $val = $lowered[$variantKey];
                    $out[$internalKey] = is_string($val) ? trim($val) : $val;
                    break;
                }
            }
        }
        return $out;
    }

    // ── Person code: PAN → Aadhaar → emp_code fallback ───────────

    private function resolvePersonCode(array $row): string
    {
        $pan     = strtoupper(trim($row['pan'] ?? ''));
        $aadhaar = trim($row['aadhaar'] ?? '');
        $empCode = strtoupper(trim($row['emp_code'] ?? ''));

        if (!empty($pan) && preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            return $pan;
        }
        if (!empty($aadhaar) && preg_match('/^\d{12}$/', $aadhaar)) {
            return $aadhaar;
        }
        // Fallback: use emp_code as person_code (temporary, HR can update later)
        return 'EMP-' . ltrim($empCode, 'BMPL-');
    }

    // ── Generate next emp code ────────────────────────────────────

    private function generateEmpCode(): string
    {
        $last = DB::table('xlr8_admin_employee')
            ->whereRaw("code REGEXP '^BMPL-[0-9]+$'")
            ->orderByRaw("CAST(SUBSTRING(code, 6) AS UNSIGNED) DESC")
            ->value('code');

        $next = $last ? ((int) substr($last, 5)) + 1 : 1;
        return 'BMPL-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Normalizers ───────────────────────────────────────────────

    private function splitName(string $full): array
    {
        $parts = explode(' ', trim($full), 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function normalizeGender(?string $v): ?string
    {
        if (!$v) return null;
        return match(strtolower(trim($v[0] ?? ''))) {
            'm' => 'male',
            'f' => 'female',
            default => null,
        };
    }

    private function normalizeBloodGroup(?string $v): ?string
    {
        if (!$v) return null;
        $v = strtoupper(trim($v));
        $valid = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
        return in_array($v, $valid) ? $v : null;
    }

    private function normalizeEmpType(?string $v): string
    {
        if (!$v) return 'permanent';
        return match(strtolower(trim($v))) {
            'apprentice', 'appr'         => 'apprentice',
            'contract', 'contractual'    => 'contract',
            'temporary', 'temp'          => 'temporary',
            'probation', 'probationary'  => 'probation',
            default                      => 'permanent',
        };
    }

    private function parseDate($v): ?string
    {
        if (!$v) return null;
        // Handle Excel serial date numbers
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$v)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanPhone(?string $v): ?string
    {
        if (!$v) return null;
        $v = preg_replace('/[^0-9+]/', '', $v);
        // Strip country code if present
        $v = ltrim($v, '+91');
        $v = ltrim($v, '0');
        return strlen($v) === 10 ? $v : null;
    }

    private function parseBool($v): int
    {
        if (!$v) return 0;
        return in_array(strtolower(trim((string)$v)), ['yes', 'y', '1', 'true', 'applicable']) ? 1 : 0;
    }

    private function isNew(string $empCode): bool
    {
        return !DB::table('xlr8_admin_employee')->where('code', $empCode)->exists();
    }

    private function warn(int $row, string $name, string $msg): void
    {
        $this->errors[] = ['row' => $row, 'name' => $name, 'level' => 'warn', 'message' => $msg];
    }

    // ── Public accessors (called by ImportEmployees command) ──────

    public function getResults(): array  { return $this->results; }
    public function getErrors(): array   { return $this->errors; }
    public function getCreated(): int    { return $this->created; }
    public function getUpdated(): int    { return $this->updated; }
    public function getSkipped(): int    { return $this->skipped; }

    // ── Required by WithHeadingRow ────────────────────────────────

    public function headingRow(): int { return 1; }
}
