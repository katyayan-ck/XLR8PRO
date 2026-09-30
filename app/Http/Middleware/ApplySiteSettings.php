<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies the Site / dealership settings to every web request (DEC-091, W13 Phase 2): the name shown in the header,
 * page titles and login page (`dealership.name`, fallback the configured project name). Read per request from the cached
 * settings, so a change on Utilities → Settings shows on the next page without a config or cache clear. Favicon, logo
 * and the legal name are read directly in the views through `site_favicon_url()`, `site_logo_url()` and `dealership()`.
 */
class ApplySiteSettings
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $name = trim((string) setting('dealership.name', ''));
            if ($name !== '') {
                config(['backpack.ui.project_name' => $name, 'backpack.theme-tabler.project_name' => $name]);
            }
        } catch (Throwable $e) {
            report($e);   // settings unavailable (e.g. before migrations) — keep the configured defaults
        }

        return $next($request);
    }
}
