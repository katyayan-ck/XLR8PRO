<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\User;
use App\Support\Result;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Idle auto-logout and screen lock for admin sessions (go-live to-do S1 / S2). Everything is driven by Settings, so a
 * designation / branch can get its own values through Settings scopes later:
 *
 *   security.idle_logout_minutes   0 = off; sign out after this much inactivity (server-enforced)
 *   security.idle_lock_minutes     0 = off; lock the screen after this much inactivity
 *   security.idle_warning_seconds  warning shown before the auto sign-out
 *   security.screen_lock_enabled   the "Lock screen" menu item
 *   security.unlock_max_attempts   wrong unlock passwords before the session is signed out
 *
 * "Activity" is a page load or the browser's heartbeat (sent only while the user types / clicks / moves); background
 * polls do not keep a session alive.
 *
 *   $guard->idleExpired($session);    // true → sign out
 *   $guard->lock($session);           $guard->unlock($session, $user, $password);   // Result
 */
class SessionGuardService
{
    public const LAST_ACTIVITY = 'xl.last_activity';

    public const LOCKED = 'xl.screen_locked';

    public const UNLOCK_FAILS = 'xl.unlock_fails';

    /** @return array{logout: int, lock: int, warning: int, lock_enabled: bool, max_attempts: int} minutes / seconds */
    public function config(): array
    {
        return [
            'logout' => max(0, (int) setting('security.idle_logout_minutes', 0)),
            'lock' => max(0, (int) setting('security.idle_lock_minutes', 0)),
            'warning' => max(10, (int) setting('security.idle_warning_seconds', 60)),
            'lock_enabled' => (bool) setting('security.screen_lock_enabled', true),
            'max_attempts' => max(1, (int) setting('security.unlock_max_attempts', 5)),
        ];
    }

    public function touch(Session $session): void
    {
        $session->put(self::LAST_ACTIVITY, now()->getTimestamp());
    }

    /** Seconds since the last activity (0 when never recorded — the first request starts the clock). */
    public function idleSeconds(Session $session): int
    {
        $last = (int) $session->get(self::LAST_ACTIVITY, 0);

        return $last > 0 ? max(0, now()->getTimestamp() - $last) : 0;
    }

    public function idleExpired(Session $session): bool
    {
        $minutes = $this->config()['logout'];

        return $minutes > 0 && $this->idleSeconds($session) >= $minutes * 60;
    }

    /** True once the idle lock time has passed (the server locks even if the browser tab was asleep). */
    public function idleLockDue(Session $session): bool
    {
        $minutes = $this->config()['lock'];

        return $minutes > 0 && $this->idleSeconds($session) >= $minutes * 60;
    }

    public function isLocked(Session $session): bool
    {
        return (bool) $session->get(self::LOCKED, false);
    }

    public function lock(Session $session): void
    {
        $session->put(self::LOCKED, true);
        $session->put(self::UNLOCK_FAILS, 0);
    }

    /**
     * Unlock with the account password. Fails WRONG_PASSWORD (with attempts left) or TOO_MANY (the caller signs out).
     */
    public function unlock(Session $session, User $user, string $password): Result
    {
        if (Hash::check($password, (string) $user->getAuthPassword())) {
            $session->forget([self::LOCKED, self::UNLOCK_FAILS]);
            $this->touch($session);

            return Result::ok([], 'Unlocked.');
        }
        $fails = (int) $session->get(self::UNLOCK_FAILS, 0) + 1;
        $session->put(self::UNLOCK_FAILS, $fails);
        $left = $this->config()['max_attempts'] - $fails;
        Log::warning('[IAM] screen unlock failed', ['user_id' => $user->id, 'attempt' => $fails]);
        if ($left <= 0) {
            return Result::fail('TOO_MANY', 'Too many wrong passwords — you have been signed out.');
        }

        return Result::fail('WRONG_PASSWORD', "Wrong password — {$left} attempt(s) left.", ['left' => $left]);
    }
}
