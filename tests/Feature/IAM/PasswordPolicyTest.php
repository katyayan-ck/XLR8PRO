<?php

namespace Tests\Feature\IAM;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * N4 (DEC-095 #28): password history and expiry are site settings, off by default. With history N a password from the
 * last N changes is refused; with expiry D days an older password sends every screen to My Account until it is changed.
 */
class PasswordPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $user = User::query()->where('is_active', 1)->whereNotNull('person_code')->get()->first(fn (User $u) => ! $u->isSuperAdmin())
            ?? $this->markTestSkipped('Needs an active non-superadmin user.');
        $user->forceFill(['password' => Hash::make('OldPass123'), 'password_changed_at' => now()])->save();

        return $user;
    }

    private function change(string $current, string $new): TestResponse
    {
        return $this->from(route('backpack.account.info'))
            ->post(route('backpack.account.password'), ['current_password' => $current, 'new_password' => $new, 'new_password_confirmation' => $new]);
    }

    public function test_a_recently_used_password_is_refused_only_when_history_is_on(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'backpack');
        app(SettingsService::class)->set('account.password_history_count', 3);

        $this->change('OldPass123', 'FirstNew11')->assertSessionHasNoErrors();
        $this->change('FirstNew11', 'SecondNew22')->assertSessionHasNoErrors();
        $this->change('SecondNew22', 'FirstNew11')->assertSessionHasErrorsIn('password', 'new_password');
        $this->assertTrue(Hash::check('SecondNew22', $user->fresh()->password));

        app(SettingsService::class)->set('account.password_history_count', 0);
        $this->change('SecondNew22', 'FirstNew11')->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_an_expired_password_sends_every_screen_to_my_account_until_it_is_changed(): void
    {
        $user = $this->user();
        $user->forceFill(['password_changed_at' => now()->subDays(40)])->save();
        $this->actingAs($user, 'backpack');

        $this->get('/admin/dashboard')->assertOk();   // off by default

        app(SettingsService::class)->set('account.password_expiry_days', 30);
        $this->get('/admin/dashboard')->assertRedirect(route('backpack.account.info'));
        $this->getJson('/admin/dashboard')->assertStatus(403)->assertJsonPath('code', 'PASSWORD_EXPIRED');
        $this->get(route('backpack.account.info'))->assertOk();

        $this->change('OldPass123', 'BrandNew33')->assertSessionHasNoErrors();
        $this->get('/admin/dashboard')->assertOk();
    }
}
