<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Services\IAM\SessionGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Screen lock + idle heartbeat (go-live to-do S1 / S2). Acts on the signed-in user's own session only — no module
 * permission, like My Account.
 */
class SessionLockController extends Controller
{
    public function __construct(private readonly SessionGuardService $guard) {}

    public function lockScreen(Request $request): View|RedirectResponse
    {
        if (! $this->guard->isLocked($request->session())) {
            return redirect()->to($this->safeTarget($request->query('to')));
        }

        return view('admin.account.lock-screen', ['user' => backpack_user(), 'to' => $this->safeTarget($request->query('to'))]);
    }

    public function lock(Request $request): JsonResponse|RedirectResponse
    {
        if (! $this->guard->config()['lock_enabled'] && ! $request->boolean('idle')) {
            abort(404);
        }
        $this->guard->lock($request->session());
        $target = route('xl.session.lock-screen', ['to' => $this->safeTarget($request->input('to'))]);

        return $request->expectsJson() ? response()->json(['locked' => true, 'redirect' => $target]) : redirect()->to($target);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string'], 'to' => ['nullable', 'string']]);
        $result = $this->guard->unlock($request->session(), backpack_user(), $data['password']);
        if ($result->ok) {
            return redirect()->to($this->safeTarget($data['to'] ?? null));
        }
        if ($result->code === 'TOO_MANY') {
            backpack_auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('backpack.auth.login')->with('status', $result->message);
        }

        return back()->withErrors(['password' => $result->message]);
    }

    /** Browser heartbeat while the user is active; answers what the idle timer needs. */
    public function activity(Request $request): JsonResponse
    {
        $config = $this->guard->config();

        return response()->json([
            'locked' => $this->guard->isLocked($request->session()),
            'idle_seconds' => $this->guard->idleSeconds($request->session()),
            'logout_minutes' => $config['logout'], 'lock_minutes' => $config['lock'],
        ]);
    }

    /** Only same-site relative targets (no open redirect). */
    private function safeTarget(?string $to): string
    {
        $fallback = backpack_url('dashboard');
        if (! $to) {
            return $fallback;
        }
        $base = rtrim(url('/'), '/');

        return str_starts_with($to, $base.'/') || $to === $base ? $to : $fallback;
    }
}
