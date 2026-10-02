<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * N4 (DEC-095 #28): the admin login locks after `security.admin_login_max_attempts` wrong passwords.
 */
class AdminLoginLockoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_admin_login_locks_after_the_configured_number_of_wrong_passwords(): void
    {
        $user = User::query()->where('is_active', 1)->firstOrFail();
        app(SettingsService::class)->set('security.admin_login_max_attempts', 2);
        $wrong = ['username' => $user->username, 'password' => 'not-the-password-'.uniqid()];

        foreach ([1, 2] as $attempt) {
            $this->from('/admin/login')->post('/admin/login', $wrong)->assertSessionHasErrors('username');
            $this->assertStringNotContainsString('Too many', (string) session('errors')->first('username'), "attempt {$attempt}");
        }

        $this->from('/admin/login')->post('/admin/login', $wrong)->assertSessionHasErrors('username');
        $this->assertStringContainsString('Too many', (string) session('errors')->first('username'));
    }
}
