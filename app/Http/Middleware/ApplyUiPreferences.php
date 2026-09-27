<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin menu layout the user picked in the Appearance panel (DEC-067). The choice is a per-browser,
 * unencrypted cookie written by public/js/xl-theme.js; only the whitelisted Backpack layouts are honoured, anything
 * else keeps the configured default.
 */
class ApplyUiPreferences
{
    /** Layouts offered in the Appearance panel. */
    public const LAYOUTS = ['horizontal', 'vertical', 'vertical_dark'];

    public const COOKIE = 'xl_layout';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $layout = $request->cookie(self::COOKIE);

        if (is_string($layout) && in_array($layout, self::LAYOUTS, true)) {
            config(['backpack.theme-tabler.layout' => $layout]);
        }

        return $next($request);
    }
}
