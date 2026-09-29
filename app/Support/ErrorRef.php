<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * One short reference id per request for server errors (go-live to-do U8). The branded 500 page shows it and the log
 * entry carries it (`bootstrap/app.php` → `withExceptions()->context()`), so support can find the exact error from what
 * the user reads out — without ever showing a stack trace.
 *
 *   ErrorRef::get();   // "K7Q2M9XA" (the same value for the whole request)
 */
final class ErrorRef
{
    private static ?string $ref = null;

    public static function get(): string
    {
        return self::$ref ??= strtoupper(Str::random(8));
    }

    /** Tests / long-running workers: start a new reference. */
    public static function reset(): void
    {
        self::$ref = null;
    }
}
