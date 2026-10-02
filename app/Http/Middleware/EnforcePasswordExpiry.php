<?php

namespace App\Http\Middleware;

use App\Services\IAM\MyAccountService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin requests: when the signed-in user's password is older than `account.password_expiry_days` (0 = off, the
 * default), every screen sends them to My Account to choose a new one (N4, DEC-095 #28). The account, session-lock and
 * sign-out routes stay open so the change can be made.
 */
class EnforcePasswordExpiry
{
    /** Routes that work while the password is expired. */
    private const OPEN = ['backpack.account.info', 'backpack.account.password', 'backpack.auth.logout', 'backpack.auth.login',
        'xl.session.lock-screen', 'xl.session.lock', 'xl.session.unlock', 'xl.session.activity'];

    public function __construct(private readonly MyAccountService $account) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = backpack_user();
        if (! $user || in_array((string) $request->route()?->getName(), self::OPEN, true) || ! $this->account->passwordExpired($user)) {
            return $next($request);
        }
        $message = __('iam.flash.password_expired');

        return $request->expectsJson() || $request->ajax()
            ? response()->json(['message' => $message, 'code' => 'PASSWORD_EXPIRED'], 403)
            : redirect()->route('backpack.account.info')->with('warning', $message);
    }
}
