<?php

namespace App\Services;

use App\Models\IAM\OtpToken;
use App\Models\IAM\DeviceSession;
use App\Models\IAM\OtpAttemptLog;
use App\Models\IAM\AccountLock;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\ValidationException;
use App\Exceptions\PermissionException;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthenticationService
{
    private OtpNotificationService $notificationService;

    public function __construct(OtpNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Generate and send OTP via Email and SMS
     */
    public function requestOtp(string $mobile): array
    {
        try {
            // Clean mobile number (remove non-digits)
            $mobile = preg_replace('/\D/', '', $mobile);

            // Validate mobile (10 digits)
            if (strlen($mobile) !== 10) {
                return [
                    'success' => false,
                    'code' => 'E422',
                    'message' => 'Mobile must be 10 digits',
                    'http_status' => 422,
                ];
            }

            // Find user by mobile
            $user = User::where('mobile', $mobile)
                ->whereNull('deleted_at')
                ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'code' => 'E002',
                    'message' => 'Mobile not registered',
                    'http_status' => 404,
                ];
            }

            // Check if account is locked
            $lockKey = "account_lock:{$mobile}";
            if (Cache::has($lockKey)) {
                $this->notificationService->sendAccountLockedEmail(
                    $user->email,
                    $mobile,
                    'Too many failed login attempts'
                );

                return [
                    'success' => false,
                    'code' => 'E004',
                    'message' => 'Account locked due to failed attempts. Try again later.',
                    'http_status' => 403,
                ];
            }

            // Check rate limit (5 OTP requests per 15 minutes)
            $rateLimitKey = "otp_request:{$mobile}";
            $requestCount = Cache::get($rateLimitKey, 0);

            if ($requestCount >= 5) {
                return [
                    'success' => false,
                    'code' => 'E005',
                    'message' => 'Too many OTP requests. Try again after 15 minutes.',
                    'http_status' => 429,
                ];
            }

            // Generate 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(10);

            // Store OTP in database
            OtpToken::create([
                'user_id' => $user->id,
                'otp_hash' => Hash::make($otp),
                'mobile' => $mobile,
                'expires_at' => $expiresAt,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Log attempt
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'action' => 'request',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Send OTP via Email and SMS
            $this->notificationService->sendViaEmailAndSms($user->email, $mobile, $otp);

            // Increment rate limit counter
            Cache::put($rateLimitKey, $requestCount + 1, now()->addMinutes(15));

            Log::info('OTP requested', [
                'user_id' => $user->id,
                'mobile' => $mobile,
                'timestamp' => now(),
            ]);

            return [
                'success' => true,
                'code' => 'S001',
                'message' => 'OTP sent to your registered mobile and email',
                'http_status' => 200,
                'data' => [
                    'expires_at' => $expiresAt,
                    'expires_in_minutes' => 10,
                    // In dev, show OTP (remove in production)
                    'otp' => config('app.debug') ? $otp : null,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error requesting OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'code' => 'E500',
                'message' => 'Internal server error',
                'http_status' => 500,
            ];
        }
    }

    /**
     * Verify OTP and create authenticated session
     */
    public function verifyOtp(string $mobile, string $otp, string $deviceId, string $deviceName, string $platform): array
    {
        try {
            // Clean mobile number
            $mobile = preg_replace('/\D/', '', $mobile);

            // Validate inputs
            if (strlen($mobile) !== 10) {
                return [
                    'success' => false,
                    'code' => 'E422',
                    'message' => 'Mobile must be 10 digits',
                    'http_status' => 422,
                ];
            }

            if (strlen($otp) !== 6 || !ctype_digit($otp)) {
                return [
                    'success' => false,
                    'code' => 'E422',
                    'message' => 'OTP must be 6 digits',
                    'http_status' => 422,
                ];
            }

            // Find user
            $user = User::where('mobile', $mobile)
                ->whereNull('deleted_at')
                ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'code' => 'E002',
                    'message' => 'Mobile not registered',
                    'http_status' => 404,
                ];
            }

            // Check if account is locked
            $lockKey = "account_lock:{$mobile}";
            if (Cache::has($lockKey)) {
                return [
                    'success' => false,
                    'code' => 'E004',
                    'message' => 'Account locked due to failed attempts. Try again later.',
                    'http_status' => 403,
                ];
            }

            // Get latest OTP
            $otpToken = OtpToken::where('user_id', $user->id)
                ->where('mobile', $mobile)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpToken) {
                OtpAttemptLog::create([
                    'user_id' => $user->id,
                    'mobile' => $mobile,
                    'action' => 'verify_failed',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                return [
                    'success' => false,
                    'code' => 'E002',
                    'message' => 'No OTP found. Request a new one.',
                    'http_status' => 404,
                ];
            }

            // Check if OTP expired
            if ($otpToken->expires_at < now()) {
                OtpAttemptLog::create([
                    'user_id' => $user->id,
                    'mobile' => $mobile,
                    'action' => 'verify_failed',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                return [
                    'success' => false,
                    'code' => 'E002',
                    'message' => 'OTP expired. Request a new one.',
                    'http_status' => 404,
                ];
            }

            // Verify OTP
            if (!Hash::check($otp, $otpToken->otp_hash)) {
                // Increment failed attempts
                $failureKey = "otp_failures:{$mobile}";
                $failureCount = Cache::get($failureKey, 0);
                Cache::put($failureKey, $failureCount + 1, now()->addMinutes(15));

                // Lock account after 5 failures
                if ($failureCount + 1 >= 5) {
                    Cache::put($lockKey, true, now()->addMinutes(30));

                    $this->notificationService->sendAccountLockedEmail(
                        $user->email,
                        $mobile,
                        'Too many failed OTP verification attempts'
                    );
                }

                OtpAttemptLog::create([
                    'user_id' => $user->id,
                    'mobile' => $mobile,
                    'action' => 'verify_failed',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                return [
                    'success' => false,
                    'code' => 'E002',
                    'message' => 'Invalid OTP',
                    'http_status' => 401,
                ];
            }

            // Mark OTP as used
            $otpToken->update([
                'used_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Clear failure counter
            Cache::forget("otp_failures:{$mobile}");

            // Check if device already exists for this user
            $device = DeviceSession::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->whereNull('deleted_at')
                ->first();

            if ($device) {
                // Device already registered - Update last activity
                $device->update([
                    'last_active_at' => now(),
                    'updated_by' => $user->id,
                ]);

                Log::info('Device session updated', [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                ]);
            } else {
                // New device - Check device limit
                $existingDevices = DeviceSession::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->count();

                // Allow maximum 5 devices per user (configurable in .env as DEVICE_LIMIT)
                $deviceLimit = 1;

                if ($existingDevices >= $deviceLimit) {
                    OtpAttemptLog::create([
                        'user_id' => $user->id,
                        'mobile' => $mobile,
                        'action' => 'verify_failed_device_limit',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);

                    Log::warning('Device limit exceeded', [
                        'user_id' => $user->id,
                        'existing_devices' => $existingDevices,
                        'device_limit' => $deviceLimit,
                    ]);

                    return [
                        'success' => false,
                        'code' => 'E003',
                        'message' => "Maximum device limit ({$deviceLimit}) reached. Please use an existing registered device or contact admin to register a new device.",
                        'http_status' => 403,
                    ];
                }

                // Create new device session
                $device = DeviceSession::create([
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'platform' => $platform,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'last_active_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                Log::info('New device session created', [
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'total_devices' => $existingDevices + 1,
                ]);
            }


            // Create API token with device binding
            $expiresAt = now()->addHours(24);
            $token = $user->createToken('auth-token', ['device_id' => $deviceId], $expiresAt);

            // Log successful verification
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $mobile,
                'action' => 'verify_success',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Send verification success email
            $this->notificationService->sendVerificationSuccessEmail(
                $user->email,
                $mobile,
                $deviceName
            );

            Log::info('OTP verified successfully', [
                'user_id' => $user->id,
                'mobile' => $mobile,
                'device' => $deviceName,
            ]);

            return [
                'success' => true,
                'code' => 'S001',
                'message' => 'Login successful',
                'http_status' => 200,
                'data' => [
                    'token' => $token->plainTextToken,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'is_active' => $user->is_active,
                    ],
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error verifying OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'code' => 'E500',
                'message' => 'Internal server error',
                'http_status' => 500,
            ];
        }
    }

    /**
     * Get current authenticated user details
     * 
     * @param User $user The authenticated user object
     * @return array
     */
    public function me($user): array
    {
        try {
            // Refresh user from database
            $user = User::find($user->id);

            if (!$user) {
                return [
                    'success' => false,
                    'code' => 'E001',
                    'message' => 'User not found',
                    'http_status' => 404,
                ];
            }

            // Get device sessions
            $devices = DeviceSession::where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->select('id', 'device_id', 'device_name', 'platform', 'last_active_at', 'created_at')
                ->get();

            // Get current role assignments (use existing relationship)
            $roleAssignments = $user->roleAssignments()
                ->where('is_current', true)
                ->where(function ($q) {
                    $q->whereNull('to_date')
                        ->orWhere('to_date', '>=', now());
                })
                ->get();

            $currentToken = $user->currentAccessToken();

            Log::info('User details retrieved', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'success' => true,
                'code' => 'S001',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'code' => $user->code,
                    'is_active' => (bool)$user->is_active,
                    'created_at' => $user->created_at,
                    'last_login_at' => $user->last_login_at,
                    'role_assignments' => $roleAssignments->map(fn($a) => [
                        'id' => $a->id,
                        'role_id' => $a->role_id,
                        'from_date' => $a->from_date,
                        'to_date' => $a->to_date,
                        'is_current' => (bool)$a->is_current,
                    ])->toArray(),
                    'devices' => $devices->map(fn($d) => [
                        'id' => $d->id,
                        'device_id' => $d->device_id,
                        'device_name' => $d->device_name,
                        'platform' => $d->platform,
                        'last_active_at' => $d->last_active_at,
                        'created_at' => $d->created_at,
                    ])->toArray(),
                    'current_token' => [
                        'abilities' => $currentToken ? $currentToken->abilities : [],
                        'last_used_at' => $currentToken?->last_used_at,
                    ],
                ],
                'http_status' => 200,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving user details', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'code' => 'E500',
                'message' => 'Internal server error',
                'http_status' => 500,
            ];
        }
    }



    /**
     * Logout and revoke token
     */
    public function logout(): array
    {
        try {
            $user = auth()->user('sanctum');

            if (!$user) {
                return [
                    'success' => false,
                    'code' => 'E001',
                    'message' => 'Not authenticated',
                    'http_status' => 401,
                ];
            }

            // Revoke current token
            $user->currentAccessToken()->delete();

            // Log logout
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Log::info('User logged out', [
                'user_id' => $user->id,
                'timestamp' => now(),
            ]);

            return [
                'success' => true,
                'code' => 'S001',
                'message' => 'Logged out successfully',
                'http_status' => 200,
            ];
        } catch (Exception $e) {
            Log::error('Error during logout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'code' => 'E500',
                'message' => 'Internal server error',
                'http_status' => 500,
            ];
        }
    }
}
