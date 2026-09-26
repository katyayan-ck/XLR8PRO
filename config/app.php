<?php

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
|
| Every value is environment-driven so one build runs unchanged on local,
| stage (dev.xceler8.in), UAT and production; only `.env` differs. Service
| providers and aliases are registered in bootstrap/providers.php and
| bootstrap/app.php (Laravel 11+ structure), not here.
|
*/

return [

    'name' => env('APP_NAME', 'Xceler8'),

    // local | stage | uat | production — production must run with APP_DEBUG=false.
    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    // Used by Artisan / queued jobs to build absolute URLs; set per environment.
    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Timezone used by PHP date functions and for timestamps written by the app.
    | IST is the storage timezone (DEC-046); rows written before 26-09-2026 are UTC
    | and were intentionally left unconverted.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_IN'),

    /*
    | Encryption. Rotate APP_KEY by moving the old key into APP_PREVIOUS_KEYS
    | (comma-separated) so existing encrypted values and sessions still decrypt.
    */
    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    | Maintenance mode. "file" suits a single server (cPanel today); use
    | APP_MAINTENANCE_DRIVER=cache with a shared store (redis/database) once the
    | app runs on more than one server so `artisan down` applies everywhere.
    */
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
