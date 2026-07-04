<?php
namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UsersImportSheet extends BaseSheetImport
{
    protected string $sheetName = 'Users_Import';
    const ASSOCIATE_DESIGNATIONS = ['RTO','DSA'];

    protected function processRow(array $row, int $rowIndex): void
    {
        $empCode = $this->code($row['Emp Code*'] ?? null, 20);
        if (!$empCode) {
            $this->skip("Row {$rowIndex}: missing Emp Code");
            return;
        }

        $personCode = $this->derivePersonCode($row);
        $now = Carbon::now();

        // 1. Person
        $this->createOrUpdatePerson($row, $personCode, $now);

        // 2. Employee
        $this->createOrUpdateEmployee($row, $empCode, $personCode, $now);

        // 3. User
        $this->createOrUpdateUser($row, $empCode, $personCode, $now);

        $this->countInsert();
    }

    private function derivePersonCode(array $row): string
    {
        $pan = $this->n($row['PAN No'] ?? null);
        $aadhaar = $this->n($row['Aadhaar No'] ?? null);
        if ($pan) return strtoupper($pan);
        if ($aadhaar) return strtoupper($aadhaar);

        static $seq = 0;
        return 'PRSN' . str_pad(++$seq, 5, '0', STR_PAD_LEFT);
    }

    private function createOrUpdatePerson(array $row, string $personCode, Carbon $now): void
    {
        $this->upsert('xlr8_admin_person', [
            'person_code'  => $personCode,
            'display_name' => $this->s($row['Employee Name*'] ?? ''),
            'gender'       => $this->s($row['Gender'] ?? null),
            'date_of_birth'=> $this->parseDate($row['Date of Birth'] ?? null),
            'marital_status'=> $this->s($row['Marital Status'] ?? null),
            'father_name'  => $this->s($row['Father Name'] ?? null),
            'pan_no'       => $this->n($row['PAN No'] ?? null),
            'aadhaar_no'   => $this->n($row['Aadhaar No'] ?? null),
            'created_at'   => $now,
            'updated_at'   => $now,
        ], ['person_code' => $personCode]);

        // Primary mobile & email
        $mobile = $this->cleanPhone($row['Personal Contact Number*'] ?? null);
        if ($mobile) {
            $this->upsert('xlr8_admin_person_contacts', [
                'person_code' => $personCode, 'data_type' => 'Mobile', 'contact_type' => 'Primary',
                'contact_detail' => $mobile, 'created_at' => $now, 'updated_at' => $now,
            ], ['person_code' => $personCode, 'data_type' => 'Mobile', 'contact_type' => 'Primary']);
        }

        $email = $this->n($row['Official Mail ID'] ?? $row['Personal Mail Id'] ?? null);
        if ($email) {
            $this->upsert('xlr8_admin_person_contacts', [
                'person_code' => $personCode, 'data_type' => 'Email', 'contact_type' => 'Primary',
                'contact_detail' => $email, 'created_at' => $now, 'updated_at' => $now,
            ], ['person_code' => $personCode, 'data_type' => 'Email', 'contact_type' => 'Primary']);
        }
    }

    private function createOrUpdateEmployee(array $row, string $empCode, string $personCode, Carbon $now): void
    {
        $desigCode  = $this->code($row['Designation*'] ?? null, 10);
        $branchCode = $this->code($row['Primary Branch*'] ?? null, 5);
        $locCode    = $this->code($row['Primary Location*'] ?? null, 10);
        $deptCode   = $this->code($row['Primary Department*'] ?? null, 10);
        $divCode    = $this->code($row['Primary Division'] ?? null, 10);

        $this->upsert('xlr8_admin_employee', [
            'code'                => $empCode,
            'person_code'         => $personCode,
            'desig_code'          => $desigCode,
            'primary_branch_code' => $branchCode,
            'primary_loc_code'    => $locCode,
            'primary_dept_code'   => $deptCode,
            'primary_div_code'    => $divCode,
            'employment_type'     => 'permanent',
            'employment_status'   => 'active',
            'joining_date'        => $this->parseDate($row['Date of Joining'] ?? null),
            'created_at'          => $now,
            'updated_at'          => $now,
        ], ['code' => $empCode]);
    }

    private function createOrUpdateUser(array $row, string $empCode, string $personCode, Carbon $now): void
    {
        $username = strtolower($empCode);
        $desigCode = $this->code($row['Designation*'] ?? null, 10);
        $userType = in_array(strtoupper($desigCode ?? ''), self::ASSOCIATE_DESIGNATIONS) ? 'Associate' : 'Emp';

        $mobile = $this->cleanPhone($row['Personal Contact Number*'] ?? null) ?? '1234567890';
        $password = Hash::make($mobile);

        $this->upsert('users', [
            'username'       => $username,
            'email'          => $this->n($row['Official Mail ID'] ?? null),
            'password'       => $password,
            'person_code'    => $personCode,
            'employee_code'  => $empCode,
            'user_type_code' => $userType,
            'is_active'      => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['username' => $username]);
    }

    private function cleanPhone(?string $v): ?string
    {
        if (!$v) return null;
        $v = preg_replace('/\D/', '', $v);
        $v = ltrim($v, '91'); $v = ltrim($v, '0');
        return strlen($v) === 10 ? $v : null;
    }
}