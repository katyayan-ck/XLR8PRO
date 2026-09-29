<?php

namespace Tests\Feature\IAM;

use App\Models\User;
use App\Services\IAM\SessionGuardService;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Go-live to-do S1 / S2: idle auto-logout and screen lock are off unless Settings turn them on; when on, the server signs
 * an idle session out, locks after the idle-lock time, keeps a locked screen locked (pages redirect, AJAX 423), unlocks
 * with the password and signs out after too many wrong ones. Background AJAX never keeps a session alive.
 */
class SessionGuardTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'idle_'.uniqid(), 'password' => bcrypt('secret-pass-1'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach (['security.idle_logout_minutes', 'security.idle_lock_minutes', 'security.unlock_max_attempts'] as $key) {
            app(SettingsService::class)->reset($key);
        }
        $this->actingAs($this->user, backpack_guard_name());
    }

    private function set(string $key, mixed $value): void
    {
        app(SettingsService::class)->set($key, $value);
    }

    public function test_nothing_changes_while_the_settings_are_off(): void
    {
        $this->get(route('backpack.account.info'))->assertOk();
        $this->travel(10)->hours();
        $this->get(route('backpack.account.info'))->assertOk()->assertSee('Lock screen');
    }

    public function test_an_idle_session_is_signed_out_and_background_polls_do_not_keep_it_alive(): void
    {
        $this->set('security.idle_logout_minutes', 15);
        $this->get(route('backpack.account.info'))->assertOk()->assertSee('name="xl-idle"', false);

        $this->travel(10)->minutes();
        $this->getJson(route('backpack.account.info'))->assertOk();   // a background AJAX call — not activity
        $this->travel(6)->minutes();
        $this->get(route('backpack.account.info'))->assertRedirect(route('backpack.auth.login'))->assertSessionHas('status');
        $this->assertNull(backpack_auth()->user());
    }

    public function test_the_heartbeat_counts_as_activity(): void
    {
        $this->set('security.idle_logout_minutes', 15);
        $this->get(route('backpack.account.info'));
        $this->travel(10)->minutes();
        $this->postJson(route('xl.session.activity'), [], ['X-XL-Activity' => '1'])->assertOk()->assertJson(['locked' => false]);
        $this->travel(10)->minutes();
        $this->get(route('backpack.account.info'))->assertOk();
    }

    public function test_lock_blocks_pages_and_ajax_until_the_password_unlocks(): void
    {
        $this->get(route('backpack.account.info'));
        $this->post(route('xl.session.lock'))->assertRedirect();
        $this->get(route('backpack.account.info'))->assertRedirect()->assertRedirectContains('session/lock-screen');
        $this->getJson(route('backpack.account.info'))->assertStatus(423);
        $this->get(route('xl.session.lock-screen'))->assertOk()->assertSee('Screen locked')->assertDontSee('name="xl-idle"', false);

        $this->post(route('xl.session.unlock'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('xl.session.unlock'), ['password' => 'secret-pass-1', 'to' => route('backpack.account.info')])->assertRedirect(route('backpack.account.info'));
        $this->get(route('backpack.account.info'))->assertOk();
    }

    public function test_the_screen_locks_after_the_idle_lock_time(): void
    {
        $this->set('security.idle_lock_minutes', 5);
        $this->get(route('backpack.account.info'));
        $this->travel(6)->minutes();
        $this->get(route('backpack.account.info'))->assertRedirectContains('session/lock-screen');
        $this->assertTrue(app(SessionGuardService::class)->isLocked(session()->driver()));
    }

    public function test_too_many_wrong_unlock_passwords_sign_out(): void
    {
        $this->set('security.unlock_max_attempts', 2);
        $this->get(route('backpack.account.info'));
        $this->post(route('xl.session.lock'));
        $this->post(route('xl.session.unlock'), ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->post(route('xl.session.unlock'), ['password' => 'nope'])->assertRedirect(route('backpack.auth.login'));
        $this->assertNull(backpack_auth()->user());
    }

    public function test_web_login_is_throttled_after_five_failures(): void
    {
        auth(backpack_guard_name())->logout();
        $field = backpack_authentication_column();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('backpack.auth.login'), [$field => $this->user->username, 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $response = $this->post(route('backpack.auth.login'), [$field => $this->user->username, 'password' => 'secret-pass-1']);
        $this->assertNull(backpack_auth()->user(), 'the 6th attempt within a minute is refused even with the right password');
        $this->assertStringContainsStringIgnoringCase('seconds', implode(' ', (array) session('errors')?->all()));
    }

    public function test_unlock_never_redirects_off_site(): void
    {
        $this->post(route('xl.session.lock'));
        $this->post(route('xl.session.unlock'), ['password' => 'secret-pass-1', 'to' => 'https://evil.example/x'])->assertRedirect(backpack_url('dashboard'));
    }
}
