<?php

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\AccountLockedException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Models\IAM\DeviceSession;
use App\Models\IAM\OtpAttemptLog;
use App\Models\IAM\OtpToken;
use App\Models\User;
use App\Services\Platform\Comms\ContactService;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authentication Service
 *
 * Handles OTP-based authentication flow:
 * 1. Request OTP - Validates mobile, generates OTP, sends via email/SMS
 * 2. Verify OTP - Validates OTP, creates device session, issues Sanctum token
 * 3. User Details - Retrieves authenticated user profile
 * 4. Logout - Revokes authentication token
 *
 * Features:
 * - Rate limiting (3 OTP requests per 15 minutes)
 * - Account locking (after 5 failed attempts, locked for 30 minutes)
 * - Device session management (max 5 devices per user)
 * - Email & SMS notifications
 * - Comprehensive logging and audit trail
 */
class AuthService
{
    /**
     * OTP Configuration
     */
    private const OTP_LENGTH = 6;

    /** Limits are site settings (N4, DEC-095 #28): `security.app_*` seeds in config/platform.php hold the defaults. */
    private const LIMIT_SETTINGS = [
        'OTP_EXPIRY_MINUTES' => 'security.app_otp_expiry_minutes',
        'MAX_OTP_REQUESTS' => 'security.app_otp_max_requests',
        'OTP_REQUEST_WINDOW_MINUTES' => 'security.app_otp_request_window_minutes',
        'MAX_OTP_ATTEMPTS' => 'security.app_otp_max_attempts',
        'OTP_ATTEMPT_WINDOW_MINUTES' => 'security.app_otp_attempt_window_minutes',
        'ACCOUNT_LOCK_DURATION_MINUTES' => 'security.app_lock_minutes',
        'DEVICE_LIMIT' => 'security.app_device_limit',
    ];

    protected Request $request;

    protected CacheManager $cache;

    protected OtpNotificationService $notificationService;

    public function __construct(
        Request $request,
        CacheManager $cache,
        OtpNotificationService $notificationService
    ) {
        $this->request = $request;
        $this->cache = $cache;
        $this->notificationService = $notificationService;
    }

    /**
     * Request OTP
     *
     * Validates mobile, checks registration, rate limits, generates OTP,
     * sends via email/SMS, and logs the attempt.
     *
     * @param  string  $mobile  Mobile number
     * @param  Request  $request  HTTP request for IP/user agent
     * @return array Response data
     *
     * @throws ValidationException Invalid mobile format
     * @throws AuthenticationException User not found/inactive
     * @throws RateLimitException Too many requests
     */
    public function requestOtp(string $mobile, Request $request): array
    {
        try {
            // Validate mobile format
            if (! $this->isValidMobile($mobile)) {
                throw new ValidationException(
                    'Invalid mobile number format. Must be 10-digit number.',
                    ['mobile' => ['Invalid format']],
                    ErrorCodeEnum::AUTH_MOBILE_INVALID
                );
            }

            // Check rate limit for OTP requests
            $this->checkOtpRequestRateLimit($mobile);

            // Find user by the person's primary mobile (users has no mobile column — DEC-095 #3 / BUG-187)
            $user = User::query()->withPrimaryMobile($mobile)->first();

            if (! $user) {
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_USER_NOT_FOUND,
                    'Mobile number not registered in system'
                );
            }

            if (! $user->is_active) {
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_USER_INACTIVE,
                    'User account is inactive. Contact support.'
                );
            }

            // Check if account is locked
            $this->checkAccountLock($mobile);

            // Generate OTP
            $otp = $this->generateOtp();

            // Create OTP token
            $token = OtpToken::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes($this->limit('OTP_EXPIRY_MINUTES')),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Send notifications
            $emailSent = $user->primary_email ? $this->notificationService->sendViaEmail($user->primary_email, $otp, $mobile) : false;
            $smsSent = $this->notificationService->sendViaSms($mobile, $otp);

            Log::info('OTP notification sent', [
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent,
                'mobile' => $this->maskMobile($mobile),
            ]);

            // Log attempt
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'action' => 'request',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Log::info('OTP requested successfully', [
                'user_id' => $user->id,
                'mobile' => $this->maskMobile($mobile),
                'expires_at' => $token->expires_at->toIso8601String(),
            ]);

            $data = [
                'mobile' => $mobile,
                'expires_at' => $token->expires_at->toIso8601String(),
                'expires_in_minutes' => $this->limit('OTP_EXPIRY_MINUTES'),
            ];

            // Add OTP in local environment for testing
            if (app()->environment('local')) {
                $data['otp'] = $otp;
            }

            return [
                'success' => true,
                'message' => 'OTP sent to your registered mobile and email',
                'data' => $data,
            ];
        } catch (Throwable $e) {
            Log::error('Error requesting OTP', [
                'mobile' => $this->maskMobile($mobile),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify OTP
     *
     * Validates OTP, checks expiration, rate limits attempts,
     * binds device, issues Sanctum token, and logs the session.
     *
     * @param  Request  $request  HTTP request for IP/user agent
     * @return array Response data with token and user details
     *
     * @throws AuthenticationException Invalid/expired OTP or user issues
     * @throws RateLimitException Too many attempts
     * @throws AccountLockedException Account locked
     * @throws AuthenticationException Device limit exceeded or binding failed
     */
    public function verifyOtp(
        string $mobile,
        string $otp,
        string $deviceId,
        string $deviceName,
        string $platform,
        Request $request
    ): array {
        try {
            // Check rate limit for OTP attempts
            $this->checkOtpAttemptRateLimit($mobile);

            // Check if account is locked
            $this->checkAccountLock($mobile);

            // Find latest valid OTP token
            $token = OtpToken::where('mobile', $mobile)
                ->latest('created_at')
                ->first();

            if (! $token) {
                $this->logFailedAttempt($mobile, 'verification', 'No OTP found', $request);
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_OTP_INVALID,
                    'Invalid OTP'
                );
            }

            // Check expiration
            if ($token->expires_at < now()) {
                $this->logFailedAttempt($mobile, 'verification', 'OTP expired', $request, $token->user_id);
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_OTP_EXPIRED,
                    'OTP has expired. Request a new one.'
                );
            }

            // Verify OTP hash
            if (! Hash::check($otp, $token->otp_hash)) {
                $this->logFailedAttempt($mobile, 'verification', 'Invalid OTP', $request, $token->user_id);
                $this->checkFailedAttemptsLock($mobile);
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_OTP_INVALID,
                    'Invalid OTP'
                );
            }

            // Get user
            $user = User::findOrFail($token->user_id);

            // Check device limit
            $this->checkDeviceLimit($user);

            // Create or update device session
            $session = DeviceSession::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                ],
                [
                    'device_name' => $deviceName,
                    'platform' => $platform,
                    'last_active_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            // Issue Sanctum token with device_id in abilities
            $authToken = $user->createToken(
                'auth_token',
                ['*', "device_id:{$deviceId}"],
                now()->addDays(30)  // Adjust as needed
            );

            // Mark OTP as used
            $token->update([
                'used_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Log successful verification
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'action' => 'verification',
                'status' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Clear failed attempts cache
            $this->clearFailedAttemptsCache($mobile);

            Log::info('OTP verified successfully', [
                'user_id' => $user->id,
                'mobile' => $this->maskMobile($mobile),
                'device_id' => $deviceId,
            ]);

            return [
                'success' => true,
                'message' => 'OTP verified',
                'data' => [
                    'token' => $authToken->plainTextToken,
                    'expires_at' => $authToken->accessToken->expires_at->toIso8601String(),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->display_name,
                        'email' => $user->primary_email,
                        'mobile' => $user->primary_mobile ?? $mobile,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                    ],
                ],
            ];
        } catch (ModelNotFoundException $e) {
            throw new AuthenticationException(
                ErrorCodeEnum::AUTH_USER_NOT_FOUND,
                'User not found'
            );
        } catch (Throwable $e) {
            Log::error('Error verifying OTP', [
                'mobile' => $this->maskMobile($mobile),
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get user details
     *
     * Retrieves authenticated user profile with roles and permissions.
     *
     * @param  User  $user  Authenticated user
     * @return array User data
     */
    public function getUserDetails(User $user): array
    {
        return [
            'success' => true,
            'message' => 'User profile retrieved',
            'data' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->primary_email,
                'mobile' => $user->primary_mobile,
                'role' => $user->getRoleNames()->first() ?? 'user',
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ];
    }

    /**
     * Logout user
     *
     * Revokes current authentication token and logs the action.
     *
     * @param  User  $user  Authenticated user
     * @return array Response data
     */
    public function logout(User $user): array
    {
        try {
            $mobile = $user->primary_mobile ?? 'unknown';

            // Revoke current token
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            // Log logout
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'action' => 'logout',
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Log::info('User logged out', [
                'user_id' => $user->id,
            ]);

            return [
                'success' => true,
                'message' => 'Logged out successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Error during logout', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate mobile number format
     *
     * Checks if mobile is 10-digit numeric (Indian format)
     *
     * @param  string  $mobile  Mobile number
     * @return bool True if valid format
     */
    private function isValidMobile(string $mobile): bool
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $mobile);

        // Check if it's 10 digits and numeric
        return strlen($cleaned) === 10 && is_numeric($cleaned);
    }

    /**
     * Generate random OTP
     *
     * @return string OTP code
     */
    private function generateOtp(): string
    {
        // cryptographically secure (DEC-095 #1 / BUG-188)
        return str_pad((string) random_int(0, (10 ** self::OTP_LENGTH) - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Check OTP request rate limit
     *
     * @throws RateLimitException
     */
    private function checkOtpRequestRateLimit(string $mobile): void
    {
        $key = "otp_request_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= $this->limit('MAX_OTP_REQUESTS')) {
            throw new RateLimitException(
                'OTP requests',
                $this->limit('MAX_OTP_REQUESTS'),
                $this->limit('OTP_REQUEST_WINDOW_MINUTES') * 60
            );
        }

        Cache::put($key, $count + 1, $this->limit('OTP_REQUEST_WINDOW_MINUTES') * 60);
    }

    /**
     * Check OTP attempt rate limit
     *
     * @throws RateLimitException
     */
    private function checkOtpAttemptRateLimit(string $mobile): void
    {
        $key = "otp_attempt_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= $this->limit('MAX_OTP_ATTEMPTS')) {
            $this->lockAccount($mobile);
            throw new AccountLockedException(
                'Too many failed OTP attempts',
                $this->limit('ACCOUNT_LOCK_DURATION_MINUTES')
            );
        }

        Cache::put($key, $count + 1, $this->limit('OTP_ATTEMPT_WINDOW_MINUTES') * 60);
    }

    /**
     * Check if account is locked
     *
     * @throws AccountLockedException
     */
    private function checkAccountLock(string $mobile): void
    {
        $lockKey = "account_lock_{$mobile}";
        if (Cache::has($lockKey)) {
            throw new AccountLockedException(
                'Account locked due to multiple failed attempts',
                $this->limit('ACCOUNT_LOCK_DURATION_MINUTES')
            );
        }
    }

    /**
     * Lock account
     *
     * @return void
     */
    /** Masked number for logs (BUG-189: never log a full phone number or an OTP). */
    private function maskMobile(?string $mobile): string
    {
        return app(ContactService::class)->mask($mobile);
    }

    private function lockAccount(string $mobile): void
    {
        $lockKey = "account_lock_{$mobile}";
        Cache::put($lockKey, true, $this->limit('ACCOUNT_LOCK_DURATION_MINUTES') * 60);

        // Find user and send notification
        $user = User::query()->withPrimaryMobile($mobile)->first();
        if ($user && $user->primary_email) {
            $this->notificationService->sendAccountLockedEmail(
                $user->primary_email,
                $mobile,
                'multiple failed OTP attempts'
            );
        }

        Log::warning('Account locked', ['mobile' => $this->maskMobile($mobile)]);
    }

    /**
     * Log failed OTP attempt
     */
    private function logFailedAttempt(
        string $mobile,
        string $action,
        string $reason,
        Request $request,
        ?int $userId = null
    ): void {
        OtpAttemptLog::create([
            'user_id' => $userId,
            'mobile' => $mobile,
            'action' => $action,
            'status' => 'failed',
            'notes' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        Log::warning('Failed OTP attempt', [
            'mobile' => $this->maskMobile($mobile),
            'reason' => $reason,
        ]);
    }

    /**
     * Check failed attempts and lock if exceeded
     */
    private function checkFailedAttemptsLock(string $mobile): void
    {
        $key = "otp_attempt_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= $this->limit('MAX_OTP_ATTEMPTS')) {
            $this->lockAccount($mobile);
        }
    }

    /**
     * Clear failed attempts cache
     */
    private function clearFailedAttemptsCache(string $mobile): void
    {
        Cache::forget("otp_attempt_count_{$mobile}");
    }

    /**
     * Check device limit
     *
     * @throws AuthenticationException
     */
    private function checkDeviceLimit(User $user): void
    {
        $activeSessions = DeviceSession::where('user_id', $user->id)
            ->where('last_active_at', '>', now()->subDays(30))  // Consider sessions active in last 30 days
            ->count();

        if ($activeSessions >= $this->limit('DEVICE_LIMIT')) {
            throw new AuthenticationException(
                ErrorCodeEnum::AUTH_DEVICE_BINDING_FAILED,
                'Device limit exceeded. Maximum '.$this->limit('DEVICE_LIMIT').' devices allowed.',
                403
            );
        }
    }

    /**
     * Create device session
     */
    private function createDeviceSession(User $user, string $deviceId, string $deviceName, string $platform): DeviceSession
    {
        return DeviceSession::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'device_name' => $deviceName,
                'platform' => $platform,
                'last_active_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );
    }

    /** A sign-in limit from its site setting (at least 1). */
    private function limit(string $name): int
    {
        return max(1, (int) setting(self::LIMIT_SETTINGS[$name]));
    }
}
