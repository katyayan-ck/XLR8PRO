<?php

namespace Tests\Feature\Api;

use App\Models\IAM\OtpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * Mobile-app OTP login (DEC-095 #3 / D1, BUG-187): the user is found by the person's primary mobile (`users` has no
 * mobile column), and the login / profile responses carry the person's name, mobile and e-mail.
 */
class AppOtpLoginTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    public function test_a_user_logs_in_with_the_otp_sent_to_their_primary_mobile(): void
    {
        Mail::fake();
        $person = $this->peopleWithMobiles(1)->first();
        $mobile = substr(preg_replace('/\D/', '', $person->mobile), -10);
        $user = User::findOrFail($person->id);
        Cache::forget("account_lock_{$mobile}");

        $this->postJson('/api/v1/auth/request-otp', ['mobile' => $mobile])->assertOk();

        $token = OtpToken::query()->where('mobile', $mobile)->latest('id')->firstOrFail();
        $this->assertSame($user->id, (int) $token->user_id);
        $token->forceFill(['otp_hash' => Hash::make('123456')])->save();   // an update must not move the expiry (BUG-227)
        $this->assertTrue($token->fresh()->expires_at->isFuture());

        $login = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => $mobile, 'otp' => '123456', 'device_id' => 'test-device-'.uniqid(), 'device_name' => 'Test phone', 'platform' => 'android',
        ])->assertOk();

        $this->assertSame($user->display_name, $login->json('data.user.name'));
        $this->assertSame($mobile, $login->json('data.user.mobile'));
        $this->assertSame($user->primary_email, $login->json('data.user.email'));
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_an_unregistered_mobile_is_refused_without_an_error_page(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['mobile' => '6000000001'])
            ->assertStatus(404)->assertJsonPath('code', 'AUTH_USER_NOT_FOUND');
    }
}
