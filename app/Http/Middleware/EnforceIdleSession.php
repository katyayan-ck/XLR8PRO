<?php

namespace App\Http\Middleware;

use App\Services\IAM\SessionGuardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin requests: sign out after the idle time, keep a locked screen locked, and record activity (go-live to-do S1 / S2).
 * Page loads count as activity; AJAX calls count only when the browser marks them as user activity (`X-XL-Activity: 1`,
 * the heartbeat in public/js/xl-idle.js), so background polls never keep an idle session alive.
 */
class EnforceIdleSession
{
    /** Routes that must work on a locked / expiring session. */
    private const OPEN = ['xl.session.lock-screen', 'xl.session.lock', 'xl.session.unlock', 'xl.session.activity', 'backpack.auth.logout', 'backpack.auth.login'];

    public function __construct(private readonly SessionGuardService $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = backpack_user();
        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }
        $session = $request->session();
        $route = (string) $request->route()?->getName();
        $ajax = $request->expectsJson() || $request->ajax();

        if ($this->guard->idleExpired($session) && $route !== 'backpack.auth.logout') {
            $minutes = $this->guard->config()['logout'];
            backpack_auth()->logout();
            $session->invalidate();
            $session->regenerateToken();
            $message = "You were signed out after {$minutes} minute(s) of inactivity.";

            return $ajax
                ? response()->json(['message' => $message, 'code' => 'IDLE_LOGOUT'], 401)
                : redirect()->route('backpack.auth.login')->with('status', $message);
        }
        if (! $this->guard->isLocked($session) && $this->guard->idleLockDue($session) && ! in_array($route, self::OPEN, true)) {
            $this->guard->lock($session);
        }
        if ($this->guard->isLocked($session) && ! in_array($route, self::OPEN, true)) {
            return $ajax
                ? response()->json(['message' => 'The screen is locked.', 'code' => 'SCREEN_LOCKED'], 423)
                : redirect()->route('xl.session.lock-screen', ['to' => $request->isMethod('GET') ? $request->fullUrl() : null]);
        }
        if ((! $ajax || $request->headers->get('X-XL-Activity') === '1') && ! $this->guard->isLocked($session)) {
            $this->guard->touch($session);
        }

        return $next($request);
    }
}
