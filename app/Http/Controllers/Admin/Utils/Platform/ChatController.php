<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\Platform\Chat\ChatService;
use App\Support\Result;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

/**
 * Remarks posted from `<x-chat.composer>` on any record screen (FRS §3). Access follows the
 * record: `ChatService::canView()` (model rule or the entity's view permission).
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function remark(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'ref_type' => 'required|string|max:30',
            'ref_id' => 'required|integer',
            'body' => 'nullable|string|max:5000',
            'file' => 'nullable|file',
            'parent_id' => 'nullable|integer',
            'internal' => 'nullable|boolean',
        ]);
        $model = $this->authorisedRecord($data['ref_type'], (int) $data['ref_id']);

        return $this->respond($request, $this->chat->remark($model, (string) ($data['body'] ?? ''), $request->file('file'), $data['parent_id'] ?? null, (bool) ($data['internal'] ?? false)), 'Remark posted.');
    }

    public function edit(Request $request, int $threadId): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $this->authorisedThread($threadId);

        return $this->respond($request, $this->chat->editRemark($threadId, $data['body']), 'Remark updated.');
    }

    public function destroy(Request $request, int $threadId): JsonResponse|RedirectResponse
    {
        $this->authorisedThread($threadId);

        return $this->respond($request, $this->chat->deleteRemark($threadId), 'Remark removed.');
    }

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['ref_type' => 'required|string|max:30', 'ref_id' => 'required|integer', 'follow' => 'required|boolean']);
        $model = $this->authorisedRecord($data['ref_type'], (int) $data['ref_id']);
        $result = $data['follow'] ? $this->chat->subscribe($model, backpack_user()->id) : $this->chat->unsubscribe($model, backpack_user()->id);

        return $this->respond($request, $result, $data['follow'] ? 'You follow this conversation.' : 'You no longer follow this conversation.');
    }

    private function authorisedRecord(string $refType, int $refId): Model
    {
        $model = $this->chat->resolve($refType, $refId);
        abort_if($model === null, 404);
        abort_unless($this->chat->canView($model, backpack_user()->id), 403);

        return $model;
    }

    private function authorisedThread(int $threadId): void
    {
        $thread = CommThread::query()->with('master')->findOrFail($threadId);
        $model = $thread->master?->entityable;
        abort_unless($model !== null && $this->chat->canView($model, backpack_user()->id), 403);
    }

    private function respond(Request $request, Result $result, string $success): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($result->toArray(), $result->ok ? 200 : 422);
        }
        $result->ok ? Alert::success($success)->flash() : Alert::error($result->message)->flash();

        return back();
    }
}
