<?php

namespace Tests\Feature\Api;

use App\Services\OtpNotificationService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * BUG-189 (DEC-070): the mobile-login notifications never write the OTP, a full phone number or a full email
 * address to the log — anyone reading laravel.log could otherwise sign in as that user.
 */
class OtpLoggingTest extends TestCase
{
    private const MOBILE = '9876543210';

    private const OTP = '482913';

    private const EMAIL = 'rahul.sharma@example.com';

    /** @return list<string> every logged message plus its JSON context */
    private function captureLogs(callable $action): array
    {
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$lines) {
            $lines[] = $e->message.' '.json_encode($e->context);
        });
        $action();

        return $lines;
    }

    public function test_the_sms_placeholder_logs_neither_the_otp_nor_the_full_number(): void
    {
        config(['app.debug' => true]);

        $lines = $this->captureLogs(fn () => app(OtpNotificationService::class)->sendViaSms(self::MOBILE, self::OTP));

        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString(self::OTP, $line);
            $this->assertStringNotContainsString(self::MOBILE, $line);
        }
    }

    public function test_the_otp_email_log_masks_the_address_and_number(): void
    {
        Mail::fake();

        $lines = $this->captureLogs(fn () => app(OtpNotificationService::class)->sendViaEmail(self::EMAIL, self::OTP, self::MOBILE));

        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertStringNotContainsString(self::OTP, $line);
            $this->assertStringNotContainsString(self::MOBILE, $line);
            $this->assertStringNotContainsString(self::EMAIL, $line);
        }
    }
}
