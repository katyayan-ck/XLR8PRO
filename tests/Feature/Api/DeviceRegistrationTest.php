<?php

namespace Tests\Feature\Api;

use App\Models\IAM\DeviceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BUG-210: POST api/v1/devices/register validated against a table that does not exist, so every call failed with a
 * 500. Registering stores the push token, and registering the same device again refreshes it (FCM rotates tokens).
 */
class DeviceRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_device_registers_and_re_registering_refreshes_its_push_token(): void
    {
        $user = User::create(['username' => 'dev_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];
        $body = ['device_id' => $session->device_id, 'device_name' => 'Pixel 8', 'platform' => 'Android', 'fcm_token' => 'fcm-one'];

        $this->postJson('/api/v1/devices/register', $body, $headers)->assertStatus(201)
            ->assertJson(['success' => true, 'data' => ['device_id' => $session->device_id, 'platform' => 'android', 'fcm_token' => 'fcm-one']]);

        $this->postJson('/api/v1/devices/register', ['fcm_token' => 'fcm-two'] + $body, $headers)->assertStatus(201)
            ->assertJsonPath('data.fcm_token', 'fcm-two');

        $this->getJson('/api/v1/devices', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_registration_needs_the_push_token(): void
    {
        $user = User::create(['username' => 'dev_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];

        $this->postJson('/api/v1/devices/register', ['device_id' => $session->device_id, 'device_name' => 'Pixel 8', 'platform' => 'android'], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonValidationErrors('fcm_token');
    }
}
