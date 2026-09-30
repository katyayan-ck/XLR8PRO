<?php

namespace App\Enums;

/**
 * ErrorCodeEnum
 *
 * Centralized error codes for the entire application.
 * Organized by category and domain with consistent naming.
 *
 * Format: {CATEGORY}_{SUBCATEGORY}_{CODE}
 * Examples:
 * - AUTH_OTP_INVALID (Authentication → OTP → Invalid)
 * - AUTH_DEVICE_BINDING_FAILED (Authentication → Device → Binding Failed)
 * - SETTINGS_UPDATE_FAILED (Settings → Update → Failed)
 *
 * Messages live in resources/lang/en/errors.php (grouped by module); the API envelope for every exception on api/*
 * is built by App\Exceptions\ApiExceptionRenderer (DEC-085).
 *
 * @author VDMS Development Team
 *
 * @version 2.0
 */
enum ErrorCodeEnum: string
{
    case AUTH_OTP_INVALID = 'AUTH_OTP_INVALID';
    case AUTH_OTP_EXPIRED = 'AUTH_OTP_EXPIRED';
    case AUTH_OTP_ATTEMPTS_EXCEEDED = 'AUTH_OTP_ATTEMPTS_EXCEEDED';
    case AUTH_OTP_SEND_FAILED = 'AUTH_OTP_SEND_FAILED';
    case AUTH_OTP_RATE_LIMIT = 'AUTH_OTP_RATE_LIMIT';

    case AUTH_USER_NOT_FOUND = 'AUTH_USER_NOT_FOUND';
    case AUTH_USER_INACTIVE = 'AUTH_USER_INACTIVE';
    case AUTH_USER_LOCKED = 'AUTH_USER_LOCKED';
    case AUTH_USER_SUSPENDED = 'AUTH_USER_SUSPENDED';

    case AUTH_DEVICE_BINDING_FAILED = 'AUTH_DEVICE_BINDING_FAILED';
    case AUTH_DEVICE_INVALID = 'AUTH_DEVICE_INVALID';

    case AUTH_TOKEN_INVALID = 'AUTH_TOKEN_INVALID';
    case AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    case AUTH_TOKEN_REVOKED = 'AUTH_TOKEN_REVOKED';
    case AUTH_UNAUTHORIZED = 'AUTH_UNAUTHORIZED';
    case AUTH_FORBIDDEN = 'AUTH_FORBIDDEN';

    case AUTH_MOBILE_INVALID = 'AUTH_MOBILE_INVALID';
    case AUTH_MOBILE_REGISTERED = 'AUTH_MOBILE_REGISTERED';
    case AUTH_MOBILE_NOT_REGISTERED = 'AUTH_MOBILE_NOT_REGISTERED';

    // Request-level (DEC-085)
    case REQUEST_INVALID = 'REQUEST_INVALID';
    case REQUEST_METHOD_NOT_ALLOWED = 'REQUEST_METHOD_NOT_ALLOWED';
    case REQUEST_RATE_LIMITED = 'REQUEST_RATE_LIMITED';

    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case VALIDATION_REQUIRED_FIELD = 'VALIDATION_REQUIRED_FIELD';
    case VALIDATION_INVALID_FORMAT = 'VALIDATION_INVALID_FORMAT';
    case VALIDATION_DUPLICATE_ENTRY = 'VALIDATION_DUPLICATE_ENTRY';
    case VALIDATION_CONSTRAINT_VIOLATION = 'VALIDATION_CONSTRAINT_VIOLATION';

    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    case RESOURCE_DELETED = 'RESOURCE_DELETED';
    case RESOURCE_CONFLICT = 'RESOURCE_CONFLICT';
    case RESOURCE_ALREADY_EXISTS = 'RESOURCE_ALREADY_EXISTS';
    case RESOURCE_PERMISSION_DENIED = 'RESOURCE_PERMISSION_DENIED';
    case RESOURCE_LOCKED = 'RESOURCE_LOCKED';

    case DATABASE_CONNECTION_FAILED = 'DATABASE_CONNECTION_FAILED';
    case DATABASE_QUERY_FAILED = 'DATABASE_QUERY_FAILED';
    case DATABASE_TRANSACTION_FAILED = 'DATABASE_TRANSACTION_FAILED';
    case DATABASE_INTEGRITY_VIOLATION = 'DATABASE_INTEGRITY_VIOLATION';
    case DATABASE_DEADLOCK = 'DATABASE_DEADLOCK';

    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case SERVICE_TIMEOUT = 'SERVICE_TIMEOUT';
    case SERVICE_CONFIGURATION_ERROR = 'SERVICE_CONFIGURATION_ERROR';
    case SERVICE_EXTERNAL_API_ERROR = 'SERVICE_EXTERNAL_API_ERROR';

    case SETTINGS_NOT_FOUND = 'SETTINGS_NOT_FOUND';
    case SETTINGS_UPDATE_FAILED = 'SETTINGS_UPDATE_FAILED';
    case SETTINGS_INVALID_VALUE = 'SETTINGS_INVALID_VALUE';
    case SETTINGS_IMPORT_FAILED = 'SETTINGS_IMPORT_FAILED';
    case SETTINGS_EXPORT_FAILED = 'SETTINGS_EXPORT_FAILED';

    // Vehicle pricing (DEC-080)
    case PRICING_NOT_FOUND = 'PRICING_NOT_FOUND';
    case PRICING_ON_HOLD = 'PRICING_ON_HOLD';

    // Vehicle compare (VEH, DEC-092)
    case VEHICLE_COMPARE_SEGMENT = 'VEHICLE_COMPARE_SEGMENT';
    case VEHICLE_COMPARE_SELECTION = 'VEHICLE_COMPARE_SELECTION';

    case SYSTEM_ERROR = 'SYSTEM_ERROR';
    case SYSTEM_MAINTENANCE = 'SYSTEM_MAINTENANCE';
    case SYSTEM_CONFIGURATION_ERROR = 'SYSTEM_CONFIGURATION_ERROR';
    case SYSTEM_PERMISSION_ERROR = 'SYSTEM_PERMISSION_ERROR';

    case POST_NOT_FOUND = 'POST_NOT_FOUND';
    case POST_OCCUPIED = 'POST_OCCUPIED';
    case EMP_HAS_PRIMARY = 'EMP_HAS_PRIMARY';
    case POST_FULLY_OCCUPIED = 'POST_FULLY_OCCUPIED';
    case EMP_ALREADY_HAS_PRIMARY_POST = 'EMP_ALREADY_HAS_PRIMARY_POST';
    case POST_HAS_ACTIVE_OCCUPANTS = 'POST_HAS_ACTIVE_OCCUPANTS';

    /**
     * The user-facing message, from `resources/lang/en/errors.php` (the SSOT, grouped by module — DEC-085). A code
     * without a line there falls back to the generic SYSTEM_ERROR text instead of failing.
     */
    public function message(): string
    {
        $key = 'errors.'.$this->value;

        return trans()->has($key) ? (string) __($key) : (string) __('errors.SYSTEM_ERROR');
    }

    public function statusCode(): int
    {
        return match ($this) {
            // 400 Bad Request
            self::REQUEST_INVALID => 400,

            // 401 Unauthorized
            self::AUTH_TOKEN_INVALID,
            self::AUTH_TOKEN_EXPIRED,
            self::AUTH_TOKEN_REVOKED,
            self::AUTH_UNAUTHORIZED,
            self::AUTH_OTP_INVALID,
            self::AUTH_OTP_EXPIRED => 401,

            // 403 Forbidden
            self::AUTH_FORBIDDEN,
            self::AUTH_USER_LOCKED,
            self::AUTH_USER_SUSPENDED,
            self::RESOURCE_PERMISSION_DENIED,
            self::AUTH_USER_INACTIVE => 403,

            // 404 Not Found
            self::RESOURCE_NOT_FOUND,
            self::SETTINGS_NOT_FOUND,
            self::AUTH_USER_NOT_FOUND,
            self::RESOURCE_DELETED => 404,

            // 409 Conflict
            self::VALIDATION_DUPLICATE_ENTRY,
            self::RESOURCE_ALREADY_EXISTS,
            self::RESOURCE_CONFLICT,
            self::AUTH_MOBILE_REGISTERED,
            self::DATABASE_INTEGRITY_VIOLATION => 409,

            // 405 Method Not Allowed
            self::REQUEST_METHOD_NOT_ALLOWED => 405,

            // 422 Unprocessable — validation (every validation response already sent 422, DEC-085) + HR / Post rules
            self::VALIDATION_FAILED,
            self::VALIDATION_REQUIRED_FIELD,
            self::VALIDATION_INVALID_FORMAT,
            self::VALIDATION_CONSTRAINT_VIOLATION,
            self::AUTH_MOBILE_INVALID,
            self::POST_NOT_FOUND,
            self::POST_OCCUPIED,
            self::POST_FULLY_OCCUPIED,
            self::POST_HAS_ACTIVE_OCCUPANTS,
            self::EMP_HAS_PRIMARY,
            self::EMP_ALREADY_HAS_PRIMARY_POST => 422,

            // 429 Too Many Requests
            self::AUTH_OTP_RATE_LIMIT,
            self::REQUEST_RATE_LIMITED,
            self::AUTH_OTP_ATTEMPTS_EXCEEDED => 429,

            // 503 Service Unavailable
            self::SERVICE_UNAVAILABLE,
            self::SYSTEM_MAINTENANCE,
            self::DATABASE_CONNECTION_FAILED => 503,

            self::PRICING_NOT_FOUND => 404,
            self::VEHICLE_COMPARE_SEGMENT,
            self::VEHICLE_COMPARE_SELECTION => 422,
            self::PRICING_ON_HOLD,
            self::RESOURCE_LOCKED => 423,

            // 500 Internal Server Error (always last)
            default => 500,
        };
    }
}
