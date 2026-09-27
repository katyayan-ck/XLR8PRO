<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Events\Platform\CallRecorded;
use App\Models\Comms\CommCall;
use App\Models\User;
use App\Models\Utilities\Docs\Document;
use App\Services\KeywordValueService;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Comms\Drivers\DriverRegistry;
use App\Services\Platform\Comms\Drivers\TelephonyDriver;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Telephony wrapper (FRS §16): click-to-call, call rows, recordings in Docs, dispositions. The
 * browser only ever sees masked numbers (TEL-02); the call row exists before the vendor answers.
 */
final class TelephonyService
{
    public function __construct(
        private readonly DriverRegistry $drivers,
        private readonly ContactService $contacts,
        private readonly SettingsService $settings,
        private readonly ChatService $chat,
        private readonly DocsService $docs,
        private readonly NotifyService $notify,
    ) {}

    /**
     * Ring the agent, then the customer (FRS §16.3).
     *
     * @param  int|string  $to  person_code, or a raw number
     * @param  array{type?: string, id?: int}|null  $ref
     * @param  array{caller_id?: string, record?: bool, campaign?: bool}  $options
     * @return Result data: {call_id, status, to_masked}
     */
    public function dial(int $actor, int|string $to, ?array $ref = null, array $options = []): Result
    {
        $agent = User::query()->with(['person'])->find($actor);
        $agentNumber = $this->contacts->normalise($agent?->person?->primary_mobile, 'SMS');
        if (! $agent || $agentNumber === null) {
            return Result::fail('AGENT_NO_PHONE', 'Your profile has no mobile number to ring first.');
        }
        $resolved = $this->contacts->resolve($to, 'SMS');
        if ($resolved['address'] === null) {
            return Result::fail('INVALID_MSISDN', 'The customer has no valid number.');
        }
        $campaign = (bool) ($options['campaign'] ?? false);
        // TEL-01: consent + quiet window apply to promotional campaigns only
        if ($campaign && (! $this->contacts->consented($resolved['person_code'], 'CALL') || ! $this->contacts->withinWindow((string) $this->settings->get('comms.promo_window', '')))) {
            return Result::fail('CONSENT_DENIED', 'No call consent, or outside the campaign window.');
        }

        $call = CommCall::create([
            'direction' => 'OUT', 'from_number' => $agentNumber, 'to_number' => $resolved['address'], 'agent_user_id' => $actor,
            'person_code' => $resolved['person_code'], 'status' => 'DIALING', 'started_at' => now(), 'caller_id' => $options['caller_id'] ?? null,
            'is_campaign' => $campaign, 'ref_type' => isset($ref['type']) ? strtoupper((string) $ref['type']) : null, 'ref_id' => $ref['id'] ?? null,
            'driver' => $this->driver()->name(),
        ]);
        $dialled = $this->driver()->dial($call, $agentNumber, $resolved['address'], $options + ['record' => true]);
        $call->update($dialled['ok'] ? ['vendor_call_id' => $dialled['vendor_call_id'] ?? null, 'status' => 'RINGING'] : ['status' => 'FAILED', 'disposition_remark' => mb_substr((string) ($dialled['error'] ?? 'Dial failed'), 0, 500)]);
        if ($call->ref_type && ($model = $this->chat->resolve($call->ref_type, (int) $call->ref_id))) {
            $this->chat->event($model, 'CALL_DIALLED', 'Call to '.$this->contacts->mask($resolved['address']).' by '.$agent->display_name, ['call_id' => $call->id], $actor);
        }

        return $dialled['ok']
            ? Result::ok(['call_id' => $call->id, 'status' => $call->status, 'to_masked' => $this->contacts->mask($resolved['address'])])
            : Result::fail('DIAL_FAILED', (string) $call->disposition_remark, ['call_id' => $call->id]);
    }

    /**
     * Vendor call event (webhook): status / timing / recording ready. Idempotent per status.
     *
     * @param  array{vendor_call_id: string, status?: string, answered_at?: string, ended_at?: string, duration?: int, recording_ready?: bool, from?: string, to?: string, direction?: string}  $event
     */
    public function event(array $event): Result
    {
        $call = CommCall::query()->where('vendor_call_id', $event['vendor_call_id'])->first();
        if (! $call && strtoupper((string) ($event['direction'] ?? '')) === 'IN') {
            return $this->inbound($event);
        }
        if (! $call) {
            return Result::fail('NOT_FOUND', 'Unknown call.');
        }
        $status = strtoupper((string) ($event['status'] ?? $call->status));
        $call->update(array_filter([
            'status' => $status,
            'answered_at' => isset($event['answered_at']) ? Carbon::parse($event['answered_at']) : ($status === 'ANSWERED' && ! $call->answered_at ? now() : null),
            'ended_at' => isset($event['ended_at']) ? Carbon::parse($event['ended_at']) : (in_array($status, ['COMPLETED', 'NO_ANSWER', 'BUSY', 'FAILED'], true) && ! $call->ended_at ? now() : null),
            'duration_seconds' => $event['duration'] ?? null,
        ], fn ($v) => $v !== null));
        if (! empty($event['recording_ready'])) {
            return $this->pullRecording($call->id);
        }

        return Result::ok(['call_id' => $call->id, 'status' => $call->status]);
    }

    /** Fetch the recording into Docs (TEL-04), idempotent; Chat CALL_RECORDED on the linked record. */
    public function pullRecording(int $callId): Result
    {
        $call = CommCall::query()->find($callId);
        if (! $call) {
            return Result::fail('NOT_FOUND', 'Unknown call.');
        }
        if ($call->recording_doc_id) {
            return Result::ok(['doc_id' => $call->recording_doc_id, 'duplicate' => true]);
        }
        $bytes = $this->driver()->fetchRecording($call);
        if ($bytes === null) {
            return Result::fail('NOT_READY', 'The recording is not available yet.');
        }
        $path = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($path, $bytes);
        $stored = $this->docs->attach($call, new UploadedFile($path, "call-{$call->id}.wav", 'audio/wav', null, true), 'call-recordings',
            ['title' => "Call recording #{$call->id}"], $call->agent_user_id, withEvent: false);
        @unlink($path);
        if (! $stored->ok) {
            return $stored;
        }
        $docId = (int) $stored->get('id');
        // entitlement: the agent + whoever may see the call (PARENT → CommCall::chatCanView)
        $this->docs->entitle($docId, ['users' => array_filter([$call->agent_user_id]), 'parent' => true]);
        $call->update(['recording_doc_id' => $docId, 'recording_missing' => false]);
        if ($call->ref_type && ($model = $this->chat->resolve($call->ref_type, (int) $call->ref_id))) {
            $this->chat->event($model, 'CALL_RECORDED', "Call recording available (call #{$call->id})", ['call_id' => $call->id, 'doc_id' => $docId]);
        }
        CallRecorded::dispatch($call->id, $docId, $call->ref_type);

        return Result::ok(['doc_id' => $docId]);
    }

    /** @return Result data: the recording's Docs DTO */
    public function recording(int $callId, ?int $viewerId = null): Result
    {
        $call = CommCall::query()->find($callId);
        if (! $call || ! $call->recording_doc_id) {
            return Result::fail('NOT_FOUND', 'No recording for this call.');
        }
        $viewerId ??= auth(backpack_guard_name())->id() ?? auth()->id();
        if ($viewerId && ! $this->docs->canView($call->recording_doc_id, (int) $viewerId)) {
            return Result::fail('FORBIDDEN', 'You may not play this recording.');
        }
        $doc = Document::query()->with('media')->find($call->recording_doc_id);

        return Result::ok($this->docs->dto($doc) + ['can_download' => (bool) User::query()->find($viewerId)?->can('UTL_COMM_RECORDING_DOWNLOAD')]);
    }

    /** Disposition (KeyValue CALL_DISPOSITION). */
    public function dispose(int $callId, string $code, ?string $remark = null, ?int $actorId = null): Result
    {
        $call = CommCall::query()->find($callId);
        if (! $call) {
            return Result::fail('NOT_FOUND', 'Unknown call.');
        }
        $code = strtoupper($code);
        if (! isset(KeywordValueService::getEnum('CALL_DISPOSITION')[$code])) {
            return Result::fail('INVALID_DISPOSITION', 'Unknown disposition.');
        }
        $call->update(['disposition' => $code, 'disposition_remark' => $remark ? mb_substr(strip_tags($remark), 0, 500) : null]);
        if ($call->ref_type && ($model = $this->chat->resolve($call->ref_type, (int) $call->ref_id))) {
            $this->chat->event($model, 'CALL_DISPOSED', "Call #{$call->id}: ".(KeywordValueService::getEnum('CALL_DISPOSITION')[$code] ?? $code).($remark ? " — {$remark}" : ''), ['call_id' => $call->id], $actorId);
        }

        return Result::ok();
    }

    /** @param array{person?: string, agent?: int, from?: string, to?: string, ref_type?: string, ref_id?: int, status?: string, call?: int} $filters */
    public function calls(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return CommCall::query()->with('agent')
            ->when($filters['call'] ?? null, fn ($q, $id) => $q->whereKey((int) $id))
            ->when($filters['person'] ?? null, fn ($q, $p) => $q->where('person_code', $p))
            ->when($filters['agent'] ?? null, fn ($q, $a) => $q->where('agent_user_id', (int) $a))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', strtoupper($s)))
            ->when($filters['ref_type'] ?? null, fn ($q, $t) => $q->where('ref_type', strtoupper($t))->when($filters['ref_id'] ?? null, fn ($w, $i) => $w->where('ref_id', (int) $i)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('started_at', '>=', Carbon::parse($d)))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('started_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest('id')->paginate($perPage)->withQueryString();
    }

    /** Completed calls past the grace period without a recording (TEL-05): flag + Alert. */
    public function flagMissingRecordings(): int
    {
        $grace = (int) $this->settings->get('telephony.recording_grace_minutes', 30);
        $count = 0;
        CommCall::query()->where('status', 'COMPLETED')->whereNull('recording_doc_id')->where('recording_missing', false)
            ->where('ended_at', '<', now()->subMinutes($grace))->chunkById(100, function ($calls) use (&$count) {
                foreach ($calls as $call) {
                    if ($this->pullRecording($call->id)->ok) {
                        continue;
                    }
                    $call->update(['recording_missing' => true]);
                    $count++;
                }
            });
        if ($count > 0) {
            $ops = User::permission('UTL_COMM_VIEW')->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->notify->toMany($ops)->kind('A')->about('SYSTEM')->actor(null)->notifySelf()
                ->title("{$count} call recording(s) missing past the grace period")->data(['severity' => 'warning'])->send();
        }

        return $count;
    }

    /** "+9198XXXXXX12" — the only form a browser ever gets when masking is on (TEL-02). */
    public function display(?string $number): string
    {
        return $this->settings->flag('telephony.mask') ? $this->contacts->mask($number) : (string) $number;
    }

    /** Inbound call (TEL-08): match the caller to a Person, never drop it. */
    private function inbound(array $event): Result
    {
        $from = $this->contacts->normalise((string) ($event['from'] ?? ''), 'SMS');
        $call = CommCall::create([
            'direction' => 'IN', 'from_number' => $from, 'to_number' => $event['to'] ?? null, 'status' => strtoupper((string) ($event['status'] ?? 'RINGING')),
            'started_at' => now(), 'vendor_call_id' => $event['vendor_call_id'], 'driver' => $this->driver()->name(),
            'person_code' => $from ? $this->contacts->personByAddress($from, 'SMS') : null,
        ]);
        $ops = User::permission('UTL_COMM_CALL')->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->notify->toMany($ops)->kind('A')->about('CALL', $call->id)->actor(null)->notifySelf()
            ->title('Incoming call from '.$this->contacts->mask($from).($call->person_code ? " ({$call->person_code})" : ''))->data(['severity' => 'info'])->send();

        return Result::ok(['call_id' => $call->id, 'person_code' => $call->person_code]);
    }

    private function driver(): TelephonyDriver
    {
        $driver = $this->drivers->for('TELEPHONY');
        assert($driver instanceof TelephonyDriver);

        return $driver;
    }
}
