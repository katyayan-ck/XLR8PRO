<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\KeywordValueService;
use App\Services\OrgService;
use App\Services\Platform\Help\SupportRequestService;
use App\Services\Platform\Ticket\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Ticket screens (FRS §6): inboxes + desk queue, open, view with transitions and desk management,
 * report. Access to a single ticket follows its people or the desk / report permission.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_TCKT_VIEW')) {
            abort(403);
        }
        $box = array_key_exists(strtoupper((string) $request->query('box')), TicketService::BOXES) ? strtoupper($request->query('box')) : 'REQUESTED';
        if ($box === 'QUEUE' && ! backpack_user()->can('UTL_TCKT_DESK')) {
            abort(403);
        }
        $filters = $request->only(['status', 'category', 'q']);

        return view('admin.utils.platform.tickets.index', [
            'title' => 'Tickets',
            'box' => $box,
            'filters' => $filters,
            'rows' => $this->tickets->inbox(backpack_user()->id, $box, $filters),
            'counts' => $this->tickets->inboxCounts(backpack_user()->id),
            'isDesk' => backpack_user()->can('UTL_TCKT_DESK'),
            'categories' => KeywordValueService::getEnum('TICKET_CATEGORY'),
        ]);
    }

    public function create(Request $request): View
    {
        if (! backpack_user()->can('UTL_TCKT_CREATE')) {
            abort(403);
        }

        return view('admin.utils.platform.tickets.create', [
            'title' => 'New ticket',
            'categories' => KeywordValueService::getEnum('TICKET_CATEGORY'),
            'priorities' => KeywordValueService::getEnum('TICKET_PRIORITY'),
            'ref' => ['type' => $request->query('ref_type'), 'id' => $request->query('ref_id')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_TCKT_CREATE')) {
            abort(403);
        }
        $data = $request->validate([
            'title' => 'required|string|max:250',
            'category' => 'required|string|max:50',
            'priority' => 'required|string|max:5',
            'details' => 'nullable|string|max:20000',
            'ref_type' => 'nullable|string|max:30',
            'ref_id' => 'nullable|integer',
            'file' => 'nullable|file',
        ]);
        $result = $this->tickets->open($data);
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back()->withInput();
        }
        if ($request->hasFile('file')) {
            $this->tickets->remark((int) $result->get('id'), backpack_user()->id, 'Attachment', $request->file('file'));
        }
        Alert::success(__('utils.flash.ticket_opened', ['number' => $result->get('number')]))->flash();

        return redirect()->route('utils.tickets.show', $result->get('id'));
    }

    public function show(int $id): View
    {
        $result = $this->tickets->get($id, backpack_user()->id);
        abort_unless($result->ok, 403, 'You cannot see this ticket.');
        $model = Ticket::query()->findOrFail($id);
        $team = in_array('manage', $result->get('user_can'), true) ? OrgService::teamOptions() : [];
        // Support tickets (DEC-094): diagnostics for the support team only; assignees are support executives only
        $support = app(SupportRequestService::class);
        $supportRequest = $support->canViewDiagnostics($model, backpack_user()) ? $support->forTicket($model) : null;
        $isSupport = str_starts_with((string) $model->category, 'SUP_');

        return view('admin.utils.platform.tickets.show', [
            'title' => $result->get('number'),
            'ticket' => $result->data,
            'model' => $model,
            'categories' => KeywordValueService::getEnum('TICKET_CATEGORY'),
            'priorities' => KeywordValueService::getEnum('TICKET_PRIORITY'),
            'team' => $team,
            'assigneeOptions' => $isSupport && $team !== [] ? array_intersect_key($team, array_flip($this->tickets->supportExecutiveIds())) : $team,
            'supportRequest' => $supportRequest,
            'diagnostics' => $supportRequest ? $support->bundleContents($supportRequest) : null,
        ]);
    }

    public function transition(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|string|max:20', 'remark' => 'nullable|string|max:5000']);
        $result = $this->tickets->transition($id, backpack_user()->id, $data['to'], $data['remark'] ?? null);
        $result->ok ? Alert::success(__('utils.flash.ticket_moved', ['status' => $result->get('status')]))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:250',
            'category' => 'required|string|max:50',
            'priority' => 'required|string|max:5',
            'owner_id' => 'nullable|integer',
            'assignees' => 'nullable|array',
            'assignees.*' => 'integer',
            'followers' => 'nullable|array',
            'followers.*' => 'integer',
            'snoopers' => 'nullable|array',
            'snoopers.*' => 'integer',
        ]);
        $result = $this->tickets->update($id, $data + ['owner_id' => null, 'assignees' => [], 'followers' => [], 'snoopers' => []], backpack_user()->id);
        $result->ok ? Alert::success(__('utils.flash.ticket_updated'))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    public function remark(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['body' => 'nullable|string|max:5000', 'file' => 'nullable|file']);
        $result = $this->tickets->remark($id, backpack_user()->id, (string) ($data['body'] ?? ''), $request->file('file'));
        $result->ok ? Alert::success(__('utils.flash.remark_posted'))->flash() : Alert::error($result->message)->flash();

        return back();
    }

    public function report(Request $request): View
    {
        if (! backpack_user()->can('UTL_TCKT_REPORT')) {
            abort(403);
        }
        $filters = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'branch' => 'nullable|string|max:20']);

        return view('admin.utils.platform.tickets.report', [
            'title' => 'Ticket report',
            'filters' => $filters,
            'report' => $this->tickets->report($filters),
            'categories' => KeywordValueService::getEnum('TICKET_CATEGORY'),
            'branches' => OrgService::branches(),
        ]);
    }
}
