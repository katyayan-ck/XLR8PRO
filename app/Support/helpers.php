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
use App\Services\Platform\Settings\SettingsService;

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

if (! function_exists('setting')) {
    /** Effective setting value (FRS SET-10) — alias of Settings::get(). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('feature')) {
    /** Feature flag (FRS SET-06) — alias of Settings::flag(). */
    function feature(string $key, bool $default = false): bool
    {
        return app(SettingsService::class)->flag($key, $default);
    }
}
