<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

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

    /**
     * The text an admin screen may show for a caught exception (to-do W6 remainder). Messages our code throws on purpose
     * ("Receipt amount exceeds the balance") are shown as they are; technical failures (SQL, PHP errors) are logged with
     * the reference and replaced by `utils.flash.technical_error`, so a screen never shows SQL, file paths or customer data.
     *
     *   Alert::error(__('accounts.flash.error_creating_receipt', ['message' => ErrorRef::userMessage($e)]))->flash();
     */
    public static function userMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }
        if (! self::isTechnical($e) && trim($e->getMessage()) !== '') {
            return $e->getMessage();
        }
        Log::error('Admin action failed', [
            'error_ref' => self::get(), 'exception' => $e::class, 'message' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine(),
        ]);

        return __('utils.flash.technical_error', ['ref' => self::get()]);
    }

    /** SQL / driver failures and PHP engine errors (TypeError, ErrorException…) — never meant for the user. */
    private static function isTechnical(Throwable $e): bool
    {
        return $e instanceof QueryException || $e instanceof PDOException || $e instanceof \Error || $e instanceof \ErrorException;
    }
}
