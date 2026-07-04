<?php

namespace App\Services\Exporters;

use App\Models\User;
use App\Models\Core\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * UserExporter Service
 * 
 * Handles bulk export of users to Excel files
 * Exports Person, Employee, User, and all assignment data
 * Includes RBAC and data scoping information
 */
class UserExporter
{
    private $filters = [];
    private $outputPath;

    public function __construct($outputPath = null)
    {
        $this->outputPath = $outputPath ?? storage_path('exports/users_' . date('Y-m-d-His') . '.xlsx');
    }

    /**
     * Set filters for export
     */
    public function withFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Filter by branch
     */
    public function filterByBranch($branchId)
    {
        $this->filters['branch_id'] = $branchId;
        return $this;
    }

    /**
     * Filter by department
     */
    public function filterByDepartment($departmentId)
    {
        $this->filters['department_id'] = $departmentId;
        return $this;
    }

    /**
     * Filter by designation
     */
    public function filterByDesignation($designationId)
    {
        $this->filters['designation_id'] = $designationId;
        return $this;
    }

    /**
     * Filter by status
     */
    public function filterByStatus($isActive)
    {
        $this->filters['is_active'] = $isActive;
        return $this;
    }

    /**
     * Execute the export process
     */
    public function execute()
    {
        try {
            $spreadsheet = new Spreadsheet();

            // Get users based on filters
            $users = $this->getFilteredUsers();

            // Create main users sheet
            $this->createUsersSheet($spreadsheet, $users);

            // Create summary sheet
            $this->createSummarySheet($spreadsheet, $users);

            // Create assignments sheet
            $this->createAssignmentsSheet($spreadsheet, $users);

            // Create data scopes sheet
            $this->createDataScopesSheet($spreadsheet, $users);

            // Save file
            $writer = new Xlsx($spreadsheet);
            $writer->save($this->outputPath);

            return [
                'success' => true,
                'path' => $this->outputPath,
                'filename' => basename($this->outputPath),
                'count' => $users->count(),
                'message' => sprintf('Exported %d users to %s', $users->count(), basename($this->outputPath)),
            ];
        } catch (Exception $e) {
            Log::error('User Export Failed', ['error' => $e->getMessage()]);
            throw new Exception("Export failed: " . $e->getMessage());
        }
    }

    /**
     * Get filtered users
     */
    private function getFilteredUsers()
    {
        $query = User::with([
            'person',
            'employee',
            'employee.designation',
            'employee.primaryBranch',
            'employee.primaryDepartment',
            'userDataScopes',
            'roles'
        ]);

        if (isset($this->filters['branch_id'])) {
            $query->whereHas('employee.branches', function ($q) {
                $q->where('branches.id', $this->filters['branch_id']);
            });
        }

        if (isset($this->filters['department_id'])) {
            $query->whereHas('employee.departments', function ($q) {
                $q->where('departments.id', $this->filters['department_id']);
            });
        }

        if (isset($this->filters['designation_id'])) {
            $query->whereHas('employee', function ($q) {
                $q->where('designationid', $this->filters['designation_id']);
            });
        }

        if (isset($this->filters['is_active'])) {
            $query->where('isactive', $this->filters['is_active']);
        }

        return $query->get();
    }

    /**
     * Create Users sheet
     */
    private function createUsersSheet(Spreadsheet $spreadsheet, $users)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        // Headers
        $headers = [
            'Person Code',
            'First Name',
            'Middle Name',
            'Last Name',
            'Gender',
            'Date of Birth',
            'Marital Status',
            'Email',
            'Phone',
            'Employee Code',
            'Designation',
            'Department',
            'Branch',
            'Date of Joining',
            'Employment Type',
            'Employment Status',
            'Username',
            'User Email',
            'User Type',
            'User Status',
            'Last Login'
        ];

        // Write headers
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->setFont(new Font(['bold' => true, 'color' => 'FFFFFF']));
            $cell->getStyle()->setFill(new Fill(['fillType' => 'solid', 'startColor' => '366092']));
            $cell->getStyle()->setAlignment(new Alignment(['horizontal' => 'center']));
        }

        // Write data
        $row = 2;
        foreach ($users as $user) {
            $person = $user->person;
            $employee = $user->employee;

            $data = [
                $person->code,
                $person->firstname,
                $person->middlename,
                $person->lastname,
                ucfirst($person->gender),
                $person->dob?->format('d-m-Y'),
                $person->maritalstatus,
                $person->emailprimary,
                $person->mobileprimary,
                $employee->code,
                $employee->designation->name,
                $employee->primaryDepartment->name,
                $employee->primaryBranch->name,
                $employee->joiningdate->format('d-m-Y'),
                ucfirst($employee->employmenttype),
                ucfirst($employee->employmentstatus),
                $user->code,
                $user->email,
                $user->userType?->name ?? 'N/A',
                $user->isactive ? 'Active' : 'Inactive',
                $user->lastloginat?->format('d-m-Y H:i'),
            ];

            foreach ($data as $col => $value) {
                $cell = $sheet->getCellByColumnAndRow($col + 1, $row);
                $cell->setValue($value);
                $cell->getStyle()->setAlignment(new Alignment(['horizontal' => 'left', 'vertical' => 'center']));
            }

            $row++;
        }

        // Auto-fit columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }

    /**
     * Create Assignments sheet
     */
    private function createAssignmentsSheet(Spreadsheet $spreadsheet, $users)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Assignments');

        // Headers
        $headers = [
            'Username',
            'Employee Code',
            'Assignment Type',
            'Entity Code',
            'Entity Name',
            'From Date',
            'To Date',
            'Is Current',
            'Additional Info'
        ];

        // Write headers
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->setFont(new Font(['bold' => true, 'color' => 'FFFFFF']));
            $cell->getStyle()->setFill(new Fill(['fillType' => 'solid', 'startColor' => '366092']));
        }

        $row = 2;

        foreach ($users as $user) {
            $employee = $user->employee;

            // Branch assignments
            foreach ($employee->branches as $branch) {
                $pivot = $branch->pivot;
                $data = [
                    $user->code,
                    $employee->code,
                    'Branch',
                    $branch->code,
                    $branch->name,
                    $pivot->fromdate->format('d-m-Y'),
                    $pivot->todate?->format('d-m-Y') ?? 'Current',
                    $pivot->iscurrent ? 'Yes' : 'No',
                    $pivot->isprimary ? 'Primary Branch' : '',
                ];

                foreach ($data as $col => $value) {
                    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
                }
                $row++;
            }

            // Department assignments
            foreach ($employee->departments as $dept) {
                $pivot = $dept->pivot;
                $data = [
                    $user->code,
                    $employee->code,
                    'Department',
                    $dept->code,
                    $dept->name,
                    $pivot->fromdate->format('d-m-Y'),
                    $pivot->todate?->format('d-m-Y') ?? 'Current',
                    $pivot->iscurrent ? 'Yes' : 'No',
                    '',
                ];

                foreach ($data as $col => $value) {
                    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
                }
                $row++;
            }

            // Location assignments
            foreach ($employee->locations as $location) {
                $pivot = $location->pivot;
                $data = [
                    $user->code,
                    $employee->code,
                    'Location',
                    $location->code,
                    $location->name,
                    $pivot->fromdate->format('d-m-Y'),
                    $pivot->todate?->format('d-m-Y') ?? 'Current',
                    $pivot->iscurrent ? 'Yes' : 'No',
                    'Branch: ' . $location->branch->name,
                ];

                foreach ($data as $col => $value) {
                    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
                }
                $row++;
            }

            // Post assignments
            foreach ($employee->posts as $post) {
                $pivot = $post->pivot;
                $data = [
                    $user->code,
                    $employee->code,
                    'Post',
                    $post->code,
                    $post->title,
                    $pivot->fromdate->format('d-m-Y'),
                    $pivot->todate?->format('d-m-Y') ?? 'Current',
                    $pivot->iscurrent ? 'Yes' : 'No',
                    'Order: ' . $pivot->assignmentorder . ' | Remarks: ' . ($pivot->remarks ?? 'N/A'),
                ];

                foreach ($data as $col => $value) {
                    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
                }
                $row++;
            }
        }

        // Auto-fit columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }

    /**
     * Create Data Scopes sheet
     */
    private function createDataScopesSheet(Spreadsheet $spreadsheet, $users)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Scopes');

        // Headers
        $headers = [
            'Username',
            'Employee Code',
            'Scope Type',
            'Scope Value',
            'Entity Name',
            'Status'
        ];

        // Write headers
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->setFont(new Font(['bold' => true, 'color' => 'FFFFFF']));
            $cell->getStyle()->setFill(new Fill(['fillType' => 'solid', 'startColor' => '366092']));
        }

        $row = 2;

        foreach ($users as $user) {
            foreach ($user->userDataScopes as $scope) {
                $entityName = $scope->getDisplayName();
                $data = [
                    $user->code,
                    $user->employee?->code ?? 'N/A',
                    ucfirst($scope->scopetype),
                    $scope->scopevalue ?? 'All (Wildcard)',
                    $entityName,
                    ucfirst($scope->status),
                ];

                foreach ($data as $col => $value) {
                    $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
                }
                $row++;
            }
        }

        // Auto-fit columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
    }

    /**
     * Create Summary sheet with statistics
     */
    private function createSummarySheet(Spreadsheet $spreadsheet, $users)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Summary');

        // Title
        $sheet->setCellValue('A1', 'VDMS User Export Summary');
        $sheet->getStyle('A1')->setFont(new Font(['bold' => true, 'size' => 14]));

        $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('d-m-Y H:i:s'));

        // Statistics
        $row = 4;
        $sheet->setCellValue('A' . $row, 'Total Users');
        $sheet->setCellValue('B' . $row, $users->count());
        $row++;

        $activeCount = $users->where('isactive', true)->count();
        $sheet->setCellValue('A' . $row, 'Active Users');
        $sheet->setCellValue('B' . $row, $activeCount);
        $row++;

        $inactiveCount = $users->where('isactive', false)->count();
        $sheet->setCellValue('A' . $row, 'Inactive Users');
        $sheet->setCellValue('B' . $row, $inactiveCount);
        $row += 2;

        // By Branch
        $sheet->setCellValue('A' . $row, 'Distribution by Branch');
        $sheet->getStyle('A' . $row)->setFont(new Font(['bold' => true, 'size' => 11]));
        $row++;

        $branchData = $users->groupBy(function ($user) {
            return $user->employee->primaryBranch->name;
        })->map->count();

        foreach ($branchData as $branch => $count) {
            $sheet->setCellValue('A' . $row, $branch);
            $sheet->setCellValue('B' . $row, $count);
            $row++;
        }

        // Auto-fit columns
        $sheet->getColumnDimensionByColumn(1)->setAutoSize(true);
        $sheet->getColumnDimensionByColumn(2)->setAutoSize(true);
    }

    /**
     * Get the export file path
     */
    public function getPath()
    {
        return $this->outputPath;
    }

    /**
     * Download the export file
     */
    public function download()
    {
        $this->execute();
        return response()->download($this->outputPath);
    }
}
