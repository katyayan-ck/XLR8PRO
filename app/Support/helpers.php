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

if (! function_exists('site_datetime')) {
    /** Date + time in the site format (`display.date_format` + `display.time_format`); function form of @sitedatetime. */
    function site_datetime(mixed $date, string $fallback = 'N/A'): string
    {
        return app(DateFormatService::class)->formatDateTime($date, $fallback);
    }
}

if (! function_exists('site_logo_url')) {
    /** The configured site logo (setting branding.logo, DEC-083), else the given image, else the product logo. */
    function site_logo_url(string $fallback = 'images/Logo-108x75.png'): string
    {
        return (string) (setting('branding.logo') ?: asset($fallback));
    }
}

if (! function_exists('site_favicon_url')) {
    /** The favicon uploaded on Settings → Site (`dealership.favicon`, DEC-091), else null (the built-in icons are used). */
    function site_favicon_url(): ?string
    {
        $url = (string) setting('dealership.favicon', '');

        return $url !== '' ? $url : null;
    }
}

if (! function_exists('site_title')) {
    /** Browser / app title (owner 30-09): "<dealership name> | <application name>", or the application name alone. */
    function site_title(): string
    {
        $app = (string) backpack_theme_config('project_name');
        $dealer = trim((string) setting('dealership.name', ''));

        return $dealer !== '' ? $dealer.' | '.$app : $app;
    }
}

if (! function_exists('dealership')) {
    /** A Site / dealership setting (DEC-091): dealership('name'), dealership('legal_name'), dealership('address') … */
    function dealership(string $field, string $default = ''): string
    {
        return (string) (setting('dealership.'.$field, $default) ?? $default);
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
