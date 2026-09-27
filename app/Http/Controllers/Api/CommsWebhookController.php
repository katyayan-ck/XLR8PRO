<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comms\CommTemplateVersion;
use App\Services\Platform\Comms\ContactService;
use App\Services\Platform\Comms\OutboxService;
use App\Services\Platform\Comms\SmsService;
use App\Services\Platform\Comms\TelephonyService;
use App\Services\Platform\Comms\WhatsAppService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inbound comms webhooks (FRS WA-03, EML-07, SMS-08, TEL-03/04). Signed with HMAC-SHA256 of the
 * raw body (header X-Signature, secret `comms.webhook_secret`) and idempotent on `event_id`.
 * Payloads are the platform's normalised shape; a vendor driver maps its own format onto it.
 */
class CommsWebhookController extends Controller
{
    public function handle(Request $request, string $channel): JsonResponse
    {
        $channel = strtoupper($channel);
        if (! in_array($channel, ['EMAIL', 'SMS', 'WHATSAPP', 'TELEPHONY'], true)) {
            return response()->json(['ok' => false, 'code' => 'UNKNOWN_CHANNEL'], 404);
        }
        if (! $this->signed($request)) {
            return response()->json(['ok' => false, 'code' => 'BAD_SIGNATURE'], 401);
        }
        $event = $request->json()->all();
        $eventId = (string) ($event['event_id'] ?? '');
        if ($eventId === '') {
            return response()->json(['ok' => false, 'code' => 'EVENT_ID_REQUIRED'], 422);
        }
        try {
            DB::table('xlr8_comm_webhook_event')->insert(['channel' => $channel, 'event_id' => mb_substr($eventId, 0, 150), 'payload' => json_encode($this->redact($event)), 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $result = match ($channel) {
            'EMAIL' => $this->email($event),
            'SMS' => $this->sms($event),
            'WHATSAPP' => $this->whatsapp($event),
            'TELEPHONY' => app(TelephonyService::class)->event($event),
        };
        DB::table('xlr8_comm_webhook_event')->where('channel', $channel)->where('event_id', $eventId)->update(['result' => mb_substr(($result->ok ? 'OK ' : 'FAIL ').$result->message, 0, 250)]);

        return response()->json($result->toArray(), $result->ok ? 200 : 422);
    }

    private function email(array $event): Result
    {
        $type = strtolower((string) ($event['type'] ?? ''));
        $status = ['delivered' => 'DELIVERED', 'bounced' => 'BOUNCED', 'complained' => 'BOUNCED', 'opened' => 'READ'][$type] ?? null;
        if ($status === null) {
            return Result::fail('UNKNOWN_EVENT', "Unknown email event {$type}.");
        }
        $outbox = app(OutboxService::class)->markByProviderId('EMAIL', (string) ($event['message_id'] ?? ''), $status, $type === 'delivered' || $type === 'opened' ? null : $type);
        // EML-07: hard bounce / complaint → suppression
        if (in_array($type, ['bounced', 'complained'], true) && ! empty($event['address'])) {
            app(ContactService::class)->suppress('EMAIL', (string) $event['address'], strtoupper($type === 'bounced' ? 'BOUNCE' : 'COMPLAINT'));
        }

        return $outbox ? Result::ok() : Result::ok([], 'No matching outbox row');
    }

    private function sms(array $event): Result
    {
        if (strtolower((string) ($event['type'] ?? '')) === 'inbound') {
            return app(SmsService::class)->inbound((string) ($event['from'] ?? ''), (string) ($event['text'] ?? ''));
        }
        $outbox = app(OutboxService::class)->markByProviderId('SMS', (string) ($event['message_id'] ?? ''), strtoupper((string) ($event['status'] ?? 'DELIVERED')));

        return $outbox ? Result::ok() : Result::ok([], 'No matching outbox row');
    }

    private function whatsapp(array $event): Result
    {
        $wa = app(WhatsAppService::class);

        return match (strtolower((string) ($event['type'] ?? ''))) {
            'message' => $wa->inbound((array) ($event['message'] ?? []) + ['id' => $event['message']['id'] ?? $event['event_id']]),
            'status' => $wa->status((string) ($event['message_id'] ?? ''), (string) ($event['status'] ?? 'DELIVERED')),
            // WA-09: provider template approval / rejection
            'template_status' => $this->templateStatus((string) ($event['provider_template_id'] ?? ''), strtoupper((string) ($event['status'] ?? ''))),
            default => Result::fail('UNKNOWN_EVENT', 'Unknown WhatsApp event.'),
        };
    }

    private function templateStatus(string $providerTemplateId, string $status): Result
    {
        $version = CommTemplateVersion::query()->where('provider_template_id', $providerTemplateId)->where('status', 'PENDING_PROVIDER')->latest('id')->first();
        if (! $version) {
            return Result::ok([], 'No pending template');
        }
        $version->update(['status' => $status === 'APPROVED' ? 'APPROVED' : 'REJECTED']);

        return Result::ok();
    }

    /** HMAC over the raw body; unsigned only while the secret is blank in local / testing. */
    private function signed(Request $request): bool
    {
        $secret = (string) app(SettingsService::class)->get('comms.webhook_secret', '');
        if ($secret === '') {
            return app()->environment(['local', 'testing']);
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Signature'));
    }

    /** Never keep media bytes or OTP-like codes in the webhook log. */
    private function redact(array $event): array
    {
        array_walk_recursive($event, function (&$value, $key) {
            if ($key === 'media_base64') {
                $value = '[media '.strlen((string) $value).' b64 chars]';
            }
        });

        return $event;
    }
}
