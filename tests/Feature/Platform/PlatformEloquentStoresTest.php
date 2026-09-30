<?php

namespace Tests\Feature\Platform;

use App\Models\Comms\CommOtp;
use App\Models\Comms\CommSandbox;
use App\Models\User;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Comms\ContactService;
use App\Services\Platform\Comms\OutboxService;
use App\Services\Platform\Comms\SmsService;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * The platform stores converted to Eloquent models (DEC-093, to-do W15): OTP codes, consent and suppression, timeline
 * subscriptions, scoped setting overrides and the outbox status counts behave as before.
 */
class PlatformEloquentStoresTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    public function test_an_otp_is_stored_hashed_sent_through_the_sandbox_and_verified_once(): void
    {
        app(SettingsService::class)->set('sms.driver', 'sandbox');
        $person = $this->peopleWithMobiles(1)->first();
        $sms = app(SmsService::class);

        $issued = $sms->otp($person->person_code, 'login');

        $this->assertTrue($issued->ok, (string) $issued->message);
        $otp = CommOtp::query()->where('person_code', $person->person_code)->latest('id')->firstOrFail();
        $this->assertSame('LOGIN', $otp->purpose);
        $this->assertTrue(CommSandbox::query()->where('outbox_id', $issued->get('outbox_id'))->exists());

        $otp->update(['code_hash' => Hash::make('123456')]);
        $this->assertSame('OTP_INVALID', $sms->verify($person->person_code, 'LOGIN', '654321')->code);
        $this->assertSame(1, $otp->fresh()->attempts);
        $this->assertTrue($sms->verify($person->person_code, 'LOGIN', '123456')->ok);
        $this->assertNotNull($otp->fresh()->used_at);
        $this->assertSame('OTP_EXPIRED', $sms->verify($person->person_code, 'LOGIN', '123456')->code);
    }

    public function test_consent_and_suppression_are_recorded_and_updated_in_place(): void
    {
        $contacts = app(ContactService::class);
        $code = 'ZQCONSENT'.strtoupper(substr(uniqid(), -5));

        $contacts->setConsent($code, 'whatsapp', true, 'test');
        $this->assertTrue($contacts->consented($code, 'WHATSAPP'));
        $contacts->setConsent($code, 'WHATSAPP', false, 'test');
        $this->assertFalse($contacts->consented($code, 'WHATSAPP'));
        $this->assertTrue($contacts->hasOptedOut($code, 'WHATSAPP'));

        $contacts->suppress('email', 'ZQ.Bounce@Example.com', 'BOUNCE');
        $contacts->suppress('EMAIL', 'zq.bounce@example.com', 'COMPLAINT');
        $this->assertTrue($contacts->suppressed('EMAIL', 'zq.bounce@example.com'));
    }

    public function test_a_user_subscribes_to_and_leaves_an_entity_timeline(): void
    {
        $chat = app(ChatService::class);
        $user = User::query()->where('is_active', 1)->firstOrFail();
        $entity = User::query()->where('is_active', 1)->whereKeyNot($user->id)->firstOrFail();

        $this->assertTrue($chat->subscribe($entity, $user->id)->ok);
        $this->assertTrue($chat->subscribe($entity, $user->id)->ok);   // idempotent
        $this->assertTrue($chat->isSubscribed($entity, $user->id));
        $chat->unsubscribe($entity, $user->id);
        $this->assertFalse($chat->isSubscribed($entity, $user->id));
    }

    public function test_a_branch_override_wins_for_that_branch_and_clears(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('sms.otp_max_per_15min', 3);

        $this->assertTrue($settings->set('sms.otp_max_per_15min', 7, 'BRANCH', 'ZQB1')->ok);
        $this->assertSame(7, (int) $settings->getFor(['branch' => 'ZQB1'], 'sms.otp_max_per_15min'));
        $this->assertSame(3, (int) $settings->getFor(['branch' => 'ZQB2'], 'sms.otp_max_per_15min'));

        $settings->clearScope('sms.otp_max_per_15min', 'branch', 'ZQB1');
        $this->assertSame(3, (int) $settings->getFor(['branch' => 'ZQB1'], 'sms.otp_max_per_15min'));
    }

    public function test_outbox_stats_count_rows_per_status(): void
    {
        $outbox = app(OutboxService::class);
        $before = $outbox->stats(1)['QUEUED'] ?? 0;
        $outbox->queue(['channel' => 'SMS', 'to_address' => '+919800000001', 'category' => 'OPERATIONAL', 'payload' => ['text' => 'x']], false);

        $this->assertSame($before + 1, $outbox->stats(1)['QUEUED'] ?? 0);
    }
}
