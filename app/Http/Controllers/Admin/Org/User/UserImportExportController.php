<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Org\User;

use App\Http\Controllers\Controller;
use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\UsersImportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Bulk user onboarding (create/update person, employee, user, scopes, role) through the
 * same importer as `php artisan import:users` (DEC-035/036). Export and history were
 * removed: their views never existed and the exporter is broken (BUG-043/158).
 */
class UserImportExportController extends Controller
{
    /** Columns of the Users_Import sheet understood by StandaloneUsersImport (* = mandatory). */
    private const TEMPLATE_HEADERS = [
        'Emp Code*', 'OEM Emp Code', 'Employee Name*', 'Employee Status', 'Personal Mail Id', 'Official Mail Id',
        'Personal Contact Number*', 'Official Contact Number*', 'PAN No.', 'Aadhaar No', 'Designation*',
        'Primary Department*', 'Addon Department', 'Primary Division', 'Add On Divisions', 'Primary Branch*',
        'Addon Branch', 'Primary Location*', 'AddOn Location', 'Vertical', 'Segment', 'Sub Segment', 'Models',
        'Reporting Manager', 'Gender', 'D.O.B.', 'Address Line 1', 'Address Line 2', 'City', 'State', 'Pincode',
        'Bank Name', 'Account Number', 'IFSC Code',
    ];

    public function showImportForm(): View
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        return view('admin.org.user.import', ['result' => null]);
    }

    public function import(Request $request): View
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240'], [], ['file' => 'import file']);

        $path = $request->file('file')->storeAs('imports', 'users_'.Str::random(10).'.xlsx', 'local');
        $fullPath = storage_path('app/private/'.$path);
        if (! is_file($fullPath)) {
            $fullPath = storage_path('app/'.$path);
        }

        $rows = new StandaloneUsersImport;
        $issues = [];
        $error = null;

        ob_start();
        try {
            $sheets = IOFactory::createReaderForFile($fullPath)->listWorksheetNames($fullPath);

            if (in_array(UsersImportWorkbook::SHEET, $sheets, true)) {
                Excel::import(new UsersImportWorkbook($rows), $fullPath);
            } elseif (count($sheets) === 1) {
                Excel::import($rows, $fullPath);
            } else {
                $error = 'The workbook has no '.UsersImportWorkbook::SHEET.' sheet (found: '.implode(', ', $sheets).').';
            }
        } catch (Throwable $e) {
            report($e);
            $error = 'The file could not be imported: '.$e->getMessage();
        } finally {
            $log = (string) ob_get_clean();
            @unlink($fullPath);
        }

        // Row-level problems the importer printed (skipped or failed rows).
        foreach (preg_split('/\R/', $log) as $line) {
            if (str_contains($line, 'SKIPPED') || str_contains($line, 'FAILED')) {
                $issues[] = trim(preg_replace('/[^\PC\s]/u', '', $line));
            }
        }

        return view('admin.org.user.import', [
            'result' => ['summary' => $rows->summary(), 'issues' => array_slice($issues, 0, 200), 'error' => $error],
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(UsersImportWorkbook::SHEET);
        $sheet->fromArray([self::TEMPLATE_HEADERS]);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        foreach (range(1, count(self::TEMPLATE_HEADERS)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $path = storage_path('app/user_import_template.xlsx');
        (new Xlsx($book))->save($path);

        return response()->download($path, 'user_import_template.xlsx')->deleteFileAfterSend();
    }
}
