<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Events\Platform\ChannelLinked;
use App\Models\Comms\WaMessage;
use App\Models\Comms\WaThread;
use App\Models\User;
use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Comms\Drivers\ChannelDriver;
use App\Services\Platform\Comms\Drivers\DriverRegistry;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Templates\TemplateService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp service (FRS §15): template (HSM) sends at any time, session sends only inside the
 * customer-care window; polls degrade to a numbered list when the driver has none; inbound media
 * goes to Docs (wa-inbound); STOP flips consent. Threads are the agent inbox.
 */
final class WhatsAppService
{
    public const SESSION_TYPES = ['TEXT', 'IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT', 'LOCATION', 'POLL', 'INTERACTIVE'];

    public function __construct(
        private readonly TemplateService $templates,
        private readonly OutboxService $outbox,
        private readonly ContactService $contacts,
        private readonly SettingsService $settings,
        private readonly ChatService $chat,
        private readonly DocsService $docs,
        private readonly DriverRegistry $drivers,
        private readonly NotifyService $notify,
    ) {}

    /**
     * @param  array<string, mixed>  $options  see FRS §15.4
     * @return Result data: {outbox_id, message_id, status, degraded?}
     */
    public function send(array $options, ?int $actorId = null): Result
    {
        $actorId ??= auth(backpack_guard_name())->id() ?? auth()->id();
        $resolved = $this->contacts->resolve($options['to'] ?? '', 'WHATSAPP');
        if ($resolved['address'] === null) {
            return Result::fail('INVALID_MSISDN', 'The destination is not a valid WhatsApp number.');
        }
        $waId = ltrim($resolved['address'], '+');
        $thread = WaThread::query()->firstOrCreate(['wa_id' => $waId], ['person_code' => $resolved['person_code'], 'label' => 'OPEN']);
        $refType = isset($options['ref_type']) ? strtoupper((string) $options['ref_type']) : $thread->ref_type;
        $refId = $options['ref_id'] ?? $thread->ref_id;

        if ($this->contacts->suppressed('WHATSAPP', $resolved['address']) || $this->contacts->hasOptedOut($resolved['person_code'], 'WHATSAPP')) {
            return Result::fail('CONSENT_DENIED', 'This contact opted out of WhatsApp.');
        }

        $degraded = false;
        if (! empty($options['template'])) {
            // WA-02: a template send carries variables only
            if (! empty($options['text'])) {
                return Result::fail('TEMPLATE_NO_EXTRA_TEXT', 'A template message cannot carry extra text; use its variables.');
            }
            $render = $this->templates->render((string) $options['template'], 'WHATSAPP', (array) ($options['vars'] ?? []), $options['locale'] ?? null);
            if (! $render->ok) {
                return $render;
            }
            if ($render->get('category') === 'PROMOTIONAL' && ! $this->contacts->consented($resolved['person_code'], 'WHATSAPP')) {
                return Result::fail('CONSENT_DENIED', 'No WhatsApp consent for promotional messages.');
            }
            $type = 'TEMPLATE';
            $text = (string) $render->get('text');
            $payload = ['to' => $waId, 'kind' => 'TEMPLATE', 'template' => $render->get('wa_payload'), 'media_doc_ids' => $this->docIds($options['attach'] ?? [])];
            $templateCode = $render->get('template_code');
            $templateVersion = $render->get('template_version');
            $category = $render->get('category');
        } else {
            $type = strtoupper((string) ($options['type'] ?? 'TEXT'));
            if (! in_array($type, self::SESSION_TYPES, true)) {
                return Result::fail('INVALID_TYPE', 'Unknown WhatsApp message type.');
            }
            // WA-01: free-form only inside the customer-care window
            if (! $thread->sessionOpen()) {
                return Result::fail('SESSION_CLOSED', 'The 24-hour window is closed; send an approved template instead.');
            }
            $text = trim((string) ($options['text'] ?? ''));
            $payload = ['to' => $waId, 'kind' => 'SESSION', 'type' => $type, 'text' => $text, 'media_doc_ids' => $this->docIds($options['attach'] ?? [])];
            if ($type === 'POLL') {
                $poll = (array) ($options['poll'] ?? []);
                if (trim((string) ($poll['question'] ?? '')) === '' || count((array) ($poll['options'] ?? [])) < 2) {
                    return Result::fail('INVALID_POLL', 'A poll needs a question and at least two options.');
                }
                $driver = $this->drivers->for('WHATSAPP');
                if (! ($driver instanceof ChannelDriver && $driver->supports('poll'))) {
                    // WA-05: degrade to a numbered list and parse the numeric reply
                    $degraded = true;
                    $text = $poll['question']."\n".collect($poll['options'])->values()->map(fn ($o, $i) => ($i + 1).'. '.$o)->implode("\n")."\nReply with the number.";
                    $payload = ['to' => $waId, 'kind' => 'SESSION', 'type' => 'TEXT', 'text' => $text];
                } else {
                    $payload['poll'] = $poll;
                }
            }
            if ($text === '' && $payload['media_doc_ids'] === [] && $type !== 'LOCATION') {
                return Result::fail('EMPTY', 'Nothing to send.');
            }
            $templateCode = null;
            $templateVersion = null;
            $category = 'OPERATIONAL';
        }

        $queued = $this->outbox->queue([
            'channel' => 'WHATSAPP', 'to_address' => '+'.$waId, 'to_person_code' => $thread->person_code ?? $resolved['person_code'],
            'envelope' => ['kind' => $payload['kind'], 'type' => $type], 'body_preview' => mb_substr($text, 0, 500), 'payload' => $payload,
            'template_code' => $templateCode, 'template_version' => $templateVersion, 'category' => $category,
            'ref_type' => $refType, 'ref_id' => $refId, 'idempotency_key' => $options['idempotency_key'] ?? null,
        ]);
        if (! $queued->ok || $queued->get('duplicate') || $queued->get('switched_off')) {
            return $queued;
        }
        $message = WaMessage::create([
            'thread_id' => $thread->id, 'direction' => 'OUT', 'type' => $type, 'text' => $text, 'doc_id' => $payload['media_doc_ids'][0] ?? null,
            'payload' => array_filter(['poll' => $options['poll'] ?? null, 'degraded' => $degraded ?: null, 'template' => $templateCode]),
            'status' => 'QUEUED', 'outbox_id' => $queued->get('outbox_id'), 'actor_id' => $actorId,
        ]);
        $thread->update(['last_message_at' => now()]);
        if ($templateCode) {
            $this->templates->recordUse($templateCode, 'WHATSAPP', (int) $templateVersion);
        }
        if ($refType && $refId && ($model = $this->chat->resolve($refType, (int) $refId))) {
            $this->chat->event($model, 'WHATSAPP_SENT', 'WhatsApp '.strtolower($type).' to '.$this->contacts->mask('+'.$waId), ['outbox_id' => $queued->get('outbox_id')], $actorId);
        }

        return Result::ok($queued->data + ['message_id' => $message->id, 'thread_id' => $thread->id, 'degraded' => $degraded]);
    }

    /**
     * Inbound message from the webhook (WA-03/04/08): idempotent on the provider id, opens the
     * session window, stores media in Docs, flips consent on STOP, remarks on a linked record.
     *
     * @param  array{from: string, id: string, type?: string, text?: string, media_base64?: string, media_name?: string, media_mime?: string, group_id?: string}  $event
     */
    public function inbound(array $event): Result
    {
        if (WaMessage::query()->where('provider_message_id', $event['id'])->exists()) {
            return Result::ok(['duplicate' => true]);
        }
        $from = $this->contacts->normalise((string) $event['from'], 'WHATSAPP');
        if ($from === null) {
            return Result::fail('INVALID_MSISDN', 'Unknown sender.');
        }
        $waId = ltrim($event['group_id'] ?? $from, '+');
        $thread = WaThread::query()->firstOrCreate(['wa_id' => $waId], [
            'is_group' => isset($event['group_id']), 'person_code' => isset($event['group_id']) ? null : $this->contacts->personByAddress($from, 'WHATSAPP'), 'label' => 'OPEN',
        ]);
        $type = strtoupper((string) ($event['type'] ?? 'TEXT'));
        $text = trim((string) ($event['text'] ?? ''));

        $docId = null;
        if (! empty($event['media_base64'])) {
            $path = tempnam(sys_get_temp_dir(), 'wa');
            file_put_contents($path, base64_decode((string) $event['media_base64']));
            $file = new UploadedFile($path, $event['media_name'] ?? 'whatsapp-media', $event['media_mime'] ?? null, null, true);
            $stored = $this->docs->attach($thread, $file, 'wa-inbound', ['title' => $event['media_name'] ?? 'WhatsApp media'], null, withEvent: false);
            @unlink($path);
            $docId = $stored->ok ? (int) $stored->get('id') : null;
        }

        $payload = [];
        $lastOut = WaMessage::query()->where('thread_id', $thread->id)->where('direction', 'OUT')->latest('id')->first();
        if ($lastOut && ($lastOut->payload['degraded'] ?? false) && preg_match('/^\d{1,2}$/', $text)) {
            $options = (array) ($lastOut->payload['poll']['options'] ?? []);
            $choice = $options[(int) $text - 1] ?? null;
            if ($choice !== null) {
                $payload['poll_answer'] = ['question' => $lastOut->payload['poll']['question'] ?? null, 'answer' => $choice, 'poll_message_id' => $lastOut->id];
            }
        }

        $message = WaMessage::create([
            'thread_id' => $thread->id, 'direction' => 'IN', 'type' => $type, 'text' => $text, 'doc_id' => $docId,
            'payload' => $payload ?: null, 'sender_wa_id' => ltrim($from, '+'), 'provider_message_id' => $event['id'], 'status' => 'RECEIVED',
        ]);
        $thread->update([
            'session_expires_at' => now()->addHours((int) $this->settings->get('whatsapp.session_hours', 24)),
            'last_message_at' => now(), 'unread' => $thread->unread + 1, 'label' => $thread->label === 'DONE' ? 'OPEN' : $thread->label,
        ]);

        if (in_array(strtoupper($text), ['STOP', 'UNSUBSCRIBE', 'OPTOUT'], true)) {
            if ($thread->person_code) {
                $this->contacts->setConsent($thread->person_code, 'WHATSAPP', false, 'INBOUND_STOP');
            }
            $this->contacts->suppress('WHATSAPP', $from, 'STOP');
        }
        if ($thread->ref_type && $thread->ref_id && ($model = $this->chat->resolve($thread->ref_type, (int) $thread->ref_id))) {
            $this->chat->event($model, 'WHATSAPP_INBOUND', 'WhatsApp from '.$this->contacts->mask($from).': '.mb_substr($text !== '' ? $text : '['.strtolower($type).']', 0, 200), ['wa_message_id' => $message->id, 'doc_id' => $docId]);
        }
        if ($thread->assigned_to) {
            $this->notify->to((int) $thread->assigned_to)->kind('M')->about('WA_THREAD', $thread->id)->actor(null)->notifySelf()
                ->title('WhatsApp from '.$this->contacts->mask($from))->body(mb_substr($text, 0, 200))->send();
        }

        return Result::ok(['message_id' => $message->id, 'thread_id' => $thread->id, 'doc_id' => $docId]);
    }

    /** Delivery / read status from the webhook. */
    public function status(string $providerMessageId, string $status): Result
    {
        $outbox = $this->outbox->markByProviderId('WHATSAPP', $providerMessageId, $status);
        if ($outbox) {
            WaMessage::query()->where('outbox_id', $outbox->id)->update(['status' => strtoupper($status)]);
        }

        return $outbox ? Result::ok() : Result::fail('NOT_FOUND', 'Unknown message.');
    }

    /** Messages of one conversation, oldest first (WA-06). */
    public function thread(string $waId, ?string $since = null, int $perPage = 50): LengthAwarePaginator
    {
        $thread = WaThread::query()->where('wa_id', ltrim($waId, '+'))->firstOrFail();

        return WaMessage::query()->where('thread_id', $thread->id)
            ->when($since, fn ($q) => $q->where('created_at', '>=', Carbon::parse($since)))
            ->orderBy('created_at')->orderBy('id')->paginate($perPage);
    }

    /**
     * History by person, group or thread, filtered by date / type / direction (FRS §15.2).
     *
     * @param  array{person?: string, group_id?: string, wa_id?: string, direction?: string, type?: string, from?: string, to?: string, with_media?: bool}  $filters
     * @return list<array<string, mixed>>
     */
    public function history(array $filters): array
    {
        $threads = WaThread::query()
            ->when($filters['person'] ?? null, fn ($q, $p) => $q->where('person_code', $p))
            ->when($filters['group_id'] ?? null, fn ($q, $g) => $q->where('wa_id', ltrim($g, '+')))
            ->when($filters['wa_id'] ?? null, fn ($q, $w) => $q->where('wa_id', ltrim($w, '+')))
            ->pluck('id');

        return WaMessage::query()->whereIn('thread_id', $threads)
            ->when(in_array(strtoupper($filters['direction'] ?? 'ANY'), ['IN', 'OUT'], true), fn ($q) => $q->where('direction', strtoupper($filters['direction'])))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', strtoupper($t)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($filters['with_media'] ?? false, fn ($q) => $q->whereNotNull('doc_id'))
            ->orderBy('created_at')->limit(2000)->get()
            ->map(fn (WaMessage $m) => $m->only(['id', 'thread_id', 'direction', 'type', 'text', 'doc_id', 'status', 'sender_wa_id']) + [
                'person_code' => $m->sender_wa_id ? $this->contacts->personByAddress('+'.$m->sender_wa_id, 'WHATSAPP') : null,
                'at' => $m->created_at?->toIso8601String(), 'poll_answer' => $m->payload['poll_answer'] ?? null,
            ])->all();
    }

    public function markRead(int $messageId): Result
    {
        $message = WaMessage::query()->find($messageId);
        if (! $message) {
            return Result::fail('NOT_FOUND', 'Message not found.');
        }
        DB::transaction(function () use ($message) {
            WaMessage::query()->where('thread_id', $message->thread_id)->where('direction', 'IN')->where('id', '<=', $message->id)->whereNull('read_at')->update(['read_at' => now()]);
            WaThread::query()->whereKey($message->thread_id)->update(['unread' => WaMessage::query()->where('thread_id', $message->thread_id)->where('direction', 'IN')->whereNull('read_at')->count()]);
        });

        return Result::ok();
    }

    /** Link a thread to a record (WA-12): Chat CHANNEL_LINKED; later inbound lands on its timeline. */
    public function link(int $threadId, string $refType, int $refId, ?int $actorId = null): Result
    {
        $thread = WaThread::query()->find($threadId);
        $model = $this->chat->resolve($refType, $refId);
        if (! $thread || ! $model) {
            return Result::fail('NOT_FOUND', 'Thread or record not found.');
        }
        $thread->update(['ref_type' => strtoupper($refType), 'ref_id' => $refId]);
        $this->chat->event($model, 'CHANNEL_LINKED', 'WhatsApp conversation '.$this->contacts->mask('+'.$thread->wa_id).' linked', ['wa_thread_id' => $thread->id], $actorId);
        ChannelLinked::dispatch($thread->id, strtoupper($refType), $refId);

        return Result::ok();
    }

    public function assign(int $threadId, ?int $userId, ?string $label = null): Result
    {
        $thread = WaThread::query()->find($threadId);
        if (! $thread) {
            return Result::fail('NOT_FOUND', 'Thread not found.');
        }
        if ($label !== null && ! in_array(strtoupper($label), ['OPEN', 'PENDING', 'DONE'], true)) {
            return Result::fail('INVALID_LABEL', 'Label must be OPEN, PENDING or DONE.');
        }
        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            return Result::fail('INVALID_USER', 'Unknown user.');
        }
        $thread->update(array_filter(['assigned_to' => $userId, 'label' => $label ? strtoupper($label) : null], fn ($v) => $v !== null) + ($userId === null && $label === null ? ['assigned_to' => null] : []));

        return Result::ok();
    }

    /** @return LengthAwarePaginator threads for the agent inbox */
    public function inbox(int $userId, string $box = 'MINE', int $perPage = 25): LengthAwarePaginator
    {
        return WaThread::query()
            ->when(strtoupper($box) === 'MINE', fn ($q) => $q->where('assigned_to', $userId))
            ->when(strtoupper($box) === 'QUEUE', fn ($q) => $q->whereNull('assigned_to')->where('label', '!=', 'DONE'))
            ->when(strtoupper($box) === 'DONE', fn ($q) => $q->where('label', 'DONE'))
            ->orderByDesc('unread')->orderByDesc('last_message_at')->paginate($perPage)->withQueryString();
    }

    /** @return list<int> */
    private function docIds(array $attach): array
    {
        $ids = array_map(fn ($a) => (int) (is_array($a) ? ($a['doc_id'] ?? 0) : $a), $attach);

        return Document::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
