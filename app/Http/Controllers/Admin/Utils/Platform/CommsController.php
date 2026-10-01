<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Comms\CommCall;
use App\Models\Comms\CommOutbox;
use App\Models\Comms\CommSandbox;
use App\Models\Comms\WaMessage;
use App\Models\Comms\WaThread;
use App\Models\Utilities\Docs\Document;
use App\Services\KeywordValueService;
use App\Services\OrgService;
use App\Services\Platform\Comms\ContactService;
use App\Services\Platform\Comms\EmailService;
use App\Services\Platform\Comms\OutboxService;
use App\Services\Platform\Comms\TelephonyService;
use App\Services\Platform\Comms\WhatsAppService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Comms operations: outbox + sandbox viewer with resend (UTL_COMM_VIEW / UTL_COMM_SEND), WhatsApp
 * agent inbox (UTL_COMM_WA_INBOX), call log + click-to-call + dispositions (UTL_COMM_CALL).
 */
class CommsController extends Controller
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly WhatsAppService $whatsapp,
        private readonly TelephonyService $telephony,
        private readonly ContactService $contacts,
    ) {}

    // ── Outbox ──────────────────────────────────────────────────────────

    public function outbox(Request $request): View
    {
        $this->gate('UTL_COMM_VIEW');
        $filters = $request->only(['channel', 'status', 'template', 'person', 'ref_type', 'ref_id', 'q']);
        $tab = $request->query('tab') === 'sandbox' ? 'sandbox' : 'outbox';

        return view('admin.utils.platform.comms.outbox', [
            'title' => 'Outbox', 'tab' => $tab, 'filters' => $filters, 'stats' => $this->outbox->stats(),
            'rows' => $tab === 'outbox' ? $this->outbox->search($filters) : null,
            'sandbox' => $tab === 'sandbox' ? CommSandbox::query()->toBase()->latest('id')->paginate(30)->withQueryString() : null,
            'contacts' => $this->contacts,
        ]);
    }

    public function outboxShow(int $id): View
    {
        $this->gate('UTL_COMM_VIEW');
        $row = CommOutbox::query()->with('actor')->findOrFail($id);

        return view('admin.utils.platform.comms.outbox-show', [
            'title' => "Outbox #{$id}", 'row' => $row, 'contacts' => $this->contacts,
            'copies' => CommOutbox::query()->where('parent_outbox_id', $row->parent_outbox_id ?? $row->id)->orWhere('id', $row->parent_outbox_id)->orderBy('id')->get(['id', 'status', 'driver', 'created_at']),
            'sandbox' => CommSandbox::query()->where('outbox_id', $row->id)->toBase()->first(),
        ]);
    }

    public function resend(int $id): RedirectResponse
    {
        $this->gate('UTL_COMM_SEND');
        $result = $this->outbox->resend($id, backpack_user()->id);
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back();
        }
        Alert::success(__('utils.flash.queued_again', ['id' => $result->get('outbox_id')]))->flash();

        return redirect()->route('utils.comms.outbox.show', $result->get('outbox_id'));
    }

    /** `<x-email.send-panel>` posts here (EML-10). */
    public function sendEmail(Request $request, EmailService $email): RedirectResponse
    {
        $this->gate('UTL_COMM_SEND');
        $data = $request->validate([
            'template' => 'required|string|max:120', 'to' => 'required|string|max:1000', 'cc' => 'nullable|string|max:1000', 'bcc' => 'nullable|string|max:1000',
            'from' => 'nullable|string|max:150', 'vars_json' => 'nullable|string|max:10000', 'attach' => 'nullable|array', 'attach.*' => 'integer',
            'ref_type' => 'nullable|string|max:30', 'ref_id' => 'nullable|integer',
        ]);
        $split = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $s))));
        $result = $email->send([
            'template' => $data['template'], 'to' => $split($data['to']), 'cc' => $split($data['cc'] ?? ''), 'bcc' => $split($data['bcc'] ?? ''),
            'from' => $data['from'] ?: 'default', 'vars' => (array) json_decode((string) ($data['vars_json'] ?? '{}'), true),
            'attach' => $data['attach'] ?? [], 'ref_type' => $data['ref_type'] ?? null, 'ref_id' => $data['ref_id'] ?? null,
        ]);

        return $this->respond($result, 'Email queued (#'.($result->get('outbox_id') ?? '?').').');
    }

    // ── WhatsApp inbox ──────────────────────────────────────────────────

    public function whatsapp(Request $request, ?int $threadId = null): View
    {
        $this->gate('UTL_COMM_WA_INBOX');
        $box = in_array(strtoupper((string) $request->query('box')), ['MINE', 'QUEUE', 'ALL', 'DONE'], true) ? strtoupper($request->query('box')) : 'QUEUE';
        $thread = $threadId ? WaThread::query()->with('assignee')->findOrFail($threadId) : null;
        $messages = $thread ? WaMessage::query()->where('thread_id', $thread->id)->latest('id')->limit(100)->get()->reverse()->values() : collect();
        if ($thread && $messages->isNotEmpty() && $thread->unread > 0) {
            $this->whatsapp->markRead((int) $messages->where('direction', 'IN')->last()?->id ?: $messages->last()->id);
            $thread->refresh();
        }

        return view('admin.utils.platform.comms.whatsapp', [
            'title' => 'WhatsApp inbox', 'box' => $box, 'threads' => $this->whatsapp->inbox(backpack_user()->id, $box),
            'thread' => $thread, 'messages' => $messages, 'contacts' => $this->contacts, 'team' => $thread ? OrgService::teamOptions() : [],
            'docs' => $thread ? Document::query()->whereIn('id', $messages->pluck('doc_id')->filter())->with('media')->get()->keyBy('id') : collect(),
        ]);
    }

    public function whatsappSend(Request $request, int $threadId): RedirectResponse
    {
        $this->gate('UTL_COMM_WA_INBOX');
        $thread = WaThread::query()->findOrFail($threadId);
        $data = $request->validate([
            'mode' => 'required|in:TEXT,TEMPLATE,POLL', 'text' => 'nullable|string|max:4000', 'template' => 'nullable|string|max:120',
            'vars_json' => 'nullable|string|max:5000', 'question' => 'nullable|string|max:250', 'options' => 'nullable|string|max:1000',
        ]);
        $options = ['to' => '+'.$thread->wa_id, 'ref_type' => $thread->ref_type, 'ref_id' => $thread->ref_id];
        $options += match ($data['mode']) {
            'TEMPLATE' => ['template' => $data['template'], 'vars' => (array) json_decode((string) ($data['vars_json'] ?? '{}'), true)],
            'POLL' => ['type' => 'POLL', 'poll' => ['question' => $data['question'], 'options' => array_values(array_filter(array_map('trim', explode("\n", (string) $data['options']))))]],
            default => ['type' => 'TEXT', 'text' => $data['text'], 'raw' => true],
        };

        return $this->respond($this->whatsapp->send($options, backpack_user()->id), 'Message queued.');
    }

    public function whatsappManage(Request $request, int $threadId): RedirectResponse
    {
        $this->gate('UTL_COMM_WA_INBOX');
        $data = $request->validate([
            'assigned_to' => 'nullable|integer', 'label' => 'nullable|in:OPEN,PENDING,DONE',
            'ref_type' => 'nullable|string|max:30', 'ref_id' => 'nullable|integer|required_with:ref_type',
        ]);
        if (! empty($data['ref_type'])) {
            $linked = $this->whatsapp->link($threadId, $data['ref_type'], (int) $data['ref_id'], backpack_user()->id);
            if (! $linked->ok) {
                return $this->respond($linked, '');
            }
        }

        return $this->respond($this->whatsapp->assign($threadId, isset($data['assigned_to']) ? (int) $data['assigned_to'] : null, $data['label'] ?? null), 'Conversation updated.');
    }

    // ── Calls ───────────────────────────────────────────────────────────

    public function calls(Request $request): View
    {
        $this->gate('UTL_COMM_CALL');
        $filters = $request->only(['person', 'status', 'from', 'to', 'ref_type', 'ref_id', 'call']);
        if (! backpack_user()->can('UTL_COMM_VIEW')) {
            $filters['agent'] = backpack_user()->id;
        }

        return view('admin.utils.platform.comms.calls', [
            'title' => 'Call log', 'filters' => $filters, 'calls' => $this->telephony->calls($filters),
            'dispositions' => KeywordValueService::getEnum('CALL_DISPOSITION'), 'telephony' => $this->telephony,
        ]);
    }

    public function dial(Request $request): JsonResponse|RedirectResponse
    {
        $this->gate('UTL_COMM_CALL');
        $data = $request->validate(['to' => 'required|string|max:50', 'ref_type' => 'nullable|string|max:30', 'ref_id' => 'nullable|integer']);
        $result = $this->telephony->dial(backpack_user()->id, $data['to'], ! empty($data['ref_type']) ? ['type' => $data['ref_type'], 'id' => (int) $data['ref_id']] : null);

        return $request->expectsJson() ? response()->json($result->toArray(), $result->ok ? 200 : 422) : $this->respond($result, 'Calling '.($result->get('to_masked') ?? '').' — your phone rings first.');
    }

    public function dispose(Request $request, int $callId): RedirectResponse
    {
        $this->gate('UTL_COMM_CALL');
        $call = CommCall::query()->findOrFail($callId);
        abort_unless($call->chatCanView(backpack_user()->id), 403);
        $data = $request->validate(['disposition' => 'required|string|max:30', 'remark' => 'nullable|string|max:500']);

        return $this->respond($this->telephony->dispose($callId, $data['disposition'], $data['remark'] ?? null, backpack_user()->id), 'Disposition saved.');
    }

    /** Play (inline) — everyone who may see the call; download only with UTL_COMM_RECORDING_DOWNLOAD (TEL-09). */
    public function recording(Request $request, int $callId): BinaryFileResponse
    {
        $this->gate('UTL_COMM_CALL');
        $result = $this->telephony->recording($callId, backpack_user()->id);
        abort_unless($result->ok, 403, $result->message);
        $download = $request->boolean('download');
        abort_if($download && ! $result->get('can_download'), 403, 'Recording download needs UTL_COMM_RECORDING_DOWNLOAD.');
        $media = Document::query()->findOrFail($result->get('id'))->media()->firstOrFail();

        return $download ? response()->download($media->getPath(), $media->file_name) : response()->file($media->getPath(), ['Content-Type' => $media->mime_type, 'Content-Disposition' => 'inline']);
    }

    private function respond(Result $result, string $success): RedirectResponse
    {
        $result->ok ? Alert::success($success)->flash() : Alert::error($result->message)->flash();

        return back();
    }

    private function gate(string $permission): void
    {
        if (! backpack_user()->can($permission)) {
            abort(403);
        }
    }
}
