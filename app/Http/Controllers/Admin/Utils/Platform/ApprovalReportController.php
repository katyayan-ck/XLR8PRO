<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Exports\Platform\RowsExport;
use App\Http\Controllers\Controller;
use App\Services\OrgService;
use App\Services\Platform\Approval\ApprovalReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Topic-wise approval report (FRS §8.4): opened / accepted / withdrawn, granted vs asked, counters
 * per level, auto share, time to close; xlsx export of the request rows.
 */
class ApprovalReportController extends Controller
{
    public function __construct(private readonly ApprovalReportService $reports) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_APPR_REPORT')) {
            abort(403);
        }
        $filters = $this->filters($request);
        $groupBy = (string) $request->query('group', 'topic_code');

        return view('admin.utils.platform.approvals.report', [
            'title' => 'Approval report',
            'filters' => $filters,
            'groupBy' => $groupBy,
            'summary' => $this->reports->summary($filters, $groupBy),
            'branches' => OrgService::branches(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        if (! backpack_user()->can('UTL_APPR_REPORT')) {
            abort(403);
        }
        $rows = $this->reports->rows($this->filters($request));
        $headings = ['ID', 'FY', 'Branch', 'Topic', 'Item', 'Source', 'Requester', 'Value type', 'Asked', 'Granted', 'Winning level', 'Status', 'Auto', 'Revisions', 'Opened', 'Closed', 'Hours to close'];

        return Excel::download(new RowsExport($headings, $rows, 'Approvals'), 'approvals-'.now()->format('Ymd-Hi').'.xlsx');
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'fy' => 'nullable|string|max:5',
            'branch' => 'nullable|string|max:20',
            'topic' => 'nullable|string|max:100',
            'item' => 'nullable|string|max:60',
            'level' => 'nullable|integer',
            'actor' => 'nullable|integer',
            'source' => 'nullable|string|max:30',
        ]), fn ($v) => $v !== null && $v !== '');
    }
}
