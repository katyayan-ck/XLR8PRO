<?php

/*
|--------------------------------------------------------------------------
| Error messages by code (DEC-085, go-live to-do U7)
|--------------------------------------------------------------------------
| The single source of the user-facing message for every App\Enums\ErrorCodeEnum code, grouped by module. Change a
| message here, never in code: ErrorCodeEnum::message() reads this file, and the API envelope
| ({http_status, success, code, message, timestamp, errors}) uses it whenever an exception carries no message of its own.
| A new code: add the enum case (with its HTTP status in statusCode()) and its line here, in its module's group.
*/

return [

    // ── Auth: OTP login, users, devices, tokens (IAM) ──────────────────────────────────────────────
    'AUTH_OTP_INVALID' => 'Invalid OTP code. Please try again.',
    'AUTH_OTP_EXPIRED' => 'OTP has expired. Please request a new one.',
    'AUTH_OTP_ATTEMPTS_EXCEEDED' => 'Too many failed OTP attempts. Please try again later.',
    'AUTH_OTP_SEND_FAILED' => 'Failed to send OTP. Please try again.',
    'AUTH_OTP_RATE_LIMIT' => 'Too many OTP requests. Please try again in a few minutes.',
    'AUTH_USER_NOT_FOUND' => 'User not found in the system.',
    'AUTH_USER_INACTIVE' => 'Your account is inactive. Please contact support.',
    'AUTH_USER_LOCKED' => 'Your account has been locked. Please contact support.',
    'AUTH_USER_SUSPENDED' => 'Your account has been suspended. Please contact support.',
    'AUTH_DEVICE_BINDING_FAILED' => 'Failed to bind device. Please try again.',
    'AUTH_DEVICE_INVALID' => 'Device information is invalid.',
    'AUTH_TOKEN_INVALID' => 'Invalid authentication token.',
    'AUTH_TOKEN_EXPIRED' => 'Authentication token has expired. Please login again.',
    'AUTH_TOKEN_REVOKED' => 'Authentication token has been revoked.',
    'AUTH_UNAUTHORIZED' => 'You are not authorized to perform this action.',
    'AUTH_FORBIDDEN' => 'Access forbidden.',
    'AUTH_MOBILE_INVALID' => 'Invalid mobile number format.',
    'AUTH_MOBILE_REGISTERED' => 'This mobile number is already registered.',
    'AUTH_MOBILE_NOT_REGISTERED' => 'This mobile number is not registered.',

    // ── Request / validation (all modules) ─────────────────────────────────────────────────────────
    'REQUEST_INVALID' => 'The request could not be understood.',
    'REQUEST_METHOD_NOT_ALLOWED' => 'This method is not allowed for this address.',
    'REQUEST_RATE_LIMITED' => 'Too many requests. Please wait a moment and try again.',
    'VALIDATION_FAILED' => 'Validation failed. Please check the provided data.',
    'VALIDATION_REQUIRED_FIELD' => 'One or more required fields are missing.',
    'VALIDATION_INVALID_FORMAT' => 'Data format is invalid.',
    'VALIDATION_DUPLICATE_ENTRY' => 'This entry already exists.',
    'VALIDATION_CONSTRAINT_VIOLATION' => 'Data violates business constraints.',

    // ── Resources (all modules) ────────────────────────────────────────────────────────────────────
    'RESOURCE_NOT_FOUND' => 'Requested resource not found.',
    'RESOURCE_DELETED' => 'Requested resource has been deleted.',
    'RESOURCE_CONFLICT' => 'Resource conflict detected.',
    'RESOURCE_ALREADY_EXISTS' => 'Resource already exists.',
    'RESOURCE_PERMISSION_DENIED' => 'You do not have permission to access this resource.',
    'RESOURCE_LOCKED' => 'This resource is locked.',

    // ── Org / HR: posts and employees (ORG) ────────────────────────────────────────────────────────
    'POST_NOT_FOUND' => 'Post not found.',
    'POST_OCCUPIED' => 'This post is already occupied.',
    'POST_FULLY_OCCUPIED' => 'This post has no vacancy left.',
    'POST_HAS_ACTIVE_OCCUPANTS' => 'This post still has active occupants.',
    'EMP_HAS_PRIMARY' => 'This employee already has a primary post.',
    'EMP_ALREADY_HAS_PRIMARY_POST' => 'This employee already has a primary post.',

    // ── Vehicle pricing (PRC, DEC-080) ─────────────────────────────────────────────────────────────
    'PRICING_NOT_FOUND' => 'No published price for this vehicle with these options.',
    'PRICING_ON_HOLD' => 'This price list is on hold.',

    // ── Settings (UTL) ─────────────────────────────────────────────────────────────────────────────
    'SETTINGS_NOT_FOUND' => 'Setting not found.',
    'SETTINGS_UPDATE_FAILED' => 'Failed to update setting.',
    'SETTINGS_INVALID_VALUE' => 'Invalid setting value.',
    'SETTINGS_IMPORT_FAILED' => 'Failed to import settings.',
    'SETTINGS_EXPORT_FAILED' => 'Failed to export settings.',

    // ── Platform: database, services, system ───────────────────────────────────────────────────────
    'DATABASE_CONNECTION_FAILED' => 'Database connection failed. Please try again.',
    'DATABASE_QUERY_FAILED' => 'Database query failed.',
    'DATABASE_TRANSACTION_FAILED' => 'Database transaction failed.',
    'DATABASE_INTEGRITY_VIOLATION' => 'Data integrity violation detected.',
    'DATABASE_DEADLOCK' => 'Database deadlock detected. Please try again.',
    'SERVICE_UNAVAILABLE' => 'Service is temporarily unavailable. Please try again later.',
    'SERVICE_TIMEOUT' => 'Service request timed out. Please try again.',
    'SERVICE_CONFIGURATION_ERROR' => 'Service configuration error.',
    'SERVICE_EXTERNAL_API_ERROR' => 'External service error. Please try again later.',
    'SYSTEM_ERROR' => 'An error occurred. Please contact support.',
    'SYSTEM_MAINTENANCE' => 'System is under maintenance. Please try again later.',
    'SYSTEM_CONFIGURATION_ERROR' => 'System configuration error.',
    'SYSTEM_PERMISSION_ERROR' => 'Insufficient permissions.',

];
