<?php

namespace App\Http\Controllers;

//use App\Services\Exporters\UserExporter;
use App\Services\Importers\UserImporter;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserImportExportController extends Controller
{
    /**
     * Show import form
     */
    public function showImportForm()
    {
        return view('admin.users.import');
    }

    /**
     * Handle file upload and import
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            ]);

            // Store uploaded file temporarily
            $file = $request->file('file');
            $filename = 'import_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('imports', $filename, 'local');
            $fullPath = storage_path('app/' . $path);

            // Execute import
            $importer = new UserImporter($fullPath);
            $result = $importer->execute();

            // Clean up temp file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Return result
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Import completed with errors',
                    'data' => $result,
                ], 422);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show export form
     */
    public function showExportForm()
    {
        return view('admin.users.export');
    }

    /**
     * Handle export
     */
    public function export(Request $request)
    {
        try {
            $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'department_id' => 'nullable|exists:departments,id',
                'designation_id' => 'nullable|exists:designations,id',
                'status' => 'nullable|in:active,inactive,all',
            ]);

            // Build filters
            $filters = [];
            if ($request->branch_id) {
                $filters['branch_id'] = $request->branch_id;
            }
            if ($request->department_id) {
                $filters['department_id'] = $request->department_id;
            }
            if ($request->designation_id) {
                $filters['designation_id'] = $request->designation_id;
            }
            if ($request->status && $request->status !== 'all') {
                $filters['is_active'] = ($request->status === 'active');
            }

            // Create exporter
            $exporter = new UserExporter();
            $exporter->withFilters($filters);
            $result = $exporter->execute();

            // Return download
            if ($result['success']) {
                return response()->download($result['path'], $result['filename']);
            } else {
                return back()->with('error', 'Export failed: ' . $result['message']);
            }
        } catch (Exception $e) {
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Download template
     */
    public function downloadTemplate()
    {
        $filename = 'user_import_template.xlsx';
        $path = resource_path('templates/' . $filename);

        if (!file_exists($path)) {
            // Create template if it doesn't exist
            $this->generateTemplate($path);
        }

        return response()->download($path, 'vdms_user_import_template.xlsx');
    }

    /**
     * Generate import template
     */
    private function generateTemplate($path)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
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
            'Location',
            'Division',
            'Vertical',
            'Post',
            'Date of Joining',
            'Employment Type',
            'Employment Status',
            'Username',
            'Email Login',
            'User Type',
            'User Status',
            'Accessible Branches',
            'Accessible Departments',
            'Accessible Locations',
        ];

        // Write headers
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->setFont(new \PhpOffice\PhpSpreadsheet\Style\Font([
                'bold' => true,
                'color' => 'FFFFFF'
            ]));
            $cell->getStyle()->setFill(new \PhpOffice\PhpSpreadsheet\Style\Fill([
                'fillType' => 'solid',
                'startColor' => '366092'
            ]));
        }

        // Add instructions
        $sheet->setCellValue('A' . 3, 'INSTRUCTIONS:');
        $sheet->getStyle('A3')->setFont(new \PhpOffice\PhpSpreadsheet\Style\Font(['bold' => true, 'italic' => true]));

        $instructions = [
            '- Person Code: Auto-generated if left blank',
            '- Date fields: Use DD-MM-YYYY format',
            '- Gender: male, female, other, prefernottosay',
            '- Employment Type: permanent, contract, temporary, probation',
            '- Employment Status: active, inactive, resigned',
            '- User Status: Active or Inactive',
            '- Designation, Department, Branch, etc: Must match existing codes',
            '- Multiple assignments: Separate with commas (e.g., BR001, BR002)',
            '- Leave optional fields blank if not applicable',
            '- Email must be unique per user',
            '- Username must be unique per user',
        ];

        foreach ($instructions as $idx => $instruction) {
            $sheet->setCellValue('A' . (4 + $idx), $instruction);
        }

        // Auto-fit columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        // Save
        @mkdir(dirname($path), 0755, true);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * Get import history
     */
    public function importHistory()
    {
        // Fetch from audit logs or create a dedicated import_logs table
        $imports = \DB::table('import_logs')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.import-history', compact('imports'));
    }

    /**
     * Get export history
     */
    public function exportHistory()
    {
        $exports = \DB::table('export_logs')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.export-history', compact('exports'));
    }
}
