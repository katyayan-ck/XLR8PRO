<?php

namespace Tests\Feature\IAM;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Go-live to-do S5 / S7: My Account self-service follows Settings — by default users change their own name, photo and
 * password (today's behaviour); switched off, the controls are replaced by "managed by your administrator" and the
 * endpoints refuse. The password rule (length, mixed case, symbols) comes from Settings too.
 */
class AccountSelfServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private const KEYS = ['account.can_change_display_name', 'account.can_change_photo', 'account.can_change_password',
        'account.password_min_length', 'account.password_require_mixed_case', 'account.password_require_symbols'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::KEYS as $key) {
            app(SettingsService::class)->reset($key);
        }
        $this->user = User::create(['username' => 'self_'.uniqid(), 'password' => bcrypt('secret-pass-1'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->actingAs($this->user, backpack_guard_name());
    }

    public function test_switched_off_self_service_hides_the_controls_and_refuses_the_change(): void
    {
        $this->get(route('backpack.account.info', ['tab' => 'security']))->assertOk()->assertSee('Change password</button>', false);

        app(SettingsService::class)->set('account.can_change_password', false);
        app(SettingsService::class)->set('account.can_change_photo', false);
        $this->get(route('backpack.account.info', ['tab' => 'security']))->assertOk()->assertSee('managed by your administrator');
        $this->post(route('backpack.account.password'), ['current_password' => 'secret-pass-1', 'new_password' => 'another-pass-2', 'new_password_confirmation' => 'another-pass-2'])->assertForbidden();
        $this->post(route('backpack.account.photo'), ['remove' => 1])->assertForbidden();
        $this->post(route('backpack.account.info.store'), ['display_name' => 'Still allowed'])->assertStatus(302);
    }

    public function test_the_password_rule_follows_settings(): void
    {
        $change = fn (string $new) => $this->post(route('backpack.account.password'), ['current_password' => 'secret-pass-1', 'new_password' => $new, 'new_password_confirmation' => $new]);

        app(SettingsService::class)->set('account.password_min_length', 12);
        $change('short-pw-12')->assertSessionHasErrorsIn('password', 'new_password');

        app(SettingsService::class)->set('account.password_require_symbols', true);
        $change('longenoughpass1')->assertSessionHasErrorsIn('password', 'new_password');
        $change('long-enough-pass-1')->assertSessionHasNoErrors();
    }
}
