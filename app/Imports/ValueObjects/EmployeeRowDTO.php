<?php

namespace App\Imports\ValueObjects;

use Carbon\Carbon;

final class EmployeeRowDTO
{
    // Identity
    public readonly string  $empCode;
    public readonly ?string $salutation;
    public readonly string  $firstName;
    public readonly ?string $middleName;
    public readonly string  $lastName;
    public readonly string  $displayName;
    public readonly ?string $gender;
    public readonly ?Carbon $dob;
    public readonly ?string $maritalStatus;
    public readonly ?string $fatherName;
    public readonly ?string $bloodGroup;
    public readonly string  $nationality;

    // Gov IDs
    public readonly ?string $panNo;
    public readonly ?string $aadhaarNo;

    // Contact
    public readonly ?string $mobile;
    public readonly ?string $officialMobile;
    public readonly ?string $email;
    public readonly ?string $officialEmail;

    // Address
    public readonly ?string $addressLine1;
    public readonly ?string $addressLine2;
    public readonly ?string $city;
    public readonly ?string $state;
    public readonly ?string $pincode;

    // Banking
    public readonly ?string $bankName;
    public readonly ?string $accountNumber;
    public readonly ?string $ifscCode;
    public readonly ?string $accountHolderName;

    // Org
    public readonly string  $designation;
    public readonly string  $department;
    public readonly ?string $division;
    public readonly array   $branches;       // ['BIKANER','CHURU'] or ['ANY']
    public readonly array   $verticals;      // ['NEW CAR','USED CAR'] or ['ANY']
    public readonly array   $segments;       // ['LMM','PERSONAL',...] or ['ANY']
    public readonly array   $subSegments;    // ['XUV','NON XUV'] or ['ANY']
    public readonly array   $workLocations;  // physical offices

    // Employment
    public readonly ?Carbon $joiningDate;
    public readonly ?Carbon $confirmationDate;
    public readonly ?Carbon $separationDate;
    public readonly string  $employmentType;
    public readonly string  $status;
    public readonly ?string $reportingManagerCode;

    // Payroll flags
    public readonly bool    $pfEligible;
    public readonly ?string $pfRegType;
    public readonly ?string $pfNumber;
    public readonly ?string $uanNumber;
    public readonly ?Carbon $pfJoiningDate;
    public readonly bool    $epsEligible;
    public readonly bool    $abryEligible;
    public readonly bool    $esiEligible;
    public readonly ?string $esiNumber;
    public readonly ?string $ptEstablishmentId;
    public readonly bool    $lwfEligible;
    public readonly ?string $biometricId;

    // Shift
    public readonly string  $shiftType;
    public readonly ?string $shiftName;
    public readonly ?int    $lateArrivalWindow;
    public readonly ?int    $earlyGoingWindow;
    public readonly ?string $leaveRule;
    public readonly ?string $weekOff;
    public readonly bool    $woWorkCompensation;
    public readonly bool    $compOffApplicable;

    // Salary
    public readonly string  $salaryPaymentMode;
    public readonly string  $salaryStructureType;

    // Misc
    public readonly ?string $oemId;
    public readonly int     $rowNumber;

    public function __construct(array $row, int $rowNumber, array $lookups = [])
    {
        $this->rowNumber = $rowNumber;

        // ── Code ──────────────────────────────────────────────────
        $this->empCode = strtoupper(trim((string) ($row['employee_code'] ?? $row['emp_code'] ?? '')));

        // ── Name ──────────────────────────────────────────────────
        $fullName          = trim((string) ($row['employee_name'] ?? $row['name'] ?? ''));
        $nameParts         = explode(' ', $fullName, 3);
        $this->firstName   = $nameParts[0] ?? $fullName;
        $this->middleName  = count($nameParts) === 3 ? $nameParts[1] : null;
        $this->lastName    = count($nameParts) >= 2 ? end($nameParts) : '';
        $this->displayName = $fullName;
        $this->salutation  = self::cleanStr($row['salutation'] ?? null);

        // ── Personal ──────────────────────────────────────────────
        $this->gender        = self::mapGender($row['gender'] ?? null);
        $this->dob           = self::parseDate($row['date_of_birth'] ?? $row['dob'] ?? null);
        $this->maritalStatus = self::cleanStr($row['marital_status'] ?? null);
        $this->fatherName    = self::cleanStr($row['father_name'] ?? null);
        $this->bloodGroup    = self::cleanStr($row['blood_group'] ?? null);
        $this->nationality   = self::cleanStr($row['nationality'] ?? null) ?? 'Indian';

        // ── Gov IDs ───────────────────────────────────────────────
        $this->panNo     = self::cleanPan($row['pan_no'] ?? $row['pan'] ?? null);
        $this->aadhaarNo = self::cleanAadhaar($row['aadhaar_no'] ?? $row['aadhaar'] ?? null);

        // ── Contact ───────────────────────────────────────────────
        $this->mobile         = self::cleanMobile($row['mobile'] ?? $row['personal_mobile'] ?? null);
        $this->officialMobile = self::cleanMobile($row['official_mobile'] ?? $row['office_mobile'] ?? null);
        $this->email          = self::cleanStr($row['email'] ?? $row['personal_email'] ?? null);
        $this->officialEmail  = self::cleanStr($row['official_email'] ?? $row['office_email'] ?? null);

        // ── Address ───────────────────────────────────────────────
        $this->addressLine1 = self::cleanStr($row['address_line_1'] ?? $row['address'] ?? null);
        $this->addressLine2 = self::cleanStr($row['address_line_2'] ?? null);
        $this->city         = self::cleanStr($row['city'] ?? null);
        $this->state        = self::cleanStr($row['state'] ?? null);
        $this->pincode      = self::cleanStr($row['pincode'] ?? $row['pin_code'] ?? null);

        // ── Banking ───────────────────────────────────────────────
        $this->bankName           = self::cleanStr($row['bank_name'] ?? null);
        $this->accountNumber      = self::cleanStr($row['account_no'] ?? $row['account_number'] ?? null);
        $this->ifscCode           = self::cleanStr($row['ifsc_code'] ?? $row['ifsc'] ?? null);
        $this->accountHolderName  = self::cleanStr($row['account_holder_name'] ?? null);

        // ── Org ───────────────────────────────────────────────────
        $this->designation  = trim((string) ($row['designation'] ?? ''));
        $this->department   = trim((string) ($row['department'] ?? ''));
        $this->division     = self::cleanStr($row['sub_department'] ?? $row['division'] ?? null);

        $this->branches      = self::parseMulti($row['branch'] ?? null);
        $this->verticals     = self::parseMulti($row['vertical'] ?? null);
        $this->segments      = self::parseMulti($row['segment'] ?? null);
        $this->subSegments   = self::parseMulti($row['sub_segment'] ?? null);
        $this->workLocations = self::parseMulti($row['work_location'] ?? $row['office_location'] ?? null);

        // ── Employment ────────────────────────────────────────────
        $this->joiningDate       = self::parseDate($row['date_of_joining'] ?? $row['joining_date'] ?? null);
        $this->confirmationDate  = self::parseDate($row['confirmation_date'] ?? null);
        $this->separationDate    = self::parseDate($row['separation_date'] ?? null);

        $rawType             = strtolower(trim((string) ($row['employment_type'] ?? 'permanent')));
        $this->employmentType = match(true) {
            str_contains($rawType, 'appr') => 'apprentice',
            str_contains($rawType, 'cont') => 'contract',
            str_contains($rawType, 'temp') => 'temporary',
            str_contains($rawType, 'prob') => 'probation',
            default                        => 'permanent',
        };

        $rawStatus    = strtolower(trim((string) ($row['status'] ?? 'active')));
        $this->status = str_contains($rawStatus, 'active') ? 'Active' : 'Inactive';

        $this->reportingManagerCode = self::cleanStr($row['reporting_manager_code'] ?? $row['reporting_manager'] ?? null);

        // ── Payroll ───────────────────────────────────────────────
        $this->pfEligible       = self::parseBool($row['pf_eligible'] ?? null);
        $this->pfRegType        = self::cleanStr($row['pf_reg_type'] ?? null);
        $this->pfNumber         = self::cleanStr($row['pf_number'] ?? null);
        $this->uanNumber        = self::cleanStr($row['uan_number'] ?? null);
        $this->pfJoiningDate    = self::parseDate($row['pf_joining_date'] ?? null);
        $this->epsEligible      = self::parseBool($row['eps_membership'] ?? $row['eps'] ?? null);
        $this->abryEligible     = self::parseBool($row['abry_eligible'] ?? $row['abry'] ?? null);
        $this->esiEligible      = self::parseBool($row['esi_eligible'] ?? null);
        $this->esiNumber        = self::cleanStr($row['esi_number'] ?? null);
        $this->ptEstablishmentId= self::cleanStr($row['pt_establishment_id'] ?? $row['pt_estab_id'] ?? null);
        $this->lwfEligible      = self::parseBool($row['lwf_eligible'] ?? null);
        $this->biometricId      = self::cleanStr($row['biometric_id'] ?? null);

        // ── Shift ─────────────────────────────────────────────────
        $shiftRaw            = strtolower(trim((string) ($row['shift'] ?? $row['shift_name'] ?? 'open')));
        $this->shiftName     = self::cleanStr($row['shift'] ?? $row['shift_name'] ?? null);
        $this->shiftType     = str_contains($shiftRaw, 'open') ? 'flexible' : 'fixed';
        $this->lateArrivalWindow  = isset($row['late_arrival']) ? (int) $row['late_arrival'] : 30;
        $this->earlyGoingWindow   = isset($row['early_going']) ? (int) $row['early_going'] : 15;
        $this->leaveRule     = self::cleanStr($row['leave_rule'] ?? null);
        $this->weekOff       = self::cleanStr($row['week_off'] ?? null) ?? 'Sunday';
        $this->woWorkCompensation = self::parseBool($row['wo_work_compensation'] ?? null);
        $this->compOffApplicable  = self::parseBool($row['comp_off_applicable'] ?? null);

        // ── Salary ────────────────────────────────────────────────
        $payMode = strtolower(trim((string) ($row['salary_payment_mode'] ?? 'bank')));
        $this->salaryPaymentMode    = in_array($payMode, ['bank','cash','cheque']) ? $payMode : 'bank';
        $salaryStruct = strtolower(trim((string) ($row['salary_structure_type'] ?? '')));
        $this->salaryStructureType  = str_contains($salaryStruct, 'above') ? 'above_statutory_limit' : 'statutory_limit';

        // ── Misc ──────────────────────────────────────────────────
        $this->oemId = self::cleanStr($row['oem_id'] ?? $row['oem_employee_id'] ?? null);
    }

    // ── Person code derivation ─────────────────────────────────────────────

    public function derivePersonCode(): string
    {
        if (!empty($this->panNo)) {
            return strtoupper(trim($this->panNo));
        }
        if (!empty($this->aadhaarNo)) {
            return trim($this->aadhaarNo);
        }
        return 'PERS-' . $this->empCode;
    }

    // ── Static helpers ─────────────────────────────────────────────────────

    private static function cleanStr(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = trim((string) $v);
        return $s !== '' ? $s : null;
    }

    private static function cleanPan(mixed $v): ?string
    {
        $s = self::cleanStr($v);
        if (!$s) return null;
        $s = strtoupper(preg_replace('/\s+/', '', $s));
        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $s) ? $s : null;
    }

    /**
     * Excel stores Aadhaar as scientific notation: 6.44695E+11
     * Cast float → int → zero-padded 12-char string.
     */
    public static function cleanAadhaar(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $str = trim((string) $v);
        if (str_contains(strtolower($str), 'e')) {
            $int = (int)(float) $str;
            $str = str_pad((string) $int, 12, '0', STR_PAD_LEFT);
        }
        $digits = preg_replace('/\D/', '', $str);
        return strlen($digits) === 12 ? $digits : null;
    }

    /**
     * Excel stores mobile as float: 9XXXXXXXXX.0
     */
    private static function cleanMobile(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $str = preg_replace('/\D/', '', (string)(str_contains((string)$v, 'e') || is_float($v+0)
            ? (string)(int)(float)$v
            : $v));
        // Strip country code prefix if present
        $str = ltrim($str, '0');
        if (strlen($str) === 12 && str_starts_with($str, '91')) {
            $str = substr($str, 2);
        }
        return strlen($str) === 10 ? $str : null;
    }

    private static function parseDate(mixed $v): ?Carbon
    {
        if (!$v) return null;
        try {
            if (is_int($v) || is_float($v)) {
                return Carbon::createFromTimestamp(($v - 25569) * 86400);
            }
            return Carbon::parse((string) $v);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseBool(mixed $v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower(trim((string) $v)), ['yes','y','1','true','applicable'], true);
    }

    private static function mapGender(mixed $v): ?string
    {
        $g = strtolower(trim((string) ($v ?? '')));
        return match(true) {
            in_array($g, ['m','male'])   => 'Male',
            in_array($g, ['f','female']) => 'Female',
            default                      => null,
        };
    }

    /**
     * Parse comma/slash/semicolon separated values.
     * Returns ['ANY'] for null/blank/any sentinel.
     */
    public static function parseMulti(mixed $v): array
    {
        if ($v === null || trim((string)$v) === '') return ['ANY'];
        $items = preg_split('/[,;\/|]+/', (string)$v);
        $items = array_map('trim', $items);
        $items = array_filter($items);
        $upper = array_map('strtoupper', $items);
        if (empty($items) || in_array('ANY', $upper) || in_array('ALL', $upper)) {
            return ['ANY'];
        }
        return array_values($items);
    }
}
