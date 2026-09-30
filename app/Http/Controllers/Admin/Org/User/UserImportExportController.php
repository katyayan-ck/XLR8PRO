<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Org\User;

use App\Exports\UserRbac\UserRbacWorkbookExport;
use App\Http\Controllers\Controller;
use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\Sheets\UserScopesSheetImport;
use App\Imports\UsersImportWorkbook;
use App\Services\IAM\UserRbacExportService;
use App\Services\Org\UsersWorkbook\UsersWorkbookColumns;
use App\Services\Org\UsersWorkbook\UsersWorkbookService;
use App\Support\ErrorRef;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Bulk user onboarding (create/update person, employee, user, scopes, role) through the
 * users workbook (DEC-089: fixed headers, master dropdowns, `UsersWorkbookService`). The users & RBAC
 * workbook (DEC-040) stays as an audit export; its Users_Import / User_Scopes sheets (and one-sheet
 * legacy files) still import through `import:users`' importer during the change-over.
 */
class UserImportExportController extends Controller
{
    public function showImportForm(): View
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        return view('admin.org.user.import', ['result' => null]);
    }

    public function import(Request $request, UsersWorkbookService $workbook): View
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
        $scopes = new UserScopesSheetImport;
        $hasScopes = false;
        $issues = [];
        $error = null;

        ob_start();
        try {
            $sheets = IOFactory::createReaderForFile($fullPath)->listWorksheetNames($fullPath);
            $hasScopes = in_array(UserScopesSheetImport::SHEET, $sheets, true);

            if (in_array(UsersWorkbookColumns::SHEET, $sheets, true)) {
                $new = $workbook->import($fullPath, (int) backpack_user()->id);
            } elseif (in_array(UsersImportWorkbook::SHEET, $sheets, true)) {
                Excel::import(new UsersImportWorkbook($rows, $scopes), $fullPath);
            } elseif (count($sheets) === 1) {
                Excel::import($rows, $fullPath);
            } else {
                $error = 'The workbook has no '.UsersImportWorkbook::SHEET.' sheet (found: '.implode(', ', $sheets).').';
            }
        } catch (Throwable $e) {
            report($e);
            $error = 'The file could not be imported: '.ErrorRef::userMessage($e);
        } finally {
            $log = (string) ob_get_clean();
            @unlink($fullPath);
        }

        if (isset($new)) {
            return view('admin.org.user.import', ['result' => [
                'summary' => $new['summary'], 'scopes' => null, 'issues' => array_slice($new['issues'], 0, 300), 'error' => $error,
            ]]);
        }

        // Row-level problems the importers printed (skipped or failed rows, values not found).
        foreach (preg_split('/\R/', $log) as $line) {
            if (str_contains($line, 'SKIPPED') || str_contains($line, 'FAILED')) {
                $issues[] = trim(preg_replace('/[^\PC\s]/u', '', $line));
            }
        }

        return view('admin.org.user.import', [
            'result' => [
                'summary' => $rows->summary(),
                'scopes' => $hasScopes ? $scopes->summary() : null,
                'issues' => array_slice($issues, 0, 300),
                'error' => $error,
            ],
        ]);
    }

    /** The empty users workbook: headers, dropdowns, Lists and Instructions (DEC-089). */
    public function downloadTemplate(UsersWorkbookService $workbook): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        return $this->workbookDownload($workbook, false, 'users-template.xlsx');
    }

    /** Every employee user in the users workbook; edit and upload back through the import (DEC-089). */
    public function export(UsersWorkbookService $workbook): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        return $this->workbookDownload($workbook, true, 'users-'.now()->format('Ymd-Hi').'.xlsx');
    }

    /** All users with roles, permissions and scopes (DEC-040 workbook, kept for audit; still importable). */
    public function exportRbac(UserRbacExportService $data): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        return Excel::download(new UserRbacWorkbookExport($data), 'users-rbac-'.now()->format('Ymd-Hi').'.xlsx');
    }

    private function workbookDownload(UsersWorkbookService $workbook, bool $withUsers, string $name): BinaryFileResponse
    {
        $path = storage_path('app/users-workbook-'.Str::random(12).'.xlsx');
        $workbook->export($path, $withUsers);

        return response()->download($path, $name)->deleteFileAfterSend();
    }
}
