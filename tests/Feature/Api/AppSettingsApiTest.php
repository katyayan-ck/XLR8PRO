<?php

namespace Tests\Feature\Api;

use App\Models\IAM\DeviceSession;
use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * GET api/v1/app-settings (DEC-091, W13 Phase 6): the app reads the same values as Utilities → Settings, live; and the
 * older system-settings API never returns secrets (BUG-207).
 */
class AppSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, string> */
    /** @param  list<string>  $permissions */
    private function headers(array $permissions = []): array
    {
        $user = User::create(['username' => 'app_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);

        return ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];
    }

    public function test_the_app_gets_branding_switches_and_editable_fields_live(): void
    {
        $settings = app(SettingsService::class);
        $headers = $this->headers();

        $settings->set('dealership.name', 'Zeta Motors');
        $settings->set('comms.enabled.whatsapp', false);
        $settings->set('account.can_change_gender', true);

        $this->getJson('/api/v1/app-settings', $headers)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dealership.name', 'Zeta Motors')
            ->assertJsonPath('data.channels.whatsapp', false)
            ->assertJsonPath('data.account.editable_fields', ['gender'])
            ->assertJsonStructure(['data' => ['dealership' => ['logo_url', 'favicon_url'], 'ui' => ['appearance_enabled', 'date_format'], 'pricing_last_updated_at']]);

        $settings->set('dealership.name', 'Zeta Motors 2');
        $this->getJson('/api/v1/app-settings', $headers)->assertJsonPath('data.dealership.name', 'Zeta Motors 2');
    }

    public function test_the_app_settings_need_a_signed_in_device(): void
    {
        $this->getJson('/api/v1/app-settings')->assertUnauthorized();
    }

    /** DEC-095 #5 / BUG-207: the full settings API is for settings managers only — app users read `app-settings`. */
    public function test_the_system_settings_api_is_for_settings_managers_only(): void
    {
        $this->getJson('/api/v1/system-settings', $this->headers())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/system-settings/site.name', $this->headers())->assertForbidden();
    }

    public function test_the_system_settings_api_never_returns_secrets(): void
    {
        app(SettingsService::class)->set('mail.smtp.password', 'never-shown');
        $headers = $this->headers(['UTL_SETTINGS_MANAGE']);

        $this->getJson('/api/v1/system-settings', $headers)->assertOk()->assertDontSee('mail.smtp.password');
        $this->getJson('/api/v1/system-settings/mail.smtp.password', $headers)->assertNotFound();
    }

    /** BUG-209: the write endpoint works for a settings manager and writes through SettingsService. */
    public function test_a_settings_manager_updates_a_setting_through_the_api(): void
    {
        app(SettingsService::class)->set('chat.edit_window_minutes', 15);
        $headers = $this->headers(['UTL_SETTINGS_MANAGE']);

        $this->putJson('/api/v1/system-settings/chat.edit_window_minutes', ['value' => 20], $headers)->assertOk();

        $this->assertSame(20, (int) app(SettingsService::class)->get('chat.edit_window_minutes'));
    }
}
