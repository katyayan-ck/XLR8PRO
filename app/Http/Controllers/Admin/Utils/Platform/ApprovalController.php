<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalTopic;
use App\Services\Platform\Approval\ApprovalService;
use App\Support\Result;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Approval inboxes and the request panel (FRS §7, APR-06/10). Access to one request follows the
 * engine's live visibility (ApprovalService::canSee), not a screen permission.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_APPR_VIEW')) {
            abort(403);
        }
        $box = in_array(strtoupper((string) $request->query('box')), ApprovalService::BOXES, true) ? strtoupper($request->query('box')) : 'TO_ACT';

        return view('admin.utils.platform.approvals.index', [
            'title' => 'Approvals',
            'box' => $box,
            'rows' => $this->approvals->inbox(backpack_user()->id, $box),
            'counts' => $this->approvals->inboxCounts(backpack_user()->id),
        ]);
    }

    public function show(int $id): View
    {
        $request = ApprovalRequest::query()->findOrFail($id);
        abort_unless($this->approvals->canSee($request, backpack_user()->id), 403, 'You cannot see this approval request.');

        return view('admin.utils.platform.approvals.show', ['title' => "Approval #{$id}", 'approval' => $request]);
    }

    /** Raise a request by hand (sources that are not wired yet, and testing). */
    public function create(Request $request): View
    {
        if (! backpack_user()->can('UTL_APPR_REQUEST')) {
            abort(403);
        }

        return view('admin.utils.platform.approvals.create', [
            'title' => 'Raise an approval request',
            'items' => ApprovalTopic::query()->where('is_active', true)->whereNotNull('item_key')->orderBy('code')->get(['id', 'code', 'title', 'item_key', 'value_type']),
            'ref' => ['type' => $request->query('ref_type'), 'id' => $request->query('ref_id')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_APPR_REQUEST')) {
            abort(403);
        }
        $data = $request->validate([
            'item_key' => 'required|string|max:60',
            'asked' => 'required|numeric|min:0',
            'value_type' => 'nullable|in:AMOUNT,PERCENTAGE,FLAG',
            'remark' => 'nullable|string|max:1000',
            'ref_type' => 'nullable|string|max:30',
            'ref_id' => 'nullable|integer',
            'scope' => 'nullable|array',
            'scope.*' => 'nullable|string|max:50',
        ]);
        $source = ! empty($data['ref_type']) && ! empty($data['ref_id']) ? ['type' => $data['ref_type'], 'id' => (int) $data['ref_id']] : null;
        $result = $this->approvals->open($source, $data['item_key'], $data['item_key'], [
            'asked' => $data['asked'], 'value_type' => $data['value_type'] ?? null, 'scope' => array_filter($data['scope'] ?? []), 'remark' => $data['remark'] ?? null,
        ]);
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back()->withInput();
        }
        Alert::success($result->get('auto_accepted') ? 'Within your own power — accepted automatically.' : 'Request raised.')->flash();

        return redirect()->route('utils.approvals.show', $result->get('id'));
    }

    public function counter(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['value' => 'required|numeric|min:0', 'remark' => 'nullable|string|max:1000']);

        return $this->respond($this->approvals->counter($id, backpack_user()->id, $data['value'], $data['remark'] ?? null), 'Counter recorded.');
    }

    public function revise(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['asked' => 'required|numeric|min:0', 'remark' => 'nullable|string|max:1000']);

        return $this->respond($this->approvals->reviseAsk($id, backpack_user()->id, $data['asked'], $data['remark'] ?? null), 'Ask revised.');
    }

    public function close(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['outcome' => 'required|in:ACCEPTED,WITHDRAWN', 'remark' => 'nullable|string|max:1000']);

        return $this->respond($this->approvals->close($id, backpack_user()->id, $data['outcome'], $data['remark'] ?? null), $data['outcome'] === 'ACCEPTED' ? 'Accepted.' : 'Withdrawn.');
    }

    private function respond(Result $result, string $success): RedirectResponse
    {
        $result->ok ? Alert::success($success)->flash() : Alert::error($result->message)->flash();

        return back();
    }
}
