<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notify → channel wrappers (FRS §17): Notify stays the orchestrator; EMAIL / SMS / WHATSAPP
 * options are forwarded unchanged. A bare `true` uses the `notify.generic` template of the channel.
 */
final class CommsRouter
{
    public const GENERIC_TEMPLATE = 'notify.generic';

    public function __construct(
        private readonly EmailService $email,
        private readonly SmsService $sms,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @param  list<int>  $recipients  user ids
     * @param  array<string, mixed>  $options  channel options + subject / text / ref / idempotency_key from Notify
     */
    public function fromNotify(string $channel, array $recipients, array $options): void
    {
        $channel = strtoupper($channel);
        $vars = (array) ($options['vars'] ?? []) + ['title' => (string) ($options['subject'] ?? ''), 'body' => (string) ($options['text'] ?? ''), 'link' => (string) ($options['link'] ?? config('app.url'))];
        $base = [
            'template' => $options['template'] ?? self::GENERIC_TEMPLATE, 'vars' => $vars,
            'ref_type' => $options['ref_type'] ?? null, 'ref_id' => $options['ref_id'] ?? null,
        ];

        if ($channel === 'EMAIL') {
            // one send, one outbox row (acceptance #9): all audience users in To unless the caller set `to`
            $result = $this->email->send($base + [
                'to' => $options['to'] ?? $recipients, 'cc' => $options['cc'] ?? [], 'bcc' => $options['bcc'] ?? [],
                'from' => $options['from'] ?? 'default', 'reply_to' => $options['reply_to'] ?? null, 'attach' => $options['attach'] ?? [],
                'idempotency_key' => $options['idempotency_key'] ?? null, 'locale' => $options['locale'] ?? null,
            ]);
            $this->log($channel, $result->ok, $result->message);

            return;
        }

        foreach ((array) ($options['to'] ?? $recipients) as $recipient) {
            $personCode = is_int($recipient) ? User::query()->whereKey($recipient)->value('person_code') : $recipient;
            if (! $personCode) {
                continue;
            }
            $args = $base + ['to' => $personCode, 'idempotency_key' => isset($options['idempotency_key']) ? $options['idempotency_key'].'.'.$personCode : null];
            $result = $channel === 'SMS' ? $this->sms->send($args) : $this->whatsapp->send($args);
            $this->log($channel, $result->ok, $result->message);
        }
    }

    private function log(string $channel, bool $ok, string $message): void
    {
        if (! $ok) {
            Log::warning("[Notify→{$channel}] not sent: {$message}");
        }
    }
}
