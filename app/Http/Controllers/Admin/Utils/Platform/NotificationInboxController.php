<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Noty\Notification;
use App\Services\Platform\Notify\NotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own inbox (FRS NOT-06..09): notifications, alerts and messages.
 * Everyone may read their own inbox, so there is no permission gate beyond being signed in.
 */
class NotificationInboxController extends Controller
{
    public function __construct(private readonly NotifyService $notify) {}

    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), ['N', 'A', 'M'], true) ? $request->query('kind') : 'N';
        $state = in_array($request->query('state'), ['ALL', 'UNREAD', 'READ', 'ARCHIVE'], true) ? $request->query('state') : 'ALL';

        return view('admin.utils.platform.notifications.index', [
            'title' => 'My inbox',
            'kind' => $kind,
            'state' => $state,
            'rows' => $this->notify->list(backpack_user()->id, $kind, $state)->withQueryString(),
            'counts' => $this->notify->counts(backpack_user()->id),
        ]);
    }

    /** Mark one row READ / UNREAD / ARCHIVE. */
    public function mark(Request $request, string $kind, int $id): JsonResponse|RedirectResponse
    {
        $request->validate(['state' => 'required|in:READ,UNREAD,ARCHIVE']);
        $result = $this->notify->mark(backpack_user()->id, $kind, $id, $request->input('state'));

        return $request->expectsJson()
            ? response()->json($result->toArray() + ['counts' => $this->notify->counts(backpack_user()->id)], $result->ok ? 200 : 404)
            : back();
    }

    public function markAll(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['kind' => 'nullable|in:N,A,M']);
        $this->notify->markAll(backpack_user()->id, $request->input('kind'));

        return $request->expectsJson() ? response()->json(['ok' => true, 'counts' => $this->notify->counts(backpack_user()->id)]) : back();
    }

    /** Open a notification: mark it read and follow its deep link. */
    public function open(string $kind, int $id): RedirectResponse
    {
        $row = (strtoupper($kind) === 'A' ? Alert::query() : Notification::query())->where('user_id', backpack_user()->id)->findOrFail($id);
        $this->notify->mark(backpack_user()->id, $kind, $id, NotifyService::READ);
        $link = $row->payload['deep_link'] ?? $this->notify->deepLink($row->reference_type, $row->reference_id);

        return $link ? redirect()->to($link) : redirect()->route('utils.inbox.index', ['kind' => strtoupper($kind)]);
    }
}
