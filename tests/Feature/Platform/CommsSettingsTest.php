<?php

namespace Tests\Feature\Platform;

use App\Models\Comms\CommOutbox;
use App\Services\Platform\Comms\Drivers\LaravelMailDriver;
use App\Services\Platform\Comms\EmailService;
use App\Services\Platform\Comms\OutboxService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Facades\Email;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Settings → Communication applied (DEC-091, W13 Phase 3): a switched-off channel records SUPPRESSED rows instead of
 * sending (OTPs exempt), the signature ends every e-mail, the default sender comes from the From settings, and the SMTP
 * settings build the mailer at send time.
 */
class CommsSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(SettingsService::class);
        $this->settings->set('mail.driver', 'log');
        $this->settings->set('mail.identities', ['default' => 'Xceler8 <noreply@xceler8.in>']);
    }

    /** @return array<string, mixed> */
    private function raw(): array
    {
        return ['raw' => true, 'to' => ['someone@example.com'], 'subject' => 'Hello', 'text' => 'Body', 'idempotency_key' => 'test.'.uniqid()];
    }

    public function test_a_switched_off_channel_suppresses_instead_of_sending(): void
    {
        $this->settings->set('comms.enabled.mail', false);

        $result = Email::send($this->raw());

        $this->assertTrue($result->ok);
        $this->assertTrue($result->get('switched_off'));
        $this->assertSame('SUPPRESSED', CommOutbox::query()->find($result->get('outbox_id'))->status);

        $this->settings->set('comms.enabled.mail', true);
        $this->assertNotSame('SUPPRESSED', CommOutbox::query()->find(Email::send($this->raw())->get('outbox_id'))->status);
    }

    public function test_otps_are_sent_even_when_sms_is_switched_off(): void
    {
        $this->settings->set('comms.enabled.sms', false);
        $outbox = app(OutboxService::class);

        $otp = $outbox->queue(['channel' => 'SMS', 'to_address' => '+919800000001', 'category' => 'OTP', 'payload' => ['text' => 'x']], false);
        $other = $outbox->queue(['channel' => 'SMS', 'to_address' => '+919800000001', 'category' => 'OPERATIONAL', 'payload' => ['text' => 'y']], false);

        $this->assertSame('QUEUED', $otp->get('status'));
        $this->assertSame('SUPPRESSED', $other->get('status'));
        $this->assertFalse($outbox->channelEnabled('SMS'));
        $this->assertTrue($outbox->channelEnabled('PUSH'));
    }

    public function test_the_signature_ends_every_mail(): void
    {
        $this->settings->set('mail.signature', "Team Bikaner Motors\n+91 99999");

        $payload = CommOutbox::query()->find(Email::send($this->raw())->get('outbox_id'))->payload;

        $this->assertStringEndsWith("\n\n-- \nTeam Bikaner Motors\n+91 99999", $payload['text']);
    }

    public function test_the_default_sender_comes_from_the_from_settings(): void
    {
        $this->settings->set('mail.identities', ['default' => null]);
        $this->settings->set('mail.smtp.from_address', 'sales@bikanermotors.com');
        $this->settings->set('dealership.name', 'Bikaner Motors');

        $this->assertSame(['sales@bikanermotors.com', 'Bikaner Motors'], app(EmailService::class)->identity('default'));
    }

    public function test_the_smtp_settings_build_the_mailer_at_send_time(): void
    {
        Mail::fake();
        $this->settings->set('mail.smtp.host', 'smtp.example.com');
        $this->settings->set('mail.smtp.port', 465);
        $this->settings->set('mail.smtp.encryption', 'ssl');
        $this->settings->set('mail.smtp.username', 'mailer');
        $this->settings->set('mail.smtp.password', 'pa55word');
        $outbox = CommOutbox::query()->find(Email::send($this->raw())->get('outbox_id'));

        $sent = (new LaravelMailDriver)->send($outbox, (array) $outbox->payload);

        $this->assertTrue($sent['ok']);
        $this->assertSame(['smtps', 'smtp.example.com', 465, 'mailer', 'pa55word'], [
            config('mail.mailers.settings_smtp.scheme'), config('mail.mailers.settings_smtp.host'), config('mail.mailers.settings_smtp.port'),
            config('mail.mailers.settings_smtp.username'), config('mail.mailers.settings_smtp.password'),
        ]);
    }
}
