<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Support\SupportRequest;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\Platform\Help\SupportRequestService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Support requests (DEC-094, W16e): every signed-in user may send one from the F1 pane; it becomes a ticket they follow
 * under Tickets. The diagnostics are for the support team only — shown on the ticket page and downloadable by support
 * admins and the ticket's owner, assignees and snoopers, never by the requester (owner 03-10). The pane request can be
 * switched off (`support.pane_requests`); "My support tickets" (My Account menu) always lists the user's own tickets and
 * opens a plain support ticket.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportRequestService $support) {}

    /** Send a support request from the pane: JSON `{ok, code, message, data{id, ticket_id, number, url}}`. */
    public function store(Request $request): JsonResponse
    {
        if (! setting('support.pane_requests', true)) {
            return response()->json(Result::fail('SUPPORT_PANE_DISABLED', __('utils.support.pane_disabled'))->toArray(), 403);
        }
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

    /** My support tickets (every signed-in user): the tickets they raised, newest first, and the new-ticket form. */
    public function mine(): View
    {
        return view('admin.utils.platform.support.mine', [
            'title' => __('utils.support.my_requests'),
            'tickets' => Ticket::query()->where('requester_id', backpack_user()->id)->latest('id')->paginate(20),
            'categories' => __('utils.support.categories'),
        ]);
    }

    /** Open a plain support ticket from "My support tickets" (no diagnostics), routed like a pane request. */
    public function open(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'category' => ['required', Rule::in(SupportRequestService::CATEGORIES)],
            'urgent' => ['nullable', 'boolean'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        $result = $this->support->submit(backpack_user(), $input + ['urgent' => (bool) ($input['urgent'] ?? false), 'diagnostics' => false]);
        if (! $result->ok) {
            return back()->withInput()->with('error', $result->message);
        }

        return redirect()->route('utils.tickets.show', ['id' => $result->get('ticket_id')])
            ->with('success', __('utils.support.created', ['number' => $result->get('number')]));
    }

    /** The diagnostic zip — support team only (403 for the requester and others; 410 once purged). */
    public function download(int $id): BinaryFileResponse
    {
        $request = SupportRequest::query()->findOrFail($id);
        abort_unless($this->support->canViewDiagnostics(Ticket::query()->find($request->ticket_id), backpack_user()), 403);
        $file = $this->support->bundleFile($request);
        abort_if($file === null, 410, __('utils.support.bundle_gone'));

        return response()->download($file, basename($file), ['Content-Type' => 'application/zip']);
    }
}
