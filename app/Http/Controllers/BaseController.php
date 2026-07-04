<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\ApplicationException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use Exception;
use Illuminate\Auth\AuthenticationException as LaravelAuthException;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BaseController
 * 
 * Enhanced base controller with robust exception/error handling.
 * Provides unified response formatting and error management.
 * 
 * Key Features:
 * - Centralized error code management via ErrorCodeEnum
 * - Custom exception handling for all error types
 * - Proper distinction between Exception and Error
 * - Contextual logging with audit trails
 * - Consistent JSON response format across all endpoints
 * - Support for field-level validation errors
 * 
 * @package App\Http\Controllers
 * @author VDMS Development Team
 * @version 2.0
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    // ═══════════════════════════════════════════════════════════════════════════════
    // SUCCESS RESPONSES
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Return successful response JSON (uniform format: no 'meta', timestamp direct)
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'http_status' => $statusCode,
            'success' => true,
            'code' => 'S' . str_pad($statusCode, 3, '0', STR_PAD_LEFT),
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return paginated response (uses uniform success format)
     * 
     * @param mixed $data Paginated items
     * @param object $paginator Laravel paginator instance
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function paginatedResponse(
        mixed $data,
        object $paginator,
        string $message = 'Data retrieved successfully',
        int $statusCode = 200
    ): JsonResponse {
        $paginationData = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return $this->successResponse(
            ['items' => $data, 'pagination' => $paginationData],
            $message,
            $statusCode
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ERROR RESPONSES
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Return error response (uniform format, optional data/errors)
     * 
     * @param string $message Error message
     * @param string $code Error code from ErrorCodeEnum
     * @param int $status HTTP status code
     * @param array $errors Optional field-level errors
     * @param mixed $data Optional additional data
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        string $code,
        int $status,
        array $errors = [],
        mixed $data = null
    ): JsonResponse {
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

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Return not found response
     * 
     * @param string $resource Resource name
     * @param mixed $id Optional ID
     * @return JsonResponse
     */
    protected function notFoundResponse(string $resource, $id = null): JsonResponse
    {
        $message = "{$resource} not found";
        if ($id !== null) {
            $message .= " with ID {$id}";
        }
        return $this->errorResponse(
            $message,
            ErrorCodeEnum::RESOURCE_NOT_FOUND->value,
            404
        );
    }

    /**
     * Return forbidden response
     * 
     * @param string $action Action attempted
     * @param string $resource Resource name
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $action, string $resource): JsonResponse
    {
        $message = "You are not authorized to {$action} {$resource}";
        return $this->errorResponse(
            $message,
            ErrorCodeEnum::AUTH_FORBIDDEN->value,
            403
        );
    }

    /**
     * Return unauthorized response
     * 
     * @param string $message Custom message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse(
            $message,
            ErrorCodeEnum::AUTH_UNAUTHORIZED->value,
            401
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // EXCEPTION HANDLING
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Handle exceptions uniformly with mapping to custom codes
     * 
     * @param Throwable $e Exception to handle
     * @param string $operation Operation context for logging
     * @param array $context Additional log context
     * @return JsonResponse Uniform error response
     */
    protected function handleException(
        Throwable $e,
        string $operation,
        array $context = []
    ): JsonResponse {
        // Log the exception first
        $this->logException($e, $operation, $context);

        // Handle custom ApplicationExceptions
        if ($e instanceof ApplicationException) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getErrorCode()->value,
                $e->getStatusCode(),
                $e->getErrors()
            );
        }

        // Map Laravel built-in exceptions to uniform format
        if ($e instanceof LaravelValidationException) {
            return $this->errorResponse(
                'Validation failed',
                ErrorCodeEnum::VALIDATION_FAILED->value,
                422,
                $e->errors()
            );
        } elseif ($e instanceof LaravelAuthException) {
            return $this->unauthorizedResponse($e->getMessage());
        } elseif ($e instanceof LaravelAuthorizationException) {
            return $this->forbiddenResponse('access', 'resource');
        } elseif ($e instanceof ModelNotFoundException) {
            return $this->notFoundResponse('Resource');
        }

        // Generic fallback for other exceptions
        return $this->errorResponse(
            'An unexpected error occurred',
            ErrorCodeEnum::SYSTEM_ERROR->value,
            500
        );
    }

    /**
     * Log exception with context
     * 
     * @param Throwable $e
     * @param string $context
     * @param array $data
     * @return void
     */
    private function logException(Throwable $e, string $context, array $data = []): void
    {
        $logContext = [
            'operation' => $context,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'exception_type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if (!empty($data)) {
            $logContext['additional_data'] = $data;
        }

        if ($e instanceof ApplicationException) {
            Log::warning("Application Exception: {$context}", $logContext);
        } else {
            Log::error("Exception Occurred: {$context}", $logContext);
        }
    }

    /**
     * Log audit trail for operations
     * 
     * @param string $action Action performed (create, update, delete, etc.)
     * @param string $resource Resource type
     * @param array $changes Changes made
     * @param int|string|null $resourceId Resource identifier
     * @param string|null $status Operation status
     * @return void
     */
    protected function logAudit(
        string $action,
        string $resource,
        array $changes = [],
        int|string|null $resourceId = null,
        ?string $status = 'success'
    ): void {
        $auditData = [
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'status' => $status,
            'changes' => $changes,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info("Audit: {$action} {$resource}", $auditData);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // AUTHORIZATION
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check authorization and throw exception if not allowed
     * 
     * @param string $ability
     * @param mixed $resource
     * @return void
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed $resource = null): void
    {
        if (!$this->canPerform($ability, $resource)) {
            throw new AuthorizationException(
                ErrorCodeEnum::AUTH_FORBIDDEN,
                "You are not authorized to {$ability}."
            );
        }
    }

    /**
     * Check if user can perform action (non-throwing version)
     * 
     * @param string $ability
     * @param mixed $resource
     * @return bool
     */
    protected function canPerform(string $ability, mixed $resource = null): bool
    {
        try {
            parent::authorize($ability, $resource);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
