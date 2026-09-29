<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response (go-live to-do S10). The Content Security Policy follows the setting
 * `security.csp_mode` (off | report | enforce, default report): report-only first, so a blocked CDN / inline script
 * shows in the browser console without breaking a screen; switch to enforce after UAT. HSTS only over HTTPS.
 */
class SecurityHeaders
{
    /** Allowed external sources: the pinned CDNs and Google Fonts (see .ai/rules/ui.md). */
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        ."font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        ."img-src 'self' data: blob: https:; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $mode = (string) rescue(fn () => setting('security.csp_mode', 'report'), 'report', false);
        if ($mode === 'enforce') {
            $headers->set('Content-Security-Policy', self::CSP);
        } elseif ($mode === 'report') {
            $headers->set('Content-Security-Policy-Report-Only', self::CSP);
        }

        return $response;
    }
}
