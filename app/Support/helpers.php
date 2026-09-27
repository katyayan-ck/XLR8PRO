<?php

/*
|--------------------------------------------------------------------------
| Thin global aliases over services (FRS availability law 2)
|--------------------------------------------------------------------------
| Only one-line aliases for Blade / plain expressions live here. Every one of
| them delegates to a service; no business logic is allowed in this file
| (the legacy app/Helpers classes were removed in DEC-060).
*/

use App\Services\DateFormatService;

if (! function_exists('site_date')) {
    /**
     * Formats a date using the site's configured display.date_format setting. Function form of
     * the @sitedate() Blade directive, for use anywhere a plain expression is needed.
     */
    function site_date(mixed $date, string $fallback = 'N/A'): string
    {
        return app(DateFormatService::class)->format($date, $fallback);
    }
}
