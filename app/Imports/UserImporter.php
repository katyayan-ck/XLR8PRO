<?php

namespace App\Services\Importers;

use App\Models\Admin\Person;
use App\Models\Core\Employee;
use App\Models\User;
use App\Models\UserDataScope;
use App\Models\Admin\Branch;
use App\Models\Core\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Division;
use App\Models\Core\Location;
use App\Models\Core\Post;
use App\Models\Core\Vertical;
use App\Models\Core\UserType;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * UserImporter Service
 * 
 * Handles bulk import of users from Excel files
 * Creates Person → Employee → User hierarchy with all assignments
 * Supports data scoping, pivot assignments, and permission management
 */
class UserImporter
{
    private $filePath;
    private $sheetName = 'Users';
    private $errors = [];
    private $warnings = [];
    private $imported = 0;
    private $skipped = 0;
    private $startRow = 2; // Skip header row

    // Caches for performance
    private $designationCache = [];
    private $departmentCache = [];
    private $branchCache = [];
    private $locationCache = [];
    private $divisionCache = [];
    private $verticalCache = [];
    private $postCache = [];
    private $userTypeCache = [];

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the import process
     */
    public function execute()
    {
        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getSheetByName($this->sheetName);

            // Get header row for column mapping
            $headers = $this->extractHeaders($worksheet);

            // Process each data row
            foreach ($worksheet->getRowIterator($this->startRow) as $row) {
                try {
                    $rowData = $this->extractRowData($row, $headers);

                    if (empty(array_filter($rowData))) {
                        $this->skipped++;
                        continue;
                    }

                    $this->processRow($rowData);
                    $this->imported++;
                } catch (Exception $e) {
                    $this->errors[] = [
                        'row' => $row->getRowIndex(),
                        'error' => $e->getMessage()
                    ];
                    $this->skipped++;
                }
            }

            return $this->getResult();
        } catch (Exception $e) {
            Log::error('User Import Failed', ['error' => $e->getMessage()]);
            throw new Exception("Import failed: " . $e->getMessage());
        }
    }

    /**
     * Extract headers from first row
     */
    private function extractHeaders($worksheet)
    {
        $headers = [];
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = trim($cell->getValue());
            }
        }
        return $headers;
    }

    /**
     * Extract data from a single row
     */
    private function extractRowData($row, $headers)
    {
        $data = [];
        $cellIterator = $row->getCellIterator();
        $colIndex = 0;

        foreach ($cellIterator as $cell) {
            if ($colIndex < count($headers)) {
                $key = $headers[$colIndex];
                $value = $cell->getValue();

                // Handle date values
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d');
                }

                $data[$key] = trim((string)$value);
            }
            $colIndex++;
        }

        return $data;
    }

    /**
     * Process a single row - create Person, Employee, and User
     */
    private function processRow($rowData)
    {
        DB::beginTransaction();

        try {
            // Validate required fields
            $this->validateRequired($rowData);

            // Create or update Person
            $person = $this->createOrUpdatePerson($rowData);

            // Create or update Employee
            $employee = $this->createOrUpdateEmployee($person, $rowData);

            // Create or update User
            $user = $this->createOrUpdateUser($person, $employee, $rowData);

            // Create assignments (Branch, Department, Location, Division, Vertical, Post)
            $this->createAssignments($employee, $rowData);

            // Create user data scopes (for RBAC)
            $this->createDataScopes($user, $rowData);

            // Assign roles and permissions
            $this->assignRolesAndPermissions($user, $employee, $rowData);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate required fields in row
     */
    private function validateRequired($rowData)
    {
        $required = [
            'First Name' => 'Person First Name',
            'Last Name' => 'Person Last Name',
            'Email' => 'Email Address',
            'Employee Code' => 'Employee Code',
            'Designation' => 'Designation',
            'Department' => 'Department',
            'Branch' => 'Branch',
            'Date of Joining' => 'Joining Date',
            'Username' => 'Username',
        ];

        foreach ($required as $key => $label) {
            if (empty($rowData[$key] ?? null)) {
                throw new Exception("Missing required field: $label");
            }
        }
    }

    /**
     * Create or update Person record
     */
    private function createOrUpdatePerson($rowData)
    {
        $email = $rowData['Email'] ?? null;
        $code = $rowData['Person Code'] ?? null;

        $personData = [
            'firstname' => $rowData['First Name'],
            'middlename' => $rowData['Middle Name'] ?? null,
            'lastname' => $rowData['Last Name'],
            'displayname' => trim($rowData['First Name'] . ' ' . ($rowData['Last Name'] ?? '')),
            'gender' => strtolower($rowData['Gender'] ?? 'other'),
            'dob' => $this->parseDate($rowData['Date of Birth'] ?? null),
            'maritalstatus' => $rowData['Marital Status'] ?? null,
            'emailprimary' => $email,
            'mobileprimary' => $rowData['Phone'] ?? null,
        ];

        // Find by email or code, or create new
        $person = Person::where('emailprimary', $email)->first();

        if (!$person) {
            $personData['code'] = $code ?? Person::generateCode();
            $person = Person::create($personData);
        } else {
            $person->update($personData);
        }

        return $person;
    }

    /**
     * Create or update Employee record
     */
    private function createOrUpdateEmployee($person, $rowData)
    {
        $empCode = $rowData['Employee Code'];
        $designation = $this->lookupDesignation($rowData['Designation']);
        $primaryDept = $this->lookupDepartment($rowData['Department']);
        $primaryBranch = $this->lookupBranch($rowData['Branch']);

        $employeeData = [
            'personid' => $person->id,
            'designationid' => $designation->id,
            'primarybranchid' => $primaryBranch->id,
            'primarydepartmentid' => $primaryDept->id,
            'joiningdate' => $this->parseDate($rowData['Date of Joining']),
            'employmenttype' => strtolower($rowData['Employment Type'] ?? 'permanent'),
            'employmentstatus' => $rowData['Employment Status'] ?? 'active',
            'isactive' => true,
        ];

        $employee = Employee::where('code', $empCode)->first();

        if (!$employee) {
            $employeeData['code'] = $empCode;
            $employee = Employee::create($employeeData);
        } else {
            $employee->update($employeeData);
        }

        return $employee;
    }

    /**
     * Create or update User record
     */
    private function createOrUpdateUser($person, $employee, $rowData)
    {
        $email = $rowData['Email Login'] ?? $rowData['Email'];
        $username = $rowData['Username'];
        $userType = $this->lookupUserType($rowData['User Type'] ?? 'Standard User');

        $userData = [
            'personid' => $person->id,
            'employeeid' => $employee->id,
            'usertypeid' => $userType->id,
            'code' => $username,
            'name' => $person->displayname,
            'email' => $email,
            'isactive' => $this->parseBoolean($rowData['User Status'] ?? 'Active'),
        ];

        $user = User::where('email', $email)->first();

        if (!$user) {
            // Generate temporary password
            $userData['password'] = bcrypt('TempPass@' . date('YmdHis'));
            $user = User::create($userData);
        } else {
            // Don't update password if user exists
            unset($userData['password']);
            $user->update($userData);
        }

        return $user;
    }

    /**
     * Create employee assignments (Branch, Department, Location, Division, Vertical, Post)
     */
    private function createAssignments($employee, $rowData)
    {
        $fromDate = $this->parseDate($rowData['Date of Joining']);

        // Branch Assignment
        if (!empty($rowData['Branch'])) {
            $branch = $this->lookupBranch($rowData['Branch']);
            $employee->branches()->syncWithoutDetaching([
                $branch->id => [
                    'fromdate' => $fromDate,
                    'isprimary' => true,
                    'iscurrent' => true,
                ]
            ]);
        }

        // Department Assignment
        if (!empty($rowData['Department'])) {
            $dept = $this->lookupDepartment($rowData['Department']);
            $employee->departments()->syncWithoutDetaching([
                $dept->id => [
                    'fromdate' => $fromDate,
                    'iscurrent' => true,
                ]
            ]);
        }

        // Location Assignment
        if (!empty($rowData['Location'])) {
            $location = $this->lookupLocation($rowData['Location']);
            $branch = $this->lookupBranch($rowData['Branch']);
            $employee->locations()->syncWithoutDetaching([
                $location->id => [
                    'branchid' => $branch->id,
                    'fromdate' => $fromDate,
                    'iscurrent' => true,
                ]
            ]);
        }

        // Division Assignment
        if (!empty($rowData['Division'])) {
            $division = $this->lookupDivision($rowData['Division']);
            $employee->divisions()->syncWithoutDetaching([
                $division->id => [
                    'fromdate' => $fromDate,
                    'iscurrent' => true,
                ]
            ]);
        }

        // Vertical Assignment
        if (!empty($rowData['Vertical'])) {
            $vertical = $this->lookupVertical($rowData['Vertical']);
            $employee->verticals()->syncWithoutDetaching([
                $vertical->id => [
                    'fromdate' => $fromDate,
                    'iscurrent' => true,
                ]
            ]);
        }

        // Post Assignment
        if (!empty($rowData['Post'])) {
            $post = $this->lookupPost($rowData['Post']);
            $employee->posts()->syncWithoutDetaching([
                $post->id => [
                    'fromdate' => $fromDate,
                    'assignmentorder' => 1,
                    'iscurrent' => true,
                ]
            ]);
        }
    }

    /**
     * Create user data scopes for RBAC
     */
    private function createDataScopes($user, $rowData)
    {
        $scopes = [];

        // Branch scope
        if (!empty($rowData['Accessible Branches'])) {
            $branches = array_map('trim', explode(',', $rowData['Accessible Branches']));
            foreach ($branches as $branchCode) {
                $branch = Branch::where('code', $branchCode)->first();
                if ($branch) {
                    $scopes[] = [
                        'userid' => $user->id,
                        'scopetype' => 'branch',
                        'scopevalue' => $branch->id,
                        'status' => 'active',
                    ];
                }
            }
        }

        // Department scope
        if (!empty($rowData['Accessible Departments'])) {
            $departments = array_map('trim', explode(',', $rowData['Accessible Departments']));
            foreach ($departments as $deptCode) {
                $dept = Department::where('code', $deptCode)->first();
                if ($dept) {
                    $scopes[] = [
                        'userid' => $user->id,
                        'scopetype' => 'department',
                        'scopevalue' => $dept->id,
                        'status' => 'active',
                    ];
                }
            }
        }

        // Location scope
        if (!empty($rowData['Accessible Locations'])) {
            $locations = array_map('trim', explode(',', $rowData['Accessible Locations']));
            foreach ($locations as $locCode) {
                $location = Location::where('code', $locCode)->first();
                if ($location) {
                    $scopes[] = [
                        'userid' => $user->id,
                        'scopetype' => 'location',
                        'scopevalue' => $location->id,
                        'status' => 'active',
                    ];
                }
            }
        }

        if (!empty($scopes)) {
            UserDataScope::insert($scopes);
        }
    }

    /**
     * Assign roles and permissions based on post/designation
     */
    private function assignRolesAndPermissions($user, $employee, $rowData)
    {
        // Assign role based on User Type
        $userType = $rowData['User Type'] ?? 'Standard User';
        $roleMap = [
            'Super Admin' => 'superadmin',
            'Foundation Manager' => 'foundationmanager',
            'User Manager' => 'usermanager',
            'Vehicle Manager' => 'vehiclemanager',
            'Standard User' => 'user',
        ];

        $role = $roleMap[$userType] ?? 'user';
        $user->assignRole($role);

        // Assign role-based permissions automatically (via Spatie)
        // The role will automatically have its associated permissions
    }

    /**
     * Lookup helpers with caching
     */
    private function lookupDesignation($code)
    {
        if (!isset($this->designationCache[$code])) {
            $designation = Designation::where('code', $code)->first();
            if (!$designation) {
                throw new Exception("Designation not found: $code");
            }
            $this->designationCache[$code] = $designation;
        }
        return $this->designationCache[$code];
    }

    private function lookupDepartment($code)
    {
        if (!isset($this->departmentCache[$code])) {
            $dept = Department::where('code', $code)->first();
            if (!$dept) {
                throw new Exception("Department not found: $code");
            }
            $this->departmentCache[$code] = $dept;
        }
        return $this->departmentCache[$code];
    }

    private function lookupBranch($code)
    {
        if (!isset($this->branchCache[$code])) {
            $branch = Branch::where('code', $code)->first();
            if (!$branch) {
                throw new Exception("Branch not found: $code");
            }
            $this->branchCache[$code] = $branch;
        }
        return $this->branchCache[$code];
    }

    private function lookupLocation($code)
    {
        if (!isset($this->locationCache[$code])) {
            $location = Location::where('code', $code)->first();
            if (!$location) {
                throw new Exception("Location not found: $code");
            }
            $this->locationCache[$code] = $location;
        }
        return $this->locationCache[$code];
    }

    private function lookupDivision($code)
    {
        if (!isset($this->divisionCache[$code])) {
            $division = Division::where('code', $code)->first();
            if (!$division) {
                throw new Exception("Division not found: $code");
            }
            $this->divisionCache[$code] = $division;
        }
        return $this->divisionCache[$code];
    }

    private function lookupVertical($code)
    {
        if (!isset($this->verticalCache[$code])) {
            $vertical = Vertical::where('code', $code)->first();
            if (!$vertical) {
                throw new Exception("Vertical not found: $code");
            }
            $this->verticalCache[$code] = $vertical;
        }
        return $this->verticalCache[$code];
    }

    private function lookupPost($code)
    {
        if (!isset($this->postCache[$code])) {
            $post = Post::where('code', $code)->first();
            if (!$post) {
                throw new Exception("Post not found: $code");
            }
            $this->postCache[$code] = $post;
        }
        return $this->postCache[$code];
    }

    private function lookupUserType($name)
    {
        if (!isset($this->userTypeCache[$name])) {
            $userType = UserType::where('name', $name)->first();
            if (!$userType) {
                // Create default if not exists
                $userType = UserType::create(['name' => $name]);
            }
            $this->userTypeCache[$name] = $userType;
        }
        return $this->userTypeCache[$name];
    }

    /**
     * Helper: Parse date string to proper format
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateString)->toDateString();
        } catch (Exception $e) {
            try {
                return Carbon::createFromFormat('d-m-Y', $dateString)->toDateString();
            } catch (Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Helper: Parse boolean from string
     */
    private function parseBoolean($value)
    {
        $truthy = ['yes', 'true', '1', 'active', 'on'];
        return in_array(strtolower($value), $truthy);
    }

    /**
     * Get import result
     */
    public function getResult()
    {
        return [
            'success' => count($this->errors) === 0,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'message' => sprintf(
                'Import completed: %d users imported, %d skipped',
                $this->imported,
                $this->skipped
            ),
        ];
    }
}
