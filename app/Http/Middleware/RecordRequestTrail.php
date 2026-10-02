<?php

namespace App\Http\Middleware;

use App\Services\Platform\Help\DiagnosticsService;
use App\Support\ErrorRef;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin requests: adds each signed-in user's request to their short server trail (route, method, masked path, status,
 * time, the error reference on a 5xx) for support requests (DEC-094, W16d). The idle heartbeat is not recorded.
 */
class RecordRequestTrail
{
    private const SKIP = ['xl.session.activity'];

    public function __construct(private readonly DiagnosticsService $diagnostics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);

        $user = backpack_user();
        $route = (string) $request->route()?->getName();
        if ($user !== null && ! in_array($route, self::SKIP, true)) {
            $status = $response->getStatusCode();
            $this->diagnostics->record((int) $user->getKey(), [
                'method' => $request->method(),
                'route' => $route !== '' ? $route : null,
                'path' => DiagnosticsService::cleanUrl($request->getRequestUri()),
                'status' => $status,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'ref' => $status >= 500 ? ErrorRef::get() : null,
            ]);
        }

        return $response;
    }
}
