<?php

namespace App\Http\Controllers\Admin\Org\User;

use App\Http\Controllers\Controller;
use App\Services\Exporters\UserExporter;
use App\Services\Importers\UserImporter;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * No "users.import"/"users.export" permissions existed in xlr8_iam_permissions
 * before this change. Minted 2 new permissions following the app's
 * `resource.action` convention — kept distinct from `users.create` since bulk
 * import is a materially higher-risk operation than creating one user.
 *
 * See known-bugs-report.md BUG-041/BUG-042: this controller's routes
 * (routes/web.php) previously used ['auth', 'verified'] middleware instead of
 * the 'admin' group every other admin route uses — bypassing the emergency
 * CheckIfAdmin gate entirely — and export() called `new UserExporter` without
 * importing it, resolving to a nonexistent class in this namespace. Both
 * fixed as part of this change.
 */
class UserImportExportController extends Controller
{
    public function showImportForm()
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        return view('admin.users.import');
    }

    public function import(Request $request)
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            $file = $request->file('file');
            $filename = 'import_'.Str::random(10).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('imports', $filename, 'local');
            $fullPath = storage_path('app/'.$path);

            $importer = new UserImporter($fullPath);
            $result = $importer->execute();

            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

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
                'message' => 'Import failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showExportForm()
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        return view('admin.users.export');
    }

    public function export(Request $request)
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        try {
            $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'department_id' => 'nullable|exists:departments,id',
                'designation_id' => 'nullable|exists:designations,id',
                'status' => 'nullable|in:active,inactive,all',
            ]);

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

            $exporter = new UserExporter;
            $exporter->withFilters($filters);
            $result = $exporter->execute();

            if ($result['success']) {
                return response()->download($result['path'], $result['filename']);
            } else {
                return back()->with('error', 'Export failed: '.$result['message']);
            }
        } catch (Exception $e) {
            return back()->with('error', 'Export failed: '.$e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        $filename = 'user_import_template.xlsx';
        $path = resource_path('templates/'.$filename);

        if (! file_exists($path)) {
            $this->generateTemplate($path);
        }

        return response()->download($path, 'vdms_user_import_template.xlsx');
    }

    private function generateTemplate($path)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        $headers = [
            'Person Code',
            'First Name',
            'Middle Name',
            'Last Name',
            'Gender',
            'D.O.B.',
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

        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);
            $cell->getStyle()->setFont(new Font([
                'bold' => true,
                'color' => 'FFFFFF',
            ]));
            $cell->getStyle()->setFill(new Fill([
                'fillType' => 'solid',
                'startColor' => '366092',
            ]));
        }

        $sheet->setCellValue('A'. 3, 'INSTRUCTIONS:');
        $sheet->getStyle('A3')->setFont(new Font(['bold' => true, 'italic' => true]));

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
            $sheet->setCellValue('A'.(4 + $idx), $instruction);
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        @mkdir(dirname($path), 0755, true);
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    }

    public function importHistory()
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        $imports = \DB::table('import_logs')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.import-history', compact('imports'));
    }

    public function exportHistory()
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        $exports = \DB::table('export_logs')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.export-history', compact('exports'));
    }
}
