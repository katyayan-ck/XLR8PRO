<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Org\User;

use App\Exports\UserRbac\UserRbacWorkbookExport;
use App\Http\Controllers\Controller;
use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\Sheets\UserScopesSheetImport;
use App\Imports\UsersImportWorkbook;
use App\Services\IAM\UserRbacExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Bulk user onboarding (create/update person, employee, user, scopes, role) through the
 * same importer as `php artisan import:users` (DEC-035/036), and the users & RBAC workbook
 * (DEC-040) whose Users_Import / User_Scopes sheets import back unchanged. The old export
 * and history screens were removed (BUG-043/158).
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
        $scopes = new UserScopesSheetImport;
        $hasScopes = false;
        $issues = [];
        $error = null;

        ob_start();
        try {
            $sheets = IOFactory::createReaderForFile($fullPath)->listWorksheetNames($fullPath);
            $hasScopes = in_array(UserScopesSheetImport::SHEET, $sheets, true);

            if (in_array(UsersImportWorkbook::SHEET, $sheets, true)) {
                Excel::import(new UsersImportWorkbook($rows, $scopes), $fullPath);
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

    /** Empty workbook with every dropdown, the permission/role reference sheets and instructions. */
    public function downloadTemplate(UserRbacExportService $data): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import users.');
        }

        return Excel::download(new UserRbacWorkbookExport($data, includeUsers: false), 'user_import_template.xlsx');
    }

    /** All users with roles, permissions and scopes; edit and upload back through the import. */
    public function export(UserRbacExportService $data): BinaryFileResponse
    {
        if (! backpack_user()->can('ORG_USER_EXPORT')) {
            abort(403, 'Unauthorized. You do not have permission to export users.');
        }

        return Excel::download(new UserRbacWorkbookExport($data), 'users-rbac-'.now()->format('Ymd-Hi').'.xlsx');
    }
}
