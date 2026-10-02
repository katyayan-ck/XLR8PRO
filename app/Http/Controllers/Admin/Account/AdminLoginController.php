<?php

namespace App\Http\Controllers\Admin\Account;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController;

/**
 * Backpack's admin login with its lockout limits taken from site settings (N4, DEC-095 #28):
 * `security.admin_login_max_attempts` wrong passwords lock the login for `security.admin_login_lock_minutes`
 * (defaults 5 / 1 — Backpack's own values). Bound in place of Backpack's controller in `AppServiceProvider`, so the
 * package routes stay as they are.
 */
class AdminLoginController extends LoginController
{
    /** Wrong passwords allowed before the login is locked. */
    public function maxAttempts(): int
    {
        return max(1, (int) setting('security.admin_login_max_attempts'));
    }

    /** Lock length in minutes. */
    public function decayMinutes(): int
    {
        return max(1, (int) setting('security.admin_login_lock_minutes'));
    }
}
