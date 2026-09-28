<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Authentication Controller
 *
 * Handles user authentication via OTP (One-Time Password)
 * - Request OTP: Send OTP to user's registered mobile and email
 * - Verify OTP: Validate OTP and issue authentication token
 * - Get Profile: Retrieve current authenticated user details
 * - Logout: Revoke authentication token
 *
 * Rate Limiting:
 * - OTP Request: Max 3 requests per 15 minutes per mobile
 * - OTP Verification: Max 5 attempts per 15 minutes per mobile
 * - Account Lock: 30 minutes after 5 failed verification attempts
 *
 * All timestamps returned in ISO 8601 format (2025-12-24T18:30:00Z)
 *
 * @OA\Info(
 *     title="VDMS API",
 *     version="1.0.0",
 *     description="Vehicle Dealership Management System API",
 *     contact={
 *         "email": "support@vdms.com"
 *     }
 * )
 *
 * @OA\Server(
 *     url="http://localhost/vdms/public/api/v1",
 *     description="Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://waba.insightechindia.in/public/api/v1/",
 *     description="Production Server"
 * )
 * 
 *
 * @OA\Components(
 *     @OA\SecurityScheme(
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="sanctum",
 *         securityScheme="sanctum"
 *     )
 * )
 */
class AuthController extends BaseController
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Request OTP
     *
     * Sends OTP to user's registered mobile and email.
     * Validates mobile format and checks if registered.
     * Rate limited to 3 requests per 15 minutes.
     *
     * @OA\Post(
     *     path="/auth/request-otp",
     *     operationId="requestOtp",
     *     tags={"Authentication"},
     *     summary="Request OTP for authentication",
     *     description="Generate and send OTP to mobile and email",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"mobile"},
     *             @OA\Property(property="mobile", type="string", example="9310260721", description="10-digit Indian mobile number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="OTP sent to your registered mobile and email"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="mobile", type="string", example="9310260721"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-24T18:40:00Z"),
     *                 @OA\Property(property="expires_in_minutes", type="integer", example=10),
     *                 @OA\Property(property="otp", type="string", example="123456", description="OTP shown only in local environment")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid mobile format"),
     *     @OA\Response(response=404, description="Mobile not registered"),
     *     @OA\Response(response=429, description="Too many requests"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mobile' => 'required|string|min:10|max:10',
            ]);

            $result = $this->authService->requestOtp($validated['mobile'], $request);

            return $this->successResponse(
                $result['data'],
                $result['message'] ?? 'OTP sent successfully',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Request OTP', [
                'mobile' => $request->input('mobile'),
                'ip_address' => $request->ip(),
            ]);
        }
    }

    /**
     * Verify OTP
     *
     * Validates OTP and issues authentication token.
     * Binds device session and enforces device limit (max 5).
     * Rate limited to 5 attempts per 15 minutes.
     *
     * @OA\Post(
     *     path="/auth/verify-otp",
     *     operationId="verifyOtp",
     *     tags={"Authentication"},
     *     summary="Verify OTP and get authentication token",
     *     description="Validate OTP and issue Sanctum token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"mobile","otp","device_id","device_name","platform"},
     *             @OA\Property(property="mobile", type="string", example="9310260721"),
     *             @OA\Property(property="otp", type="string", example="123456"),
     *             @OA\Property(property="device_id", type="string", example="unique-device-uuid"),
     *             @OA\Property(property="device_name", type="string", example="iPhone 14"),
     *             @OA\Property(property="platform", type="string", enum={"android","ios","web"}),
     *             @OA\Property(property="platform_version", type="string", example="iOS 16.0"),
     *             @OA\Property(property="fcm_token", type="string", example="fcm-push-token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="OTP verified"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="1|random-token-string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="mobile", type="string"),
     *                     @OA\Property(property="role", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=401, description="Invalid or expired OTP"),
     *     @OA\Response(response=403, description="Account locked"),
     *     @OA\Response(response=429, description="Too many attempts"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mobile' => 'required|string|min:10|max:10',
                'otp' => 'required|string|min:6|max:6',
                'device_id' => 'required|string|max:255',
                'device_name' => 'required|string|max:255',
                'platform' => 'required|string|in:android,Android,ios,iOS,web,Web',
                'platform_version' => 'string|max:50',
                'fcm_token' => 'string|max:255',
            ]);

            $result = $this->authService->verifyOtp(
                $validated['mobile'],
                $validated['otp'],
                $validated['device_id'],
                $validated['device_name'],
                $validated['platform'],
                $request
            );

            return $this->successResponse(
                $result['data'],
                $result['message'] ?? 'OTP verified successfully',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Verify OTP', [
                'mobile' => $request->input('mobile'),
                'ip_address' => $request->ip(),
            ]);
        }
    }

    /**
     * Get Profile
     *
     * Retrieves authenticated user details including roles and permissions.
     *
     * @OA\Get(
     *     path="/auth/me",
     *     operationId="getProfile",
     *     tags={"Authentication"},
     *     summary="Get authenticated user profile",
     *     description="Retrieve current user details",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="User profile retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="mobile", type="string"),
     *                 @OA\Property(property="role", type="string"),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user('sanctum');

            if (!$user) {
                return $this->unauthorizedResponse('No authenticated user found');
            }

            $result = $this->authService->getUserDetails($user);

            return $this->successResponse(
                $result['data'],
                $result['message'] ?? 'User profile retrieved successfully',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Profile', [
                'user_id' => auth('sanctum')->id(),
                'ip_address' => $request->ip(),
            ]);
        }
    }

    /**
     * Logout
     *
     * Revokes the current authentication token.
     *
     * @OA\Post(
     *     path="/auth/logout",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     summary="Logout authenticated user",
     *     description="Revoke current token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Logged out successfully"),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user('sanctum');

            if (!$user) {
                return $this->unauthorizedResponse('No authenticated user found');
            }

            Log::info('User logout initiated', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $result = $this->authService->logout($user);

            Log::info('User logged out successfully', [
                'user_id' => $user->id,
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->logAudit('logout', 'User', [], $user->id, 'success');

            return $this->successResponse(
                null,
                'Logged out successfully',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Logout', [
                'user_id' => auth('sanctum')->id(),
                'ip_address' => $request->ip(),
            ]);
        }
    }
}
