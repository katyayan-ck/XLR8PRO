<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Events\Platform\OutboxAccepted;
use App\Jobs\Platform\SendOutboxMessage;
use App\Models\Comms\CommOutbox;
use App\Services\Platform\Comms\Drivers\ChannelDriver;
use App\Services\Platform\Comms\Drivers\DriverRegistry;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Outbox (FRS Part B laws 2, 3, 8, 10): every send writes a row first, then a job talks to the
 * driver. Idempotency is local (unique key) whatever the vendor supports.
 */
final class OutboxService
{
    public function __construct(private readonly DriverRegistry $drivers, private readonly SettingsService $settings) {}

    /**
     * Write the row (or return the existing one for the same idempotency key) and queue delivery.
     *
     * @param  array<string, mixed>  $row  outbox columns; `payload` is the channel message
     * @return Result data: {outbox_id, status, duplicate}
     */
    public function queue(array $row, bool $dispatch = true): Result
    {
        // DEC-091: a channel switched off on Settings → Communication is recorded as SUPPRESSED, not sent. OTPs are
        // exempt so sign-in keeps working.
        if (($row['category'] ?? null) !== 'OTP' && ! $this->channelEnabled((string) $row['channel'])) {
            $skipped = $this->skip($row, 'SUPPRESSED', 'The '.strtolower((string) $row['channel']).' channel is switched off in Settings.');

            return Result::ok($skipped->data + ['duplicate' => false, 'switched_off' => true]);
        }

        $key = (string) ($row['idempotency_key'] ?? '');
        if ($key === '') {
            $key = strtolower($row['channel']).'.'.hash('sha256', json_encode([$row['channel'], $row['to_address'] ?? null, $row['template_code'] ?? null, $row['payload'] ?? null, $row['ref_type'] ?? null, $row['ref_id'] ?? null]));
        }
        $existing = CommOutbox::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return Result::ok(['outbox_id' => $existing->id, 'status' => $existing->status, 'duplicate' => true, 'provider_message_id' => $existing->provider_message_id]);
        }

        $row['idempotency_key'] = $key;
        $row['actor_id'] ??= auth(backpack_guard_name())->id() ?? auth()->id();
        $row['driver'] ??= $this->drivers->for($row['channel'])->name();
        $outbox = CommOutbox::create(['status' => 'QUEUED'] + $row);
        if ($dispatch) {
            SendOutboxMessage::dispatch($outbox->id)->afterCommit();
        }

        return Result::ok(['outbox_id' => $outbox->id, 'status' => 'QUEUED', 'duplicate' => false]);
    }

    /** Settings → Communication global switch for a channel (EMAIL → comms.enabled.mail, SMS, WHATSAPP, PUSH). */
    public function channelEnabled(string $channel): bool
    {
        $key = ['EMAIL' => 'mail', 'SMS' => 'sms', 'WHATSAPP' => 'whatsapp', 'PUSH' => 'push'][strtoupper($channel)] ?? null;

        return $key === null || (bool) $this->settings->get('comms.enabled.'.$key, true);
    }

    /** Record a row that is not sent (suppressed, consent denied …) so the audit is complete. */
    public function skip(array $row, string $status, string $reason): Result
    {
        $row['idempotency_key'] = ($row['idempotency_key'] ?? null) ?: strtolower($row['channel']).'.skip.'.hash('sha256', json_encode($row).microtime());
        $outbox = CommOutbox::query()->firstOrCreate(['idempotency_key' => $row['idempotency_key']], $row + ['status' => $status, 'error' => $reason]);

        return Result::ok(['outbox_id' => $outbox->id, 'status' => $outbox->status, 'sent' => 0, 'reason' => $reason]);
    }

    /**
     * Deliver one row through its driver (the job calls this; OTP calls it inline with a transient
     * payload that is never stored). Primary failure retries once on the failover driver (SMS).
     *
     * @param  array<string, mixed>|null  $transientPayload
     */
    public function deliver(CommOutbox $outbox, ?array $transientPayload = null): Result
    {
        if (! in_array($outbox->status, ['QUEUED', 'RETRY'], true)) {
            return Result::ok(['status' => $outbox->status]);
        }
        $payload = $transientPayload ?? (array) $outbox->payload;
        $driver = $this->drivers->for($outbox->channel, $outbox->driver);
        $result = $driver instanceof ChannelDriver ? $driver->send($outbox, $payload) : ['ok' => false, 'error' => 'No channel driver.'];

        if (! $result['ok'] && ($result['retryable'] ?? false) && ($failover = $this->drivers->failover($outbox->channel))) {
            $result = $failover->send($outbox, $payload);
            $outbox->driver = $failover->name();
        }

        $attempts = $outbox->attempts + 1;
        if ($result['ok']) {
            $outbox->forceFill([
                'status' => ($result['delivered'] ?? false) ? 'DELIVERED' : 'SENT', 'attempts' => $attempts, 'error' => null,
                'provider_message_id' => $result['provider_message_id'] ?? null, 'units' => $result['units'] ?? null,
                'sent_at' => now(), 'delivered_at' => ($result['delivered'] ?? false) ? now() : null,
            ])->save();
            OutboxAccepted::dispatch($outbox->id, $outbox->channel, $outbox->status);

            return Result::ok(['status' => $outbox->status, 'provider_message_id' => $outbox->provider_message_id]);
        }

        $final = ! ($result['retryable'] ?? false) || $attempts >= (int) $this->settings->get('comms.max_attempts', 3);
        $outbox->forceFill(['status' => $final ? 'FAILED' : 'RETRY', 'attempts' => $attempts, 'error' => mb_substr((string) ($result['error'] ?? 'Send failed'), 0, 500)])->save();

        return Result::fail($final ? 'SEND_FAILED' : 'RETRY', (string) $outbox->error, ['status' => $outbox->status]);
    }

    /** Vendor-normalised status (EML-08, SMS-07). */
    public function status(int $outboxId): Result
    {
        $outbox = CommOutbox::query()->find($outboxId);

        return $outbox ? Result::ok([
            'status' => $outbox->status, 'provider_message_id' => $outbox->provider_message_id, 'attempts' => $outbox->attempts,
            'error' => $outbox->error, 'sent_at' => $outbox->sent_at?->toIso8601String(), 'delivered_at' => $outbox->delivered_at?->toIso8601String(),
        ]) : Result::fail('NOT_FOUND', 'Outbox row not found.');
    }

    /** Clone a row with the same snapshot and a new idempotency suffix (EML-09) — works after a driver swap. */
    public function resend(int $outboxId, ?int $actorId = null): Result
    {
        $outbox = CommOutbox::query()->find($outboxId);
        if (! $outbox) {
            return Result::fail('NOT_FOUND', 'Outbox row not found.');
        }
        if ($outbox->template_code === 'otp.sms') {
            return Result::fail('NOT_RESENDABLE', 'OTP messages are never resent; request a new OTP.');
        }
        $copies = CommOutbox::query()->where('parent_outbox_id', $outbox->parent_outbox_id ?? $outbox->id)->count();

        return $this->queue([
            'channel' => $outbox->channel, 'to_address' => $outbox->to_address, 'to_person_code' => $outbox->to_person_code,
            'envelope' => $outbox->envelope, 'subject' => $outbox->subject, 'body_preview' => $outbox->body_preview, 'payload' => $outbox->payload,
            'template_code' => $outbox->template_code, 'template_version' => $outbox->template_version, 'category' => $outbox->category,
            'ref_type' => $outbox->ref_type, 'ref_id' => $outbox->ref_id, 'parent_outbox_id' => $outbox->parent_outbox_id ?? $outbox->id,
            'idempotency_key' => mb_substr($outbox->idempotency_key, 0, 170).'.resend'.($copies + 1),
            'actor_id' => $actorId,
        ]);
    }

    /** Webhook status update by provider message id (delivery receipts, bounces). */
    public function markByProviderId(string $channel, string $providerMessageId, string $status, ?string $error = null): ?CommOutbox
    {
        $outbox = CommOutbox::query()->where('channel', strtoupper($channel))->where('provider_message_id', $providerMessageId)->first();
        if ($outbox) {
            $outbox->forceFill(array_filter([
                'status' => strtoupper($status), 'error' => $error,
                'delivered_at' => in_array(strtoupper($status), ['DELIVERED', 'READ'], true) ? now() : null,
            ], fn ($v) => $v !== null))->save();
        }

        return $outbox;
    }

    /** Audit query (law 8): by person, entity, template, channel, status. */
    public function search(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return CommOutbox::query()
            ->when(($filters['channel'] ?? '') !== '', fn ($q) => $q->where('channel', strtoupper($filters['channel'])))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', strtoupper($filters['status'])))
            ->when(($filters['template'] ?? '') !== '', fn ($q) => $q->where('template_code', $filters['template']))
            ->when(($filters['person'] ?? '') !== '', fn ($q) => $q->where('to_person_code', $filters['person']))
            ->when(($filters['ref_type'] ?? '') !== '', fn ($q) => $q->where('ref_type', strtoupper($filters['ref_type']))->when(($filters['ref_id'] ?? '') !== '', fn ($w) => $w->where('ref_id', (int) $filters['ref_id'])))
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where(fn ($w) => $w->where('to_address', 'like', '%'.$filters['q'].'%')->orWhere('subject', 'like', '%'.$filters['q'].'%')))
            ->latest('id')->paginate($perPage)->withQueryString();
    }

    /** @return array<string, int> status counts for the last N days */
    public function stats(int $days = 7): array
    {
        return CommOutbox::query()->where('created_at', '>=', now()->subDays($days))->groupBy('status')
            ->selectRaw('status, count(*) as n')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }
}
