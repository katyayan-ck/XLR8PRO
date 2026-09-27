<?php

namespace App\Http\Middleware;

use App\Services\IAM\DataScope\DataScopeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `data-scope:off` (DEC-071): the whole request runs without user data scoping — for screens that
 * must see every row (e.g. a head-office report). Pass a reason: ->middleware('data-scope:off,HO report').
 */
class DataScopeOff
{
    public function handle(Request $request, Closure $next, string $mode = 'off', string $reason = ''): Response
    {
        if ($mode === 'off') {
            app(DataScopeManager::class)->offForRequest($reason !== '' ? $reason : (string) $request->route()?->getName());
        }

        return $next($request);
    }
}
