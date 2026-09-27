<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Models\Comms\CommOutbox;
use App\Models\Utilities\Docs\Document;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * EMAIL through the Laravel mailer (config/mail.php) — the only place in the app allowed to use
 * `Mail::` (FRS §13.6). Attachments are read from Docs; `.ics` invites are attached as text/calendar.
 */
final class LaravelMailDriver implements ChannelDriver
{
    public function name(): string
    {
        return 'laravel';
    }

    public function send(CommOutbox $outbox, array $payload): array
    {
        try {
            $sent = Mail::html((string) ($payload['html'] ?? nl2br(e((string) ($payload['text'] ?? '')))), function (Message $m) use ($payload, $outbox) {
                [$fromAddress, $fromName] = $payload['from'];
                $m->from($fromAddress, $fromName);
                $m->to($payload['to']);
                if (! empty($payload['cc'])) {
                    $m->cc($payload['cc']);
                }
                if (! empty($payload['bcc'])) {
                    $m->bcc($payload['bcc']);
                }
                if (! empty($payload['reply_to'])) {
                    $m->replyTo($payload['reply_to']);
                }
                $m->subject((string) ($payload['subject'] ?? ''));
                foreach ((array) ($payload['attachments'] ?? []) as $attachment) {
                    if (isset($attachment['ics'])) {
                        $m->attachData((string) $attachment['ics'], $attachment['as'] ?? 'invite.ics', ['mime' => 'text/calendar; charset=UTF-8; method=REQUEST']);

                        continue;
                    }
                    $media = Document::query()->find($attachment['doc_id'] ?? 0)?->media()->first();
                    if ($media && is_file($media->getPath())) {
                        $m->attach($media->getPath(), ['as' => $attachment['as'] ?? $media->file_name, 'mime' => $media->mime_type]);
                    }
                }
                $headers = $m->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('X-Outbox-Id', (string) $outbox->id);
                foreach ((array) ($payload['headers'] ?? []) as $name => $value) {
                    $headers->addTextHeader((string) $name, (string) $value);
                }
            });

            return ['ok' => true, 'provider_message_id' => $sent?->getMessageId(), 'delivered' => false];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 450), 'retryable' => true];
        }
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['attachments', 'ics'], true);
    }
}
