<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Utilities\Support\SupportRequest;
use App\Models\Utilities\Ticket\TicketPerson;
use App\Services\Platform\Help\SupportRequestService;
use App\Services\Platform\Ticket\TicketService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Support requests (DEC-094, W16e): every signed-in user may send one from the F1 pane and see their own; support
 * admins (`UTL_SUPP_ADMIN`) see all and assign executives; executives (`UTL_SUPP_EXEC`) see the ones assigned to them.
 * Diagnostic zips are downloadable only by the requester, support admins and the ticket's assignees.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportRequestService $support) {}

    /** My support requests (admins: all; executives: also those assigned to them). */
    public function index(): View
    {
        $user = backpack_user();
        $isAdmin = $user->can('UTL_SUPP_ADMIN');
        $assigned = TicketPerson::query()->where('user_id', $user->id)->where('role', TicketService::ASSIGNEE)->pluck('ticket_id');
        $rows = SupportRequest::query()->with(['ticket', 'requester'])
            ->when(! $isAdmin, fn ($q) => $q->where(fn ($w) => $w->where('requester_id', $user->id)->orWhereIn('ticket_id', $assigned)))
            ->latest('id')->paginate(25);

        return view('admin.utils.platform.support.index', [
            'title' => __('utils.support.my_requests'),
            'rows' => $rows,
            'isAdmin' => $isAdmin,
            'assignees' => TicketPerson::query()->whereIn('ticket_id', $rows->pluck('ticket_id')->filter())->where('role', TicketService::ASSIGNEE)
                ->get(['ticket_id', 'user_id'])->groupBy('ticket_id')->map(fn ($g) => $g->pluck('user_id')->all())->all(),
            'executives' => $isAdmin ? User::query()->whereIn('id', $this->support->holders('UTL_SUPP_EXEC'))->get()
                ->mapWithKeys(fn (User $u) => [$u->id => $u->getAttribute('display_name') ?: $u->getAttribute('username')])->all() : [],
        ]);
    }

    /** Send a support request from the pane: JSON `{ok, code, message, data{id, ticket_id, number, url}}`. */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'category' => ['required', Rule::in(SupportRequestService::CATEGORIES)],
            'urgent' => ['boolean'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'route' => ['nullable', 'string', 'max:190'],
            'diagnostics' => ['boolean'],
            'snapshot' => ['nullable', 'array'],
            'screenshot' => ['nullable', 'string', 'max:8000000'],
        ]);
        $result = $this->support->submit(backpack_user(), $input);
        if ($result->ok) {
            $result = Result::ok($result->data + ['url' => route('utils.tickets.show', ['id' => $result->get('ticket_id')])],
                __('utils.support.sent', ['number' => $result->get('number')]));
        }

        return response()->json($result->toArray(), $result->ok ? 201 : 422);
    }

    /** The diagnostic zip (requester, support admins, assignees only; 410 once purged). */
    public function download(int $id): BinaryFileResponse
    {
        $request = SupportRequest::query()->findOrFail($id);
        abort_unless($this->support->canDownload($request, backpack_user()), 403);
        $file = $this->support->bundleFile($request);
        abort_if($file === null, 410, __('utils.support.bundle_gone'));

        return response()->download($file, basename($file), ['Content-Type' => 'application/zip']);
    }

    /** Support admin: assign executives (`UTL_SUPP_EXEC` holders only). */
    public function assign(Request $request, int $id): RedirectResponse
    {
        if (! backpack_user()->can('UTL_SUPP_ADMIN')) {
            abort(403, 'Unauthorized. You do not have permission to perform this action.');
        }
        $request->validate(['executives' => ['array'], 'executives.*' => ['integer']]);
        $ids = array_map('intval', (array) $request->input('executives', []));
        $result = $this->support->assign(SupportRequest::query()->findOrFail($id), $ids, backpack_user());

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? __('utils.support.assigned') : $result->message);
    }
}
