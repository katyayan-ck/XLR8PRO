<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): Response
    {
        if (str_starts_with($request->path(), 'api') || $request->expectsJson()) {
            $status = $this->isHttpException($e) ? $e->getStatusCode() : 500;
            $code = ErrorCodeEnum::SYSTEM_ERROR->value;
            $message = $e->getMessage() ?: 'An unexpected error occurred';
            $errors = [];

            if ($e instanceof ApplicationException) {
                $code = $e->getErrorCode()->value;
                $status = $e->getStatusCode();
                $message = $e->getMessage();
                $errors = $e->getErrors();
            } elseif ($e instanceof LaravelValidationException) {
                $code = ErrorCodeEnum::VALIDATION_FAILED->value;
                $status = 422;
                $message = 'Validation failed';
                $errors = $e->errors();
            } elseif ($e instanceof LaravelAuthenticationException) {
                $code = ErrorCodeEnum::AUTH_UNAUTHORIZED->value;
                $status = 401;
                $message = 'Unauthorized';
            } elseif ($e instanceof RouteNotFoundException) {
                $code = ErrorCodeEnum::RESOURCE_NOT_FOUND->value;
                $status = 404;
                $message = 'Route not found';
            } elseif ($e instanceof ModelNotFoundException) {
                $code = ErrorCodeEnum::RESOURCE_NOT_FOUND->value;
                $status = 404;
                $message = 'Resource not found';
            }

            $response = [
                'http_status' => $status,
                'success' => false,
                'code' => $code,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $status);
        }

        return parent::render($request, $e);
    }

    protected function unauthenticated($request, LaravelAuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'http_status' => 401,
            'success' => false,
            'code' => ErrorCodeEnum::AUTH_UNAUTHORIZED->value,
            'message' => 'Unauthorized',
            'timestamp' => now()->toIso8601String(),
        ], 401);
    }
}
