<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Support\SupportRequest;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\Platform\Help\SupportRequestService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Support requests (DEC-094, W16e): every signed-in user may send one from the F1 pane; it becomes a ticket they follow
 * under Tickets. The diagnostics are for the support team only — shown on the ticket page and downloadable by support
 * admins and the ticket's owner, assignees and snoopers, never by the requester (owner 03-10).
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportRequestService $support) {}

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
