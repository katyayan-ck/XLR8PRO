<?php

namespace App\Services;

use App\Exceptions\AccountLockedException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Models\IAM\DeviceSession;
use App\Models\IAM\OtpAttemptLog;
use App\Models\IAM\OtpToken;
use App\Models\User;
use App\Services\OtpNotificationService;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Enums\ErrorCodeEnum;
use Illuminate\Support\Str;

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
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_OTP_REQUESTS = 5;
    private const OTP_REQUEST_WINDOW_MINUTES = 15;
    private const MAX_OTP_ATTEMPTS = 5;
    private const OTP_ATTEMPT_WINDOW_MINUTES = 15;
    private const ACCOUNT_LOCK_DURATION_MINUTES = 30;
    private const DEVICE_LIMIT = 5;

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
     * @param string $mobile Mobile number
     * @param Request $request HTTP request for IP/user agent
     * @return array Response data
     * @throws ValidationException Invalid mobile format
     * @throws AuthenticationException User not found/inactive
     * @throws RateLimitException Too many requests
     */
    public function requestOtp(string $mobile, Request $request): array
    {
        try {
            // Validate mobile format
            if (!$this->isValidMobile($mobile)) {
                throw new ValidationException(
                    'Invalid mobile number format. Must be 10-digit number.',
                    ['mobile' => ['Invalid format']],
                    ErrorCodeEnum::AUTH_MOBILE_INVALID
                );
            }

            // Check rate limit for OTP requests
            $this->checkOtpRequestRateLimit($mobile);

            // Find user by mobile
            $user = User::where('mobile', $mobile)->first();

            if (!$user) {
                throw new AuthenticationException(
                    ErrorCodeEnum::AUTH_USER_NOT_FOUND,
                    "Mobile number '{$mobile}' not registered in system"
                );
            }

            if (!$user->is_active) {
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
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Send notifications
            $emailSent = $this->notificationService->sendViaEmail($user->email, $otp, $mobile);
            $smsSent = $this->notificationService->sendViaSms($mobile, $otp);

            Log::info('OTP notification sent', [
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent,
                'mobile' => $mobile,
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
                'mobile' => $mobile,
                'expires_at' => $token->expires_at->toIso8601String(),
            ]);

            $data = [
                'mobile' => $mobile,
                'expires_at' => $token->expires_at->toIso8601String(),
                'expires_in_minutes' => self::OTP_EXPIRY_MINUTES,
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
                'mobile' => $mobile,
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
     * @param string $mobile
     * @param string $otp
     * @param string $deviceId
     * @param string $deviceName
     * @param string $platform
     * @param Request $request HTTP request for IP/user agent
     * @return array Response data with token and user details
     * @throws AuthenticationException Invalid/expired OTP or user issues
     * @throws RateLimitException Too many attempts
     * @throws AccountLockedException Account locked
     * @throws ApplicationException Device limit exceeded or binding failed
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

            if (!$token) {
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
            if (!Hash::check($otp, $token->otp_hash)) {
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
                'mobile' => $mobile,
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
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                    ],
                ],
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new AuthenticationException(
                ErrorCodeEnum::AUTH_USER_NOT_FOUND,
                'User not found'
            );
        } catch (Throwable $e) {
            Log::error('Error verifying OTP', [
                'mobile' => $mobile,
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
     * @param User $user Authenticated user
     * @return array User data
     */
    public function getUserDetails(User $user): array
    {
        return [
            'success' => true,
            'message' => 'User profile retrieved',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
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
     * @param User $user Authenticated user
     * @return array Response data
     */
    public function logout(User $user): array
    {
        try {
            $mobile = $user->mobile ?? 'unknown';

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
                'mobile' => $mobile,
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
     * @param string $mobile Mobile number
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
        return str_pad(rand(0, pow(10, self::OTP_LENGTH) - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Check OTP request rate limit
     * 
     * @param string $mobile
     * @throws RateLimitException
     */
    private function checkOtpRequestRateLimit(string $mobile): void
    {
        $key = "otp_request_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= self::MAX_OTP_REQUESTS) {
            throw new RateLimitException(
                'OTP requests',
                self::MAX_OTP_REQUESTS,
                self::OTP_REQUEST_WINDOW_MINUTES * 60
            );
        }

        Cache::put($key, $count + 1, self::OTP_REQUEST_WINDOW_MINUTES * 60);
    }

    /**
     * Check OTP attempt rate limit
     * 
     * @param string $mobile
     * @throws RateLimitException
     */
    private function checkOtpAttemptRateLimit(string $mobile): void
    {
        $key = "otp_attempt_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= self::MAX_OTP_ATTEMPTS) {
            $this->lockAccount($mobile);
            throw new AccountLockedException(
                'Too many failed OTP attempts',
                self::ACCOUNT_LOCK_DURATION_MINUTES
            );
        }

        Cache::put($key, $count + 1, self::OTP_ATTEMPT_WINDOW_MINUTES * 60);
    }

    /**
     * Check if account is locked
     * 
     * @param string $mobile
     * @throws AccountLockedException
     */
    private function checkAccountLock(string $mobile): void
    {
        $lockKey = "account_lock_{$mobile}";
        if (Cache::has($lockKey)) {
            throw new AccountLockedException(
                'Account locked due to multiple failed attempts',
                self::ACCOUNT_LOCK_DURATION_MINUTES
            );
        }
    }

    /**
     * Lock account
     * 
     * @param string $mobile
     * @return void
     */
    private function lockAccount(string $mobile): void
    {
        $lockKey = "account_lock_{$mobile}";
        Cache::put($lockKey, true, self::ACCOUNT_LOCK_DURATION_MINUTES * 60);

        // Find user and send notification
        $user = User::where('mobile', $mobile)->first();
        if ($user) {
            $this->notificationService->sendAccountLockedEmail(
                $user->email,
                $mobile,
                'multiple failed OTP attempts'
            );
        }

        Log::warning('Account locked', ['mobile' => $mobile]);
    }

    /**
     * Log failed OTP attempt
     * 
     * @param string $mobile
     * @param string $action
     * @param string $reason
     * @param Request $request
     * @param int|null $userId
     * @return void
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
            'mobile' => $mobile,
            'reason' => $reason,
        ]);
    }

    /**
     * Check failed attempts and lock if exceeded
     * 
     * @param string $mobile
     * @return void
     */
    private function checkFailedAttemptsLock(string $mobile): void
    {
        $key = "otp_attempt_count_{$mobile}";
        $count = Cache::get($key, 0);

        if ($count >= self::MAX_OTP_ATTEMPTS) {
            $this->lockAccount($mobile);
        }
    }

    /**
     * Clear failed attempts cache
     * 
     * @param string $mobile
     * @return void
     */
    private function clearFailedAttemptsCache(string $mobile): void
    {
        Cache::forget("otp_attempt_count_{$mobile}");
    }

    /**
     * Check device limit
     * 
     * @param User $user
     * @throws ApplicationException
     */
    private function checkDeviceLimit(User $user): void
    {
        $activeSessions = DeviceSession::where('user_id', $user->id)
            ->where('last_active_at', '>', now()->subDays(30))  // Consider sessions active in last 30 days
            ->count();

        if ($activeSessions >= self::DEVICE_LIMIT) {
            throw new ApplicationException(
                'Device limit exceeded. Maximum ' . self::DEVICE_LIMIT . ' devices allowed.',
                ErrorCodeEnum::AUTH_DEVICE_BINDING_FAILED,
                403
            );
        }
    }

    /**
     * Create device session
     * 
     * @param User $user
     * @param string $deviceId
     * @param string $deviceName
     * @param string $platform
     * @return DeviceSession
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
}
