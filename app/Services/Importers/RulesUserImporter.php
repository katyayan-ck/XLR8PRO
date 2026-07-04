<?php

namespace App\Services\Importers;

use Illuminate\Support\Facades\DB;
use App\Models\Admin\Branch;
use App\Models\Core\Location;
use App\Models\Core\Department;
use App\Models\Admin\Division;
use App\Models\Core\Vertical;
use App\Models\Core\Segment;
use App\Models\Core\SubSegment;
use App\Models\Core\VehicleModel;
use App\Models\Admin\Designation;
use App\Models\Admin\Person;
use App\Models\Core\Employee;
use App\Models\User;
use App\Models\UserDataScope;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use Exception;

class RulesUserImporter
{
    private $filePath;
    private $sheetName = 'Rules';
    private $startRow = 2;
    private $imported = 0;
    private $updated = 0;
    private $skipped = 0;
    private $errors = [];
    private $warnings = [];
    private $existingUsers = [];
    private $importedUserIds = [];
    private $mobileSequence = 9000000001;

    public function __construct($filePath = null, $sheetName = null)
    {
        $this->filePath = $filePath;

        if ($sheetName) {
            $this->sheetName = $sheetName;
        }

        // Pre-load existing users for update detection by User.code (NOT Emp Code)
        $this->existingUsers = User::pluck('id', 'code')->toArray();
    }

    /**
     * Execute the import process
     */
    public function execute()
    {
        $startTime = microtime(true);

        try {
            $spreadsheet = IOFactory::load($this->filePath);

            // Check if sheet exists
            if (!$spreadsheet->sheetNameExists($this->sheetName)) {
                return $this->formatResult(false, "Sheet '{$this->sheetName}' not found");
            }

            $worksheet = $spreadsheet->getSheetByName($this->sheetName);
            $maxRow = $worksheet->getHighestRow();

            DB::beginTransaction();

            // Process each row
            for ($row = $this->startRow; $row <= $maxRow; $row++) {
                try {
                    $data = $this->extractRowData($worksheet, $row);

                    if (empty($data['Emp Code'])) {
                        $this->skipped++;
                        continue;
                    }

                    $this->processUserRow($data, $row);
                } catch (Exception $e) {
                    $this->errors[] = [
                        'row' => $row,
                        'error' => $e->getMessage(),
                    ];
                    $this->skipped++;
                }
            }

            DB::commit();
            $duration = round(microtime(true) - $startTime, 2);

            return $this->formatResult(
                true,
                "Import completed successfully. Imported: {$this->imported}, Updated: {$this->updated}, Skipped: {$this->skipped}",
                $duration
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->formatResult(false, "Import failed: " . $e->getMessage());
        }
    }

    /**
     * Extract data from a single row
     */
    private function extractRowData($worksheet, $row)
    {
        return [
            'S.No' => $worksheet->getCell("A{$row}")->getValue(),
            'Name' => $worksheet->getCell("B{$row}")->getValue(),
            'Email' => $worksheet->getCell("C{$row}")->getValue(),
            'Mobile' => $worksheet->getCell("D{$row}")->getValue(),
            'Mile ID' => $worksheet->getCell("E{$row}")->getValue(),
            'Emp Code' => $worksheet->getCell("F{$row}")->getValue(),
            'Designation' => $worksheet->getCell("G{$row}")->getValue(),
            'Department' => $worksheet->getCell("H{$row}")->getValue(),
            'Sub Department' => $worksheet->getCell("I{$row}")->getValue(),
            'Location' => $worksheet->getCell("J{$row}")->getValue(),
            'Branch' => $worksheet->getCell("K{$row}")->getValue(),
            'Vertical' => $worksheet->getCell("L{$row}")->getValue(),
            'Segment' => $worksheet->getCell("M{$row}")->getValue(),
            'Sub Segment' => $worksheet->getCell("N{$row}")->getValue(),
            'Models' => $worksheet->getCell("O{$row}")->getValue(),
            'User ID' => $worksheet->getCell("P{$row}")->getValue(),
            'Password' => $worksheet->getCell("Q{$row}")->getValue(),
        ];
    }

    /**
     * Process a complete user row
     * ✅ FIXED: Now correctly detects existing users by User.code instead of Emp Code
     */
    private function processUserRow($data, $row)
    {
        // Parse name
        $nameParts = $this->parseName($data['Name']);
        $firstName = $nameParts['first_name'];
        $lastName = $nameParts['last_name'];

        // Generate/get email
        $email = $this->generateEmail($data['Email'], $firstName, $data['Emp Code']);

        // Generate/get mobile
        $mobile = $this->generateMobile($data['Mobile']);

        // Get or create Person
        $person = $this->getOrCreatePerson([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email_primary' => $email,
            'mobile_primary' => $mobile,
        ]);

        // Lookup required entities
        $designation = $this->lookupDesignation($data['Designation']);
        $department = $this->lookupDepartment($data['Department']);

        if (!$designation || !$department) {
            throw new Exception("Designation or Department not found");
        }

        // Get or create Employee
        $employee = $this->getOrCreateEmployee([
            'person_id' => $person->id,
            'code' => $data['Emp Code'],
            'designation_id' => $designation->id,
            'primary_department_id' => $department->id,
            'joining_date' => now()->toDateString(),
        ]);

        // ✅ FIXED: Generate userId FIRST, then check if user exists by User.code
        $userId = $data['User ID'] ?? $this->generateUserId($data['Emp Code']);
        $isNewUser = !isset($this->existingUsers[$userId]);

        // ✅ FIXED: Only generate password for new users (don't overwrite on update)
        $password = $isNewUser ? $this->generatePassword($data['Password'] ?? $data['Emp Code']) : null;

        // Get or create User
        $user = $this->getOrCreateUser([
            'person_id' => $person->id,
            'employee_id' => $employee->id,
            'code' => $userId,
            'email' => $email,
            'password' => $password,
            'name' => "{$firstName} {$lastName}",
            'mobile' => $mobile,  // ✅ FIXED: Now passing mobile to method
        ]);

        // Track imported user
        $this->importedUserIds[] = $user->id;

        if ($isNewUser) {
            $this->imported++;
        } else {
            $this->updated++;
        }

        // Clear old assignments if updating
        if (!$isNewUser) {
            $employee->branches()->detach();
            $employee->departments()->detach();
            $employee->locations()->detach();
            $employee->divisions()->detach();
            $employee->verticals()->detach();
            $employee->posts()->detach();
            $user->userDataScopes()->delete();
        }

        // Process multi-value assignments
        $this->processAssignments($employee, $user, $data);
    }

    /**
     * Process all assignments for user
     */
    private function processAssignments($employee, $user, $data)
    {
        // Branch assignments (with Location scoping)
        $branches = $this->parseMultiValue($data['Branch']);
        foreach ($branches as $branchCode) {
            $branch = Branch::where('code', $branchCode)->first();
            if ($branch) {
                $employee->branches()->attach($branch->id, [
                    'from_date' => now(),
                    'is_primary' => count($branches) === 1,
                    'is_current' => true,
                ]);

                // Create data scope for branch (NULL location = all locations)
                $location = $this->lookupLocation($data['Location']);
                $this->createDataScope($user, 'branch', $location ? null : $branch->id, 'branch');
            }
        }

        // Location assignments if specified
        if (!empty($data['Location'])) {
            $locations = $this->parseMultiValue($data['Location']);
            foreach ($locations as $locationCode) {
                $location = Location::where('code', $locationCode)->first();
                if ($location) {
                    $employee->locations()->attach($location->id, [
                        'from_date' => now(),
                        'is_current' => true,
                    ]);

                    // Create data scope for location
                    $this->createDataScope($user, 'location', $location->id);
                }
            }
        }

        // Department assignments (with Division scoping)
        $departments = $this->parseMultiValue($data['Department']);
        foreach ($departments as $deptCode) {
            $dept = Department::where('code', $deptCode)->first();
            if ($dept) {
                $employee->departments()->attach($dept->id, [
                    'from_date' => now(),
                    'is_current' => true,
                ]);

                // Create data scope for department (NULL division = all divisions)
                $division = $this->lookupDivision($data['Sub Department']);
                $this->createDataScope($user, 'department', $division ? null : $dept->id, 'department');
            }
        }

        // Division (Sub Department) assignments if specified
        if (!empty($data['Sub Department'])) {
            $divisions = $this->parseMultiValue($data['Sub Department']);
            foreach ($divisions as $divCode) {
                $division = Division::where('code', $divCode)->first();
                if ($division) {
                    $employee->divisions()->attach($division->id, [
                        'from_date' => now(),
                        'is_current' => true,
                    ]);

                    // Create data scope for division
                    $this->createDataScope($user, 'division', $division->id);
                }
            }
        }

        // Vertical assignments
        if (!empty($data['Vertical'])) {
            $verticals = $this->parseMultiValue($data['Vertical']);
            foreach ($verticals as $vertCode) {
                $vertical = Vertical::where('code', $vertCode)->first();
                if ($vertical) {
                    $employee->verticals()->attach($vertical->id, [
                        'from_date' => now(),
                        'is_current' => true,
                    ]);
                    $this->createDataScope($user, 'vertical', $vertical->id);
                }
            }
        }

        // Brand/Segment/SubSegment/VehicleModel/Variant/Variant_Color hierarchy
        if (!empty($data['Segment'])) {
            $segments = $this->parseMultiValue($data['Segment']);
            foreach ($segments as $segCode) {
                $segment = Segment::where('code', $segCode)->first();
                if ($segment) {
                    $this->createDataScope($user, 'segment', $segment->id);

                    // If SubSegment specified, scope to that; else scope to all subsegments
                    if (!empty($data['Sub Segment'])) {
                        $subSegments = $this->parseMultiValue($data['Sub Segment']);
                        foreach ($subSegments as $subSegCode) {
                            $subSegment = SubSegment::where('code', $subSegCode)->first();
                            if ($subSegment) {
                                $this->createDataScope($user, 'subsegment', $subSegment->id);

                                // If Models specified, scope to those; else scope to all
                                if (!empty($data['Models'])) {
                                    $models = $this->parseMultiValue($data['Models']);
                                    foreach ($models as $modelCode) {
                                        $model = VehicleModel::where('code', $modelCode)->first();
                                        if ($model) {
                                            $this->createDataScope($user, 'vehiclemodel', $model->id);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // If no SubSegment, scope to all vehicles under segment
                        $allModels = VehicleModel::whereHas('subSegment', function ($q) use ($segment) {
                            $q->where('segment_id', $segment->id);
                        })->get();

                        foreach ($allModels as $model) {
                            $this->createDataScope($user, 'vehiclemodel', $model->id);
                        }
                    }
                }
            }
        }
    }

    /**
     * Parse comma-separated values
     */
    private function parseMultiValue($value)
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $value)),
            fn($v) => !empty($v)
        );
    }

    /**
     * Parse name into first and last
     */
    private function parseName($name)
    {
        $parts = array_map('trim', explode(' ', trim($name), 2));

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? $parts[0] ?? '',
        ];
    }

    /**
     * Generate email if missing
     */
    private function generateEmail($email, $firstName, $empCode)
    {
        if (!empty($email)) {
            return $email;
        }

        $slug = Str::slug("{$firstName}.{$empCode}", '.');

        return "{$slug}@bmpl.com";
    }

    /**
     * Generate mobile if missing
     */
    private function generateMobile($mobile)
    {
        if (!empty($mobile)) {
            return $mobile;
        }

        $generated = $this->mobileSequence;
        $this->mobileSequence++;

        return (string)$generated;
    }

    /**
     * Generate user ID from employee code
     */
    private function generateUserId($empCode)
    {
        return str_replace([' ', '-'], '', $empCode);
    }

    /**
     * Generate password
     */
    private function generatePassword($empCode)
    {
        $sanitized = str_replace([' ', '-', 'BMPL'], '', $empCode);

        return "user@bmpl#{$sanitized}";
    }

    /**
     * Get or create Person
     */
    private function getOrCreatePerson($data)
    {
        return Person::updateOrCreate(
            ['email_primary' => $data['email_primary']],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'mobile_primary' => $data['mobile_primary'],
                'display_name' => "{$data['first_name']} {$data['last_name']}",
            ]
        );
    }

    /**
     * Get or create Employee
     */
    private function getOrCreateEmployee($data)
    {
        return Employee::updateOrCreate(
            ['code' => $data['code']],
            [
                'person_id' => $data['person_id'],
                'designation_id' => $data['designation_id'],
                'primary_department_id' => $data['primary_department_id'],
                'joining_date' => $data['joining_date'],
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create User
     * ✅ FIXED: Only sets password on creation, never overwrites existing password on update
     * ✅ FIXED: Now includes mobile field in updateData
     */
    private function getOrCreateUser($data)
    {
        // Prepare update data - conditionally include password
        $updateData = [
            'person_id' => $data['person_id'],
            'employee_id' => $data['employee_id'],
            'email' => $data['email'],
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,  // ✅ FIXED: Save mobile field
            'is_active' => true,
        ];

        // Only include password if provided (new user only)
        if (!empty($data['password'])) {
            $updateData['password'] = bcrypt($data['password']);
        }

        return User::updateOrCreate(
            ['code' => $data['code']],
            $updateData
        );
    }

    /**
     * Create data scope for RBAC
     */
    private function createDataScope($user, $scopeType, $scopeValue, $contextType = null)
    {
        UserDataScope::create([
            'user_id' => $user->id,
            'scope_type' => $scopeType,
            'scope_value' => $scopeValue, // NULL means all children
            'status' => 'active',
        ]);
    }

    /**
     * Lookup helpers
     */
    private function lookupDesignation($code)
    {
        return Designation::where('code', $code)
            ->orWhere('name', 'LIKE', "%{$code}%")
            ->first();
    }

    private function lookupDepartment($code)
    {
        return Department::where('code', $code)
            ->orWhere('name', 'LIKE', "%{$code}%")
            ->first();
    }

    private function lookupDivision($code)
    {
        if (empty($code)) return null;

        return Division::where('code', $code)
            ->orWhere('name', 'LIKE', "%{$code}%")
            ->first();
    }

    private function lookupLocation($code)
    {
        if (empty($code)) return null;

        return Location::where('code', $code)
            ->orWhere('name', 'LIKE', "%{$code}%")
            ->first();
    }

    /**
     * Format result
     */
    private function formatResult($success, $message, $duration = 0)
    {
        return [
            'success' => $success,
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'total_processed' => $this->imported + $this->updated + $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'message' => $message,
            'duration_seconds' => $duration,
        ];
    }
}
