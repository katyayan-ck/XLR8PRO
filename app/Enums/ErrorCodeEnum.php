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
 * @package App\Enums
 * @author VDMS Development Team
 * @version 2.0
 */
enum ErrorCodeEnum: string
{
    // ═══════════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION ERRORS (AUTH_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    // OTP-Related Errors
    case AUTH_OTP_INVALID = 'AUTH_OTP_INVALID';
    case AUTH_OTP_EXPIRED = 'AUTH_OTP_EXPIRED';
    case AUTH_OTP_ATTEMPTS_EXCEEDED = 'AUTH_OTP_ATTEMPTS_EXCEEDED';
    case AUTH_OTP_SEND_FAILED = 'AUTH_OTP_SEND_FAILED';
    case AUTH_OTP_RATE_LIMIT = 'AUTH_OTP_RATE_LIMIT';
    
    // User-Related Errors
    case AUTH_USER_NOT_FOUND = 'AUTH_USER_NOT_FOUND';
    case AUTH_USER_INACTIVE = 'AUTH_USER_INACTIVE';
    case AUTH_USER_LOCKED = 'AUTH_USER_LOCKED';
    case AUTH_USER_SUSPENDED = 'AUTH_USER_SUSPENDED';
    
    // Device Binding Errors
    case AUTH_DEVICE_BINDING_FAILED = 'AUTH_DEVICE_BINDING_FAILED';
    case AUTH_DEVICE_INVALID = 'AUTH_DEVICE_INVALID';
    
    // Token Errors
    case AUTH_TOKEN_INVALID = 'AUTH_TOKEN_INVALID';
    case AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    case AUTH_TOKEN_REVOKED = 'AUTH_TOKEN_REVOKED';
    case AUTH_UNAUTHORIZED = 'AUTH_UNAUTHORIZED';
    case AUTH_FORBIDDEN = 'AUTH_FORBIDDEN';
    
    // Mobile Validation Errors
    case AUTH_MOBILE_INVALID = 'AUTH_MOBILE_INVALID';
    case AUTH_MOBILE_REGISTERED = 'AUTH_MOBILE_REGISTERED';
    case AUTH_MOBILE_NOT_REGISTERED = 'AUTH_MOBILE_NOT_REGISTERED';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // VALIDATION ERRORS (VALIDATION_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case VALIDATION_REQUIRED_FIELD = 'VALIDATION_REQUIRED_FIELD';
    case VALIDATION_INVALID_FORMAT = 'VALIDATION_INVALID_FORMAT';
    case VALIDATION_DUPLICATE_ENTRY = 'VALIDATION_DUPLICATE_ENTRY';
    case VALIDATION_CONSTRAINT_VIOLATION = 'VALIDATION_CONSTRAINT_VIOLATION';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // RESOURCE ERRORS (RESOURCE_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    case RESOURCE_DELETED = 'RESOURCE_DELETED';
    case RESOURCE_CONFLICT = 'RESOURCE_CONFLICT';
    case RESOURCE_ALREADY_EXISTS = 'RESOURCE_ALREADY_EXISTS';
    case RESOURCE_PERMISSION_DENIED = 'RESOURCE_PERMISSION_DENIED';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // DATABASE ERRORS (DATABASE_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case DATABASE_CONNECTION_FAILED = 'DATABASE_CONNECTION_FAILED';
    case DATABASE_QUERY_FAILED = 'DATABASE_QUERY_FAILED';
    case DATABASE_TRANSACTION_FAILED = 'DATABASE_TRANSACTION_FAILED';
    case DATABASE_INTEGRITY_VIOLATION = 'DATABASE_INTEGRITY_VIOLATION';
    case DATABASE_DEADLOCK = 'DATABASE_DEADLOCK';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // SERVICE ERRORS (SERVICE_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case SERVICE_TIMEOUT = 'SERVICE_TIMEOUT';
    case SERVICE_CONFIGURATION_ERROR = 'SERVICE_CONFIGURATION_ERROR';
    case SERVICE_EXTERNAL_API_ERROR = 'SERVICE_EXTERNAL_API_ERROR';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // SETTINGS ERRORS (SETTINGS_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case SETTINGS_NOT_FOUND = 'SETTINGS_NOT_FOUND';
    case SETTINGS_UPDATE_FAILED = 'SETTINGS_UPDATE_FAILED';
    case SETTINGS_INVALID_VALUE = 'SETTINGS_INVALID_VALUE';
    case SETTINGS_IMPORT_FAILED = 'SETTINGS_IMPORT_FAILED';
    case SETTINGS_EXPORT_FAILED = 'SETTINGS_EXPORT_FAILED';
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // SYSTEM ERRORS (SYSTEM_*)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    case SYSTEM_ERROR = 'SYSTEM_ERROR';
    case SYSTEM_MAINTENANCE = 'SYSTEM_MAINTENANCE';
    case SYSTEM_CONFIGURATION_ERROR = 'SYSTEM_CONFIGURATION_ERROR';
    case SYSTEM_PERMISSION_ERROR = 'SYSTEM_PERMISSION_ERROR';

    case POST_NOT_FOUND = 'POST_NOT_FOUND';
case POST_OCCUPIED  = 'POST_OCCUPIED';     // also add this — PostService uses it
case EMP_HAS_PRIMARY = 'EMP_HAS_PRIMARY'; // HRJourneyService uses this
case POST_FULLY_OCCUPIED         = 'POST_FULLY_OCCUPIED';
case EMP_ALREADY_HAS_PRIMARY_POST = 'EMP_ALREADY_HAS_PRIMARY_POST';
case POST_HAS_ACTIVE_OCCUPANTS    = 'POST_HAS_ACTIVE_OCCUPANTS';   // ← PostService
    
    /**
     * Get human-readable error message
     */
    public function message(): string
    {
        return match ($this) {
            // Authentication
            self::AUTH_OTP_INVALID => 'Invalid OTP code. Please try again.',
            self::AUTH_OTP_EXPIRED => 'OTP has expired. Please request a new one.',
            self::AUTH_OTP_ATTEMPTS_EXCEEDED => 'Too many failed OTP attempts. Please try again later.',
            self::AUTH_OTP_SEND_FAILED => 'Failed to send OTP. Please try again.',
            self::AUTH_OTP_RATE_LIMIT => 'Too many OTP requests. Please try again in a few minutes.',
            self::AUTH_USER_NOT_FOUND => 'User not found in the system.',
            self::AUTH_USER_INACTIVE => 'Your account is inactive. Please contact support.',
            self::AUTH_USER_LOCKED => 'Your account has been locked. Please contact support.',
            self::AUTH_USER_SUSPENDED => 'Your account has been suspended. Please contact support.',
            self::AUTH_DEVICE_BINDING_FAILED => 'Failed to bind device. Please try again.',
            self::AUTH_DEVICE_INVALID => 'Device information is invalid.',
            self::AUTH_TOKEN_INVALID => 'Invalid authentication token.',
            self::AUTH_TOKEN_EXPIRED => 'Authentication token has expired. Please login again.',
            self::AUTH_TOKEN_REVOKED => 'Authentication token has been revoked.',
            self::AUTH_UNAUTHORIZED => 'You are not authorized to perform this action.',
            self::AUTH_FORBIDDEN => 'Access forbidden.',
            self::AUTH_MOBILE_INVALID => 'Invalid mobile number format.',
            self::AUTH_MOBILE_REGISTERED => 'This mobile number is already registered.',
            self::AUTH_MOBILE_NOT_REGISTERED => 'This mobile number is not registered.',
            
            // Validation
            self::VALIDATION_FAILED => 'Validation failed. Please check the provided data.',
            self::VALIDATION_REQUIRED_FIELD => 'One or more required fields are missing.',
            self::VALIDATION_INVALID_FORMAT => 'Data format is invalid.',
            self::VALIDATION_DUPLICATE_ENTRY => 'This entry already exists.',
            self::VALIDATION_CONSTRAINT_VIOLATION => 'Data violates business constraints.',
            
            // Resource
            self::RESOURCE_NOT_FOUND => 'Requested resource not found.',
            self::RESOURCE_DELETED => 'Requested resource has been deleted.',
            self::RESOURCE_CONFLICT => 'Resource conflict detected.',
            self::RESOURCE_ALREADY_EXISTS => 'Resource already exists.',
            self::RESOURCE_PERMISSION_DENIED => 'You do not have permission to access this resource.',
            
            // Database
            self::DATABASE_CONNECTION_FAILED => 'Database connection failed. Please try again.',
            self::DATABASE_QUERY_FAILED => 'Database query failed.',
            self::DATABASE_TRANSACTION_FAILED => 'Database transaction failed.',
            self::DATABASE_INTEGRITY_VIOLATION => 'Data integrity violation detected.',
            self::DATABASE_DEADLOCK => 'Database deadlock detected. Please try again.',
            
            // Service
            self::SERVICE_UNAVAILABLE => 'Service is temporarily unavailable. Please try again later.',
            self::SERVICE_TIMEOUT => 'Service request timed out. Please try again.',
            self::SERVICE_CONFIGURATION_ERROR => 'Service configuration error.',
            self::SERVICE_EXTERNAL_API_ERROR => 'External service error. Please try again later.',
            
            // Settings
            self::SETTINGS_NOT_FOUND => 'Setting not found.',
            self::SETTINGS_UPDATE_FAILED => 'Failed to update setting.',
            self::SETTINGS_INVALID_VALUE => 'Invalid setting value.',
            self::SETTINGS_IMPORT_FAILED => 'Failed to import settings.',
            self::SETTINGS_EXPORT_FAILED => 'Failed to export settings.',
            
            // System
            self::SYSTEM_ERROR => 'An error occurred. Please contact support.',
            self::SYSTEM_MAINTENANCE => 'System is under maintenance. Please try again later.',
            self::SYSTEM_CONFIGURATION_ERROR => 'System configuration error.',
            self::SYSTEM_PERMISSION_ERROR => 'Insufficient permissions.',
        };
    }

    /**
     * Get HTTP status code for error
     */
public function statusCode(): int
{
    return match ($this) {
        // 400 Bad Request
        self::VALIDATION_FAILED,
        self::VALIDATION_REQUIRED_FIELD,
        self::VALIDATION_INVALID_FORMAT,
        self::AUTH_MOBILE_INVALID,
        self::VALIDATION_CONSTRAINT_VIOLATION => 400,

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

        // 422 Unprocessable — HR/Post domain rules  ← MOVED BEFORE default
        self::POST_NOT_FOUND,
        self::POST_OCCUPIED,
        self::POST_FULLY_OCCUPIED,
        self::POST_HAS_ACTIVE_OCCUPANTS,
        self::EMP_HAS_PRIMARY,
        self::EMP_ALREADY_HAS_PRIMARY_POST => 422,

        // 429 Too Many Requests
        self::AUTH_OTP_RATE_LIMIT,
        self::AUTH_OTP_ATTEMPTS_EXCEEDED => 429,

        // 503 Service Unavailable
        self::SERVICE_UNAVAILABLE,
        self::SYSTEM_MAINTENANCE,
        self::DATABASE_CONNECTION_FAILED => 503,

        // 500 Internal Server Error (always last)
        default => 500,
    };
}
}