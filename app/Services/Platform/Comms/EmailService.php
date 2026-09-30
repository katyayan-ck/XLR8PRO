<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Models\User;
use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Templates\TemplateService;
use App\Support\Result;

/**
 * Email service (FRS §13). Option shape is locked (§13.3): to / cc / bcc / from / reply_to /
 * template / vars / attach / ref_type / ref_id / idempotency_key / locale, or raw subject+html+text
 * with `raw: true` (internal only). One send = one outbox row, whatever the recipient count.
 */
final class EmailService
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly OutboxService $outbox,
        private readonly ContactService $contacts,
        private readonly SettingsService $settings,
        private readonly ChatService $chat,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Result data: {outbox_id, status, sent, suppressed}
     */
    public function send(array $options): Result
    {
        $from = $this->identity($options['from'] ?? 'default');
        if ($from === null) {
            return Result::fail('FROM_NOT_ALLOWED', 'The From identity is not in the allowed list.');
        }

        $suppressed = [];
        $personCodes = [];
        $collect = function (array $list) use (&$suppressed, &$personCodes): array {
            $out = [];
            foreach ($list as $recipient) {
                $resolved = $this->contacts->resolve($recipient, 'EMAIL');
                if ($resolved['address'] === null) {
                    continue;
                }
                if ($this->contacts->suppressed('EMAIL', $resolved['address'])) {
                    $suppressed[] = $resolved['address'];

                    continue;
                }
                $personCodes[] = $resolved['person_code'];
                $out[] = $resolved['address'];
            }

            return array_values(array_unique($out));
        };
        $to = $collect((array) ($options['to'] ?? []));
        $cc = $collect((array) ($options['cc'] ?? []));
        $bcc = $collect((array) ($options['bcc'] ?? []));

        // compose the copy
        if (! empty($options['raw'])) {
            $rendered = ['subject' => (string) ($options['subject'] ?? ''), 'html' => $options['html'] ?? null, 'text' => $options['text'] ?? null, 'template_code' => null, 'template_version' => null, 'category' => 'OPERATIONAL', 'pii' => []];
            if ($rendered['subject'] === '' || ($rendered['html'] === null && $rendered['text'] === null)) {
                return Result::fail('INVALID', 'A raw email needs a subject and a body.');
            }
        } else {
            if (empty($options['template'])) {
                return Result::fail('TEMPLATE_REQUIRED', 'Customer-facing email must use a template (or raw: true for internal mail).');
            }
            $render = $this->templates->render((string) $options['template'], 'EMAIL', (array) ($options['vars'] ?? []), $options['locale'] ?? null);
            if (! $render->ok) {
                return $render;
            }
            $rendered = $render->data;
        }

        // attachments from Docs (EML-04): a missing doc fails before any vendor call
        $attachments = [];
        foreach ((array) ($options['attach'] ?? []) as $item) {
            $item = is_array($item) ? $item : ['doc_id' => (int) $item];
            if (isset($item['ics'])) {
                $attachments[] = ['ics' => (string) $item['ics'], 'as' => $item['as'] ?? 'invite.ics'];

                continue;
            }
            $doc = Document::query()->with('media')->find((int) ($item['doc_id'] ?? 0));
            if (! $doc || $doc->media->isEmpty()) {
                return Result::fail('ATTACHMENT_MISSING', 'Document #'.($item['doc_id'] ?? '?').' has no file.');
            }
            $attachments[] = ['doc_id' => $doc->id, 'as' => $item['as'] ?? $doc->media->first()->file_name];
        }

        $refType = isset($options['ref_type']) ? strtoupper((string) $options['ref_type']) : null;
        $row = [
            'channel' => 'EMAIL',
            'to_address' => $to[0] ?? null,
            'to_person_code' => collect($personCodes)->filter()->first(),
            'envelope' => ['from' => $from[0], 'to' => $to, 'cc' => $cc, 'bcc' => $bcc, 'reply_to' => $options['reply_to'] ?? null],
            'subject' => mb_substr((string) $rendered['subject'], 0, 250),
            'body_preview' => $this->preview((string) ($rendered['text'] ?? strip_tags((string) $rendered['html'])), (array) ($rendered['pii'] ?? []), (array) ($options['vars'] ?? [])),
            'template_code' => $rendered['template_code'], 'template_version' => $rendered['template_version'], 'category' => $rendered['category'],
            'ref_type' => $refType, 'ref_id' => $options['ref_id'] ?? null,
            'idempotency_key' => $options['idempotency_key'] ?? null,
        ];

        // EML-02: nobody left after suppression → ok, sent 0
        if ($to === []) {
            return $this->outbox->skip($row, 'SUPPRESSED', $suppressed ? 'All recipients are suppressed.' : 'No valid recipient.');
        }

        // DEC-091: the signature from Settings → Communication ends every e-mail
        $signature = trim((string) $this->settings->get('mail.signature', ''));
        if ($signature !== '') {
            $rendered['html'] = $rendered['html'] !== null ? $rendered['html'].'<br><br>'.nl2br(e($signature)) : null;
            $rendered['text'] = $rendered['text'] !== null ? $rendered['text']."\n\n-- \n".$signature : null;
        }

        $redirect = trim((string) $this->settings->get('mail.redirect_to', ''));
        $row['payload'] = [
            'from' => $from, 'to' => $redirect !== '' ? [$redirect] : $to, 'cc' => $redirect !== '' ? [] : $cc, 'bcc' => $redirect !== '' ? [] : $bcc,
            'reply_to' => $options['reply_to'] ?? null, 'subject' => ($redirect !== '' ? '[to '.implode(', ', $to).'] ' : '').$rendered['subject'],
            'html' => $rendered['html'], 'text' => $rendered['text'], 'attachments' => $attachments,
            'headers' => array_filter(['X-Entity-Ref-Type' => $refType, 'X-Entity-Ref-Id' => $options['ref_id'] ?? null,
                'List-Unsubscribe' => $rendered['category'] === 'PROMOTIONAL' ? '<mailto:'.$from[0].'?subject=unsubscribe>' : null]),
        ];

        $queued = $this->outbox->queue($row);
        if ($queued->ok && ! $queued->get('duplicate') && ! $queued->get('switched_off')) {
            if ($rendered['template_code']) {
                $this->templates->recordUse($rendered['template_code'], 'EMAIL', (int) $rendered['template_version']);
            }
            if ($refType && ($model = $this->chat->resolve($refType, (int) ($options['ref_id'] ?? 0)))) {
                // EML-05: BCC never appears in the timeline
                $this->chat->event($model, 'EMAIL_SENT', "Email \"{$row['subject']}\" to ".implode(', ', array_merge($to, $cc)), ['outbox_id' => $queued->get('outbox_id')]);
            }
        }

        return Result::ok($queued->data + ['sent' => count($to) + count($cc) + count($bcc), 'suppressed' => $suppressed]);
    }

    public function status(int $outboxId): Result
    {
        return $this->outbox->status($outboxId);
    }

    public function resend(int $outboxId, ?int $actorId = null): Result
    {
        return $this->outbox->resend($outboxId, $actorId);
    }

    /**
     * An alias (`quotes`) or full address, checked against the allow-list (EML-01).
     *
     * @return array{0: string, 1: ?string}|null [address, name]
     */
    public function identity(string $fromOrAlias): ?array
    {
        $identities = (array) $this->settings->get('mail.identities', []);
        // DEC-091: the default sender is Settings → Communication → From (name falls back to the dealership name), else .env
        $fromAddress = trim((string) $this->settings->get('mail.smtp.from_address', '')) ?: config('mail.from.address');
        $fromName = trim((string) $this->settings->get('mail.smtp.from_name', '')) ?: (trim((string) $this->settings->get('dealership.name', '')) ?: config('mail.from.name'));
        $identities['default'] = ($identities['default'] ?? null) ?: trim($fromName.' <'.$fromAddress.'>');
        $value = $identities[$fromOrAlias] ?? $fromOrAlias;
        if (! preg_match('/^\s*(?:"?([^"<]*)"?\s*)?<?([^\s<>]+@[^\s<>]+)>?\s*$/', (string) $value, $m)) {
            return null;
        }
        [$name, $address] = [trim($m[1] ?? '') ?: null, strtolower($m[2])];
        $allowed = collect($identities)->filter()->map(fn ($v) => preg_match('/<?([^\s<>]+@[^\s<>]+)>?\s*$/', (string) $v, $x) ? strtolower($x[1]) : null)
            ->merge(array_map('trim', explode(',', strtolower((string) $this->settings->get('mail.allowed_from', '')))))->filter()->all();

        return in_array($address, $allowed, true) ? [$address, $name] : null;
    }

    /** Body preview with PII variable values masked (FRS §12.2). */
    private function preview(string $text, array $pii, array $vars): string
    {
        foreach ($pii as $name) {
            if (isset($vars[$name]) && is_scalar($vars[$name]) && (string) $vars[$name] !== '') {
                $text = str_replace((string) $vars[$name], '•••', $text);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 500);
    }

    /** Only template senders with UTL_COMM_SEND may send raw internal mail from screens. */
    public function canSendRaw(?int $userId): bool
    {
        return (bool) User::query()->find($userId)?->can('UTL_COMM_SEND');
    }
}
