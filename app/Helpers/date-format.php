<?php

use App\Services\DateFormatService;

if (! function_exists('site_date')) {
    /**
     * Formats a date using the site's configured display.date_format
     * setting. Function form of the @sitedate() Blade directive, for use
     * anywhere a plain expression is needed (e.g. nested inside old()).
     */
    function site_date(mixed $date, string $fallback = 'N/A'): string
    {
        return app(DateFormatService::class)->format($date, $fallback);
    }
}
