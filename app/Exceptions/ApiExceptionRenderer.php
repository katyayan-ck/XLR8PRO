<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use App\Support\ErrorRef;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * One place that turns any exception into the API envelope (DEC-085, go-live to-do U7):
 *
 *   { "http_status": 404, "success": false, "code": "RESOURCE_NOT_FOUND", "message": "…", "timestamp": "…",
 *     "errors": { "field": ["…"] },        // validation only
 *     "error_ref": "K7Q2M9XA" }            // 5xx only — the same id is in the log entry (U8)
 *
 * Registered for every `api/*` request in `bootstrap/app.php` (`withExceptions()->render(...)`), and used by
 * `BaseController::handleException()`, so a controller's try / catch and an uncaught exception answer alike. The status
 * codes are the ones Laravel already sent; messages come from `resources/lang/en/errors.php` unless the exception
 * carries its own. A 5xx never shows internals; with `APP_DEBUG=true` a `debug` block is added for developers.
 */
final class ApiExceptionRenderer
{
    /** HTTP status → the code used when an HTTP exception carries none of its own. */
    private const STATUS_CODES = [
        400 => ErrorCodeEnum::REQUEST_INVALID,
        401 => ErrorCodeEnum::AUTH_UNAUTHORIZED,
        403 => ErrorCodeEnum::AUTH_FORBIDDEN,
        404 => ErrorCodeEnum::RESOURCE_NOT_FOUND,
        405 => ErrorCodeEnum::REQUEST_METHOD_NOT_ALLOWED,
        409 => ErrorCodeEnum::RESOURCE_CONFLICT,
        422 => ErrorCodeEnum::VALIDATION_FAILED,
        423 => ErrorCodeEnum::RESOURCE_LOCKED,
        429 => ErrorCodeEnum::REQUEST_RATE_LIMITED,
        503 => ErrorCodeEnum::SERVICE_UNAVAILABLE,
    ];

    /**
     * The `withExceptions()->render()` callback: the envelope for `api/*` requests, null (Laravel's own rendering) for
     * everything else and for exceptions that already carry a response.
     */
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        return self::toResponse($e);
    }

    /** The envelope for one exception (see the class doc for the shape). */
    public static function toResponse(Throwable $e): JsonResponse
    {
        [$status, $code, $message, $errors] = self::classify($e);

        $body = [
            'http_status' => $status,
            'success' => false,
            'code' => $code->value,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        if ($status >= 500) {
            $body['error_ref'] = ErrorRef::get();
            if (config('app.debug')) {
                $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()];
            }
        }

        $headers = $e instanceof HttpExceptionInterface ? array_intersect_key($e->getHeaders(), array_flip(['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Allow'])) : [];

        return response()->json($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: ErrorCodeEnum, 2: string, 3: array<string, mixed>}
     */
    private static function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ApplicationException => [$e->getStatusCode(), $e->getErrorCode(), $e->getMessage() ?: $e->getErrorCode()->message(), $e->getErrors()],
            $e instanceof LaravelValidationException => [422, ErrorCodeEnum::VALIDATION_FAILED, 'Validation failed', $e->errors()],
            $e instanceof LaravelAuthenticationException => [401, ErrorCodeEnum::AUTH_UNAUTHORIZED, $e->getMessage() ?: 'Unauthenticated.', []],
            // BaseController::handleException gets these before Laravel converts them to HTTP exceptions; the texts
            // are the ones its responses always had
            $e instanceof LaravelAuthorizationException => [403, ErrorCodeEnum::AUTH_FORBIDDEN, 'You are not authorized to access resource', []],
            $e instanceof ModelNotFoundException => [404, ErrorCodeEnum::RESOURCE_NOT_FOUND, 'Resource not found', []],
            $e instanceof HttpExceptionInterface => self::fromHttp($e),
            default => [500, ErrorCodeEnum::SYSTEM_ERROR, 'An unexpected error occurred', []],
        };
    }

    /**
     * @return array{0: int, 1: ErrorCodeEnum, 2: string, 3: array<string, mixed>}
     */
    private static function fromHttp(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();
        $code = self::STATUS_CODES[$status] ?? ErrorCodeEnum::SYSTEM_ERROR;
        $previous = $e instanceof Throwable ? $e->getPrevious() : null;

        $message = match (true) {
            $previous instanceof ModelNotFoundException => 'Resource not found',
            $status >= 500 => $code->message(),
            // Symfony's route-miss text names the internal path matcher; users get the plain message
            $status === 404 || $status === 405 => $code->message(),
            default => ($e instanceof Throwable ? $e->getMessage() : '') ?: $code->message(),
        };

        return [$status, $code, $message, []];
    }
}
