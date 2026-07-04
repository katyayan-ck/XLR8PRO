<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Core\DeviceSession;
use App\Models\Core\OtpAttemptLog;

class ValidateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json([
                'http_status' => 401,
                'success' => false,
                'code' => 'E002',
                'message' => 'Unauthorized: User not authenticated',
            ], 401);
        }

        $token = $user->currentAccessToken();

        if (!$token) {
            return response()->json([
                'http_status' => 401,
                'success' => false,
                'code' => 'E002',
                'message' => 'Unauthorized: No valid token',
            ], 401);
        }

        // ✅ FIX: Extract device_id from token abilities properly
        $abilities = $token->abilities;
        $deviceId = null;

        // Look for device_id in abilities (format: "device_id:xxxxx")
        foreach ($abilities as $ability) {
            if (strpos($ability, 'device_id:') === 0) {
                $deviceId = substr($ability, strlen('device_id:'));
                break;
            }
        }

        if (!$deviceId) {
            return response()->json([
                'http_status' => 401,
                'success' => false,
                'code' => 'E002',
                'message' => 'Unauthorized: No device bound',
            ], 401);
        }

        // Verify device session exists and is valid
        $deviceSession = DeviceSession::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->whereNull('deleted_at')
            ->first();

        if (!$deviceSession) {
            // Log security event
            OtpAttemptLog::create([
                'user_id' => $user->id,
                'mobile' => $user->phone,
                'action' => 'locked',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => 'Invalid or revoked device',
                'created_by' => $user->id,
            ]);

            return response()->json([
                'http_status' => 401,
                'success' => false,
                'code' => 'E002',
                'message' => 'Unauthorized: Invalid device session',
            ], 401);
        }

        // Update last activity
        $deviceSession->update([
            'last_active_at' => now(),
            'updated_by' => $user->id,
        ]);

        // Store device session in request for later use
        $request->attributes->set('device_session', $deviceSession);

        return $next($request);
    }
}
