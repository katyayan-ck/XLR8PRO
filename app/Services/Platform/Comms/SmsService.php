<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Models\Comms\CommOutbox;
use App\Models\User;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Templates\TemplateService;
use App\Support\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SMS service (FRS §14): E.164, consent, DLT mapping, promotional window, OTP with hashed codes.
 * Body always from the Template Engine; raw only with `raw: true` + UTL_COMM_SMS_RAW.
 */
final class SmsService
{
    public const OTP_TEMPLATE = 'otp.sms';

    public function __construct(
        private readonly TemplateService $templates,
        private readonly OutboxService $outbox,
        private readonly ContactService $contacts,
        private readonly SettingsService $settings,
        private readonly ChatService $chat,
    ) {}

    /**
     * @param  array{to: int|string, template?: string, vars?: array<string, mixed>, from?: string, raw?: bool, text?: string, ref_type?: string, ref_id?: int, idempotency_key?: string, locale?: string}  $options
     */
    public function send(array $options, ?int $actorId = null): Result
    {
        $prepared = $this->prepare($options, $actorId);
        if (! $prepared->ok) {
            return $prepared;
        }
        [$row, $category] = [$prepared->get('row'), $prepared->get('category')];
        if (isset($row['status'])) {
            return $this->outbox->skip($row, $row['status'], $row['error']);
        }
        $queued = $this->outbox->queue($row);
        if ($queued->ok && ! $queued->get('duplicate') && ! $queued->get('switched_off')) {
            if ($row['template_code']) {
                $this->templates->recordUse($row['template_code'], 'SMS', (int) $row['template_version']);
            }
            if ($row['ref_type'] && ($model = $this->chat->resolve($row['ref_type'], (int) $row['ref_id']))) {
                $this->chat->event($model, 'SMS_SENT', 'SMS to '.$this->contacts->mask($row['to_address']).($row['template_code'] ? " ({$row['template_code']})" : ''), ['outbox_id' => $queued->get('outbox_id')]);
            }
        }

        return Result::ok($queued->data + ['sent' => 1, 'category' => $category]);
    }

    /**
     * Generate, store (hashed) and send an OTP (FRS §14.3, SMS-05): the code never reaches the
     * outbox or the queue — the message is delivered inline with a transient payload.
     */
    public function otp(string $personCode, string $purpose = 'LOGIN', ?int $ttl = null): Result
    {
        $purpose = strtoupper($purpose);
        $ttl ??= (int) $this->settings->get('sms.otp_ttl_seconds', 300);
        $max = (int) $this->settings->get('sms.otp_max_per_15min', 3);
        $recent = DB::table('xlr8_comm_otp')->where('person_code', $personCode)->where('purpose', $purpose)->where('created_at', '>=', now()->subMinutes(15))->count();
        if ($recent >= $max) {
            return Result::fail('RATE_LIMITED', "At most {$max} codes every 15 minutes.");
        }
        $resolved = $this->contacts->resolve($personCode, 'SMS');
        if ($resolved['address'] === null) {
            return Result::fail('INVALID_MSISDN', 'No valid mobile number for this person.');
        }
        $code = (string) random_int(100000, 999999);
        $render = $this->templates->render(self::OTP_TEMPLATE, 'SMS', ['code' => $code, 'minutes' => (string) max(1, intdiv($ttl, 60)), 'purpose' => strtolower($purpose)]);
        if (! $render->ok) {
            return $render;
        }

        $masked = $this->contacts->mask($resolved['address']);
        $queued = $this->outbox->queue([
            'channel' => 'SMS', 'to_address' => $resolved['address'], 'to_person_code' => $resolved['person_code'],
            'envelope' => ['header' => $render->get('dlt')['header'] ?? null, 'purpose' => $purpose],
            'body_preview' => "OTP for {$purpose} to {$masked}", 'payload' => ['purpose' => $purpose, 'otp' => true],
            'template_code' => self::OTP_TEMPLATE, 'template_version' => $render->get('template_version'), 'category' => 'OTP',
            'idempotency_key' => 'otp.'.$personCode.'.'.$purpose.'.'.now()->format('YmdHisv').'.'.random_int(100, 999),
        ], dispatch: false);
        $outbox = CommOutbox::query()->find($queued->get('outbox_id'));
        $sent = $this->outbox->deliver($outbox, ['to' => $resolved['address'], 'text' => $render->get('text'), 'header' => $render->get('dlt')['header'] ?? null, 'dlt' => $render->get('dlt'), 'otp' => true]);

        DB::table('xlr8_comm_otp')->insert([
            'person_code' => $personCode, 'purpose' => $purpose, 'destination_masked' => $masked, 'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($ttl), 'outbox_id' => $outbox->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $sent->ok ? Result::ok(['outbox_id' => $outbox->id, 'destination' => $masked, 'expires_in' => $ttl]) : $sent;
    }

    /** Check the latest live OTP (5 wrong tries burn it). */
    public function verify(string $personCode, string $purpose, string $code): Result
    {
        $otp = DB::table('xlr8_comm_otp')->where('person_code', $personCode)->where('purpose', strtoupper($purpose))
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp) {
            return Result::fail('OTP_EXPIRED', 'No valid code; request a new one.');
        }
        if ($otp->attempts >= 5) {
            return Result::fail('OTP_LOCKED', 'Too many wrong attempts; request a new code.');
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            DB::table('xlr8_comm_otp')->where('id', $otp->id)->increment('attempts');

            return Result::fail('OTP_INVALID', 'The code is not correct.');
        }
        DB::table('xlr8_comm_otp')->where('id', $otp->id)->update(['used_at' => now(), 'updated_at' => now()]);

        return Result::ok();
    }

    /** Inbound SMS (SMS-08): STOP revokes consent, suppresses and acknowledges. */
    public function inbound(string $from, string $text): Result
    {
        $address = $this->contacts->normalise($from, 'SMS');
        if ($address === null) {
            return Result::fail('INVALID_MSISDN', 'Unknown sender.');
        }
        $keyword = strtoupper(trim(strtok($text, " \n") ?: ''));
        if (in_array($keyword, ['STOP', 'UNSUBSCRIBE', 'OPTOUT'], true)) {
            $personCode = $this->contacts->personByAddress($address, 'SMS');
            if ($personCode) {
                $this->contacts->setConsent($personCode, 'SMS', false, 'INBOUND_STOP');
            }
            $this->contacts->suppress('SMS', $address, 'STOP');
            $ack = $this->templates->render('sms.stop.ack', 'SMS', []);
            if ($ack->ok) {
                $this->outbox->queue(['channel' => 'SMS', 'to_address' => $address, 'to_person_code' => $personCode, 'body_preview' => $ack->get('text'),
                    'payload' => ['to' => $address, 'text' => $ack->get('text'), 'dlt' => $ack->get('dlt')], 'template_code' => 'sms.stop.ack',
                    'template_version' => $ack->get('template_version'), 'category' => 'TRANSACTIONAL', 'idempotency_key' => 'sms.stop.ack.'.$address.'.'.now()->format('YmdHi')]);
            }

            return Result::ok(['action' => 'OPTED_OUT']);
        }

        return Result::ok(['action' => 'IGNORED']);
    }

    public function status(int $outboxId): Result
    {
        return $this->outbox->status($outboxId);
    }

    /** Validate and build the outbox row (shared by send and campaigns). */
    private function prepare(array $options, ?int $actorId): Result
    {
        $resolved = $this->contacts->resolve($options['to'] ?? '', 'SMS');
        if ($resolved['address'] === null) {
            return Result::fail('INVALID_MSISDN', 'The destination is not a valid mobile number.');
        }

        if (! empty($options['raw'])) {
            if (! User::query()->find($actorId ?? auth(backpack_guard_name())->id() ?? auth()->id())?->can('UTL_COMM_SMS_RAW')) {
                return Result::fail('FORBIDDEN', 'Raw SMS needs the UTL_COMM_SMS_RAW permission.');
            }
            $rendered = ['text' => trim((string) ($options['text'] ?? '')), 'template_code' => null, 'template_version' => null, 'category' => 'OPERATIONAL', 'dlt' => []];
            if ($rendered['text'] === '') {
                return Result::fail('INVALID', 'A raw SMS needs text.');
            }
        } else {
            if (empty($options['template'])) {
                return Result::fail('TEMPLATE_REQUIRED', 'SMS copy must come from a template.');
            }
            $render = $this->templates->render((string) $options['template'], 'SMS', (array) ($options['vars'] ?? []), $options['locale'] ?? null);
            if (! $render->ok) {
                return $render;
            }
            $rendered = $render->data;
            // SMS-04: Indian transactional traffic needs its DLT mapping
            if (in_array($rendered['category'], ['TRANSACTIONAL', 'PROMOTIONAL', 'OTP'], true) && $this->settings->flag('sms.dlt_required')
                && (empty($rendered['dlt']['template_id']) || empty($rendered['dlt']['entity_id']) || empty($rendered['dlt']['header']))) {
                return Result::fail('DLT_MAP_MISSING', "Template {$rendered['template_code']} has no DLT template / entity / header.");
            }
        }

        $row = [
            'channel' => 'SMS', 'to_address' => $resolved['address'], 'to_person_code' => $resolved['person_code'],
            'envelope' => ['header' => $options['from'] ?? ($rendered['dlt']['header'] ?? $this->settings->get('sms.default_header'))],
            'body_preview' => mb_substr((string) $rendered['text'], 0, 500),
            'payload' => ['to' => $resolved['address'], 'text' => $rendered['text'], 'header' => $options['from'] ?? ($rendered['dlt']['header'] ?? null), 'dlt' => $rendered['dlt']],
            'template_code' => $rendered['template_code'], 'template_version' => $rendered['template_version'], 'category' => $rendered['category'],
            'ref_type' => isset($options['ref_type']) ? strtoupper((string) $options['ref_type']) : null, 'ref_id' => $options['ref_id'] ?? null,
            'idempotency_key' => $options['idempotency_key'] ?? null,
        ];

        // SMS-02 consent, SMS-09 window, hard suppression
        if ($this->contacts->suppressed('SMS', $resolved['address'])) {
            $row += ['status' => 'SUPPRESSED', 'error' => 'Number is suppressed.'];
        } elseif ($rendered['category'] === 'PROMOTIONAL' && ! $this->contacts->consented($resolved['person_code'], 'SMS')) {
            return Result::fail('CONSENT_DENIED', 'No SMS consent for promotional messages.');
        } elseif ($rendered['category'] === 'PROMOTIONAL' && ! $this->contacts->withinWindow((string) $this->settings->get('comms.promo_window', ''))) {
            return Result::fail('OUTSIDE_WINDOW', 'Promotional SMS may only go out in the configured window.');
        }

        return Result::ok(['row' => $row, 'category' => $rendered['category']]);
    }
}
