<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Org\User;

use App\Http\Controllers\Controller;
use App\Services\Org\UsersWorkbook\UsersWorkbookColumns;
use App\Services\Org\UsersWorkbook\UsersWorkbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Org → Users → Bulk edit (DEC-089 Phase C, to-do W11): a grid of every employee user in the users-workbook columns,
 * with dropdowns and filter-like pickers fed from the masters; new rows create users. Saving sends the edited rows to
 * `UsersWorkbookService::saveRows()` → `UserRowService` — the same write path, rules and history as the workbook.
 */
class UserBulkEditController extends Controller
{
    /** Most rows one save may send (the screen sends only edited rows). */
    private const MAX_ROWS = 500;

    public function index(UsersWorkbookService $workbook): View
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to bulk edit users.');
        }

        return view('admin.org.user.bulk', [
            'title' => 'Bulk edit users',
            'headers' => UsersWorkbookColumns::HEADERS,
            'multi' => UsersWorkbookColumns::MULTI,
            'masters' => $workbook->masterPayload(),
        ]);
    }

    /** The grid rows (loaded after the page renders). */
    public function data(UsersWorkbookService $workbook): JsonResponse
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to bulk edit users.');
        }

        return response()->json(['rows' => $workbook->userRows()]);
    }

    /** Save the edited / new rows; per-row results come back in the order sent. */
    public function save(Request $request, UsersWorkbookService $workbook): JsonResponse
    {
        if (! backpack_user()->can('ORG_USER_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to bulk edit users.');
        }

        $request->validate(['rows' => 'required|array|max:'.self::MAX_ROWS, 'rows.*' => 'array'], [], ['rows' => 'rows']);
        $rows = [];
        foreach (array_values((array) $request->input('rows')) as $i => $row) {
            $rows[$i] = array_intersect_key((array) $row, UsersWorkbookColumns::HEADERS);
        }

        return response()->json($workbook->saveRows($rows, (int) backpack_user()->id, 'Grid row'));
    }
}
