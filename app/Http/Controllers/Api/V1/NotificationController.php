<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\ApplicationException;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\BaseController;
use App\Http\Resources\AlertResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\NotificationResource;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Noty\Message;
use App\Models\Utilities\Noty\Notification;
use App\Models\User;
use App\Models\IAM\UserDeviceToken;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="Notification and messaging endpoints for managing notifications, alerts, devices, and messages"
 * )
 */
class NotificationController extends BaseController
{
    /**
     * Constructor with service injection
     *
     * @param NotificationService $notificationService
     * @param FirebaseService $firebaseService
     */
    public function __construct(
        private NotificationService $notificationService,
        private FirebaseService $firebaseService
    ) {
        $this->middleware('auth:sanctum')->except([]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // DEVICE MANAGEMENT ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Register device FCM token
     *
     * Registers a new device with FCM token for push notifications.
     * Each device must have a unique device_id. Multiple devices per user allowed.
     * Device metadata can include additional info like OS version, app version, etc.
     *
     * @OA\Post(
     *     path="/devices/register",
     *     operationId="registerDevice",
     *     tags={"Notifications"},
     *     summary="Register device for push notifications",
     *     description="Register FCM token for device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id","device_name","platform","fcm_token"},
     *             @OA\Property(property="device_id", type="string", description="Unique device identifier"),
     *             @OA\Property(property="device_name", type="string", description="Device model/name"),
     *             @OA\Property(property="platform", type="string", enum={"android","ios","web"}),
     *             @OA\Property(property="platform_version", type="string", description="OS version"),
     *             @OA\Property(property="fcm_token", type="string", description="FCM registration token"),
     *             @OA\Property(property="metadata", type="object", description="Additional device info")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Device registered",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Device registered successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="device_id", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerDevice(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|string|max:255|unique:user_device_tokens,device_id,NULL,id,user_id,' . auth('sanctum')->id(),
                'device_name' => 'required|string|max:255',
                'platform' => 'required|string|in:android,Android,ios,iOS,web,Web',
                'platform_version' => 'string|max:50',
                'fcm_token' => 'required|string|max:255',
                'metadata' => 'array',
            ]);

            Log::info('Registering device', [
                'user_id' => auth('sanctum')->id(),
                'device_id' => $validated['device_id'],
                'platform' => $validated['platform'],
                'timestamp' => now()->toIso8601String(),
            ]);

            $device = $this->firebaseService->registerDeviceToken(
                auth('sanctum')->user(),
                $validated['device_id'],
                $validated['device_name'],
                strtolower($validated['platform']),  // Normalize to lowercase if needed
                $validated['fcm_token'],
                $validated['metadata'] ?? []
            );

            Log::info('Device registered successfully', [
                'device_id' => $device->id,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->logAudit(
                'register',
                'UserDeviceToken',
                $validated,
                $device->id,
                'success'
            );

            return $this->successResponse(
                $device,
                'Device registered',
                201
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Register Device Token', [
                'device_id' => $request->device_id,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Update device FCM token
     *
     * Updates the FCM token for an existing device.
     * Useful for token refresh without re-registering device.
     *
     * @OA\Put(
     *     path="/devices/{deviceId}",
     *     operationId="updateDeviceToken",
     *     tags={"Notifications"},
     *     summary="Update device FCM token",
     *     description="Refresh FCM token for registered device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="deviceId",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", description="New FCM token"),
     *             @OA\Property(property="metadata", type="object", description="Updated metadata")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Device updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="device_id", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Device not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @param string $deviceId
     * @return JsonResponse
     */
    public function updateDeviceToken(Request $request, string $deviceId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fcm_token' => 'required|string|max:255',
                'metadata' => 'array',
            ]);

            $device = UserDeviceToken::where('device_id', $deviceId)
                ->where('user_id', auth('sanctum')->id())
                ->firstOrFail();

            Log::info('Updating device token', [
                'device_id' => $deviceId,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $device->update([
                'fcm_token' => $validated['fcm_token'],
                'metadata' => $validated['metadata'] ?? $device->metadata,
                'updated_by' => auth('sanctum')->id(),
            ]);

            $this->logAudit(
                'update',
                'UserDeviceToken',
                $validated,
                $device->id,
                'success'
            );

            return $this->successResponse(
                $device,
                'Device token updated',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Device', $deviceId);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Update Device Token', [
                'device_id' => $deviceId,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Unregister device
     *
     * Removes a device and its FCM token.
     * The device will no longer receive push notifications.
     *
     * @OA\Delete(
     *     path="/devices/{deviceId}",
     *     operationId="unregisterDevice",
     *     tags={"Notifications"},
     *     summary="Unregister device",
     *     description="Remove device from notifications",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="deviceId",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device unregistered",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Device unregistered successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Device not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param string $deviceId
     * @return JsonResponse
     */
    public function unregisterDevice(string $deviceId): JsonResponse
    {
        try {
            $device = UserDeviceToken::where('device_id', $deviceId)
                ->where('user_id', auth('sanctum')->id())
                ->firstOrFail();

            Log::info('Unregistering device', [
                'device_id' => $deviceId,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $device->delete();

            $this->logAudit(
                'unregister',
                'UserDeviceToken',
                [],
                $device->id,
                'success'
            );

            return $this->successResponse(
                null,
                'Device unregistered',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Device', $deviceId);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Unregister Device', [
                'device_id' => $deviceId,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get user devices
     *
     * Retrieves list of registered devices for the authenticated user.
     *
     * @OA\Get(
     *     path="/devices",
     *     operationId="getUserDevices",
     *     tags={"Notifications"},
     *     summary="Get user devices",
     *     description="List registered devices",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Devices retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Devices retrieved"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="device_id", type="string"),
     *                     @OA\Property(property="device_name", type="string"),
     *                     @OA\Property(property="platform", type="string"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="last_used_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserDevices(Request $request): JsonResponse
    {
        try {
            $devices = UserDeviceToken::where('user_id', auth('sanctum')->id())
                ->orderBy('last_used_at', 'desc')
                ->get();

            return $this->successResponse(
                $devices,
                'User devices retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get User Devices', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // NOTIFICATION ENDPOINTS
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Get unread notifications count
     *
     * Retrieves count of unread notifications, alerts, and messages.
     *
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     operationId="getUnreadCount",
     *     tags={"Notifications"},
     *     summary="Get unread notifications count",
     *     description="Retrieve count of unread items",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Unread count retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="notifications", type="integer", example=5),
     *                 @OA\Property(property="alerts", type="integer", example=2),
     *                 @OA\Property(property="messages", type="integer", example=3),
     *                 @OA\Property(property="total", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();
            $master = $user->getOrCreateNotificationsMaster();

            $data = [
                'notifications' => $this->notificationService->getUnreadCount($user),
                'alerts' => Alert::unread()->forUser($user->id)->count(),
                'messages' => Message::unread()->forUser($user->id)->count(),
                'total' => $master->unread_count,
            ];

            return $this->successResponse(
                $data,
                'Unread count retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Unread Count', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get notifications
     *
     * Retrieves paginated list of notifications for the authenticated user.
     * Supports pagination and sorting.
     *
     * Pagination Parameters:
     * - page: Current page number (default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - sort_by: Field to sort by (default: created_at)
     * - sort_order: asc or desc (default: desc)
     * - type: Filter by notification type
     * - category: Filter by category
     * - priority: Filter by priority
     * - read_status: all/read/unread (default: all)
     *
     * @OA\Get(
     *     path="/notifications",
     *     operationId="getNotifications",
     *     tags={"Notifications"},
     *     summary="Get user notifications",
     *     description="Retrieve paginated notifications",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority",
     *         @OA\Schema(type="string", enum={"low","normal","high","critical"})
     *     ),
     *     @OA\Parameter(
     *         name="read_status",
     *         in="query",
     *         description="Filter by read status",
     *         @OA\Schema(type="string", enum={"all","read","unread"}, default="all")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Notifications retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(
     *                     property="notifications",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/NotificationResource")
     *                 ),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $query = Notification::forUser(auth('sanctum')->id())
                ->with('sender:id,name')
                ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

            if ($request->filled('type')) {
                $query->byType($request->type);
            }

            if ($request->filled('category')) {
                $query->byCategory($request->category);
            }

            if ($request->filled('priority')) {
                $query->byPriority($request->priority);
            }

            if ($request->read_status === 'unread') {
                $query->unread();
            } elseif ($request->read_status === 'read') {
                $query->read();
            }

            $notifications = $query->paginate($perPage);

            Log::info('Notifications retrieved', [
                'user_id' => auth('sanctum')->id(),
                'count' => $notifications->total(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->successResponse(
                [
                    'notifications' => NotificationResource::collection($notifications),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'last_page' => $notifications->lastPage(),
                    ],
                ],
                'Notifications retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Notifications', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get unread notifications
     *
     * Retrieves paginated list of unread notifications for the authenticated user.
     * Supports pagination and sorting.
     *
     * Pagination Parameters:
     * - page: Current page number (default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - sort_by: Field to sort by (default: created_at)
     * - sort_order: asc or desc (default: desc)
     *
     * @OA\Get(
     *     path="/notifications/unread",
     *     operationId="getUnreadNotifications",
     *     tags={"Notifications"},
     *     summary="Get unread notifications",
     *     description="Retrieve paginated unread notifications",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unread notifications retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Unread notifications retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(
     *                     property="notifications",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/NotificationResource")
     *                 ),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnreadNotifications(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $query = Notification::forUser(auth('sanctum')->id())
                ->with('sender:id,name')
                ->unread()
                ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

            $notifications = $query->paginate($perPage);

            Log::info('Unread notifications retrieved', [
                'user_id' => auth('sanctum')->id(),
                'count' => $notifications->total(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->successResponse(
                [
                    'notifications' => NotificationResource::collection($notifications),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'last_page' => $notifications->lastPage(),
                    ],
                ],
                'Unread notifications retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Unread Notifications', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get alerts
     *
     * Retrieves paginated list of alerts for the authenticated user.
     * Supports pagination and sorting.
     *
     * Pagination Parameters:
     * - page: Current page number (default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - sort_by: Field to sort by (default: created_at)
     * - sort_order: asc or desc (default: desc)
     * - severity: Filter by severity (info/warning/critical)
     * - read_status: all/read/unread (default: all)
     *
     * @OA\Get(
     *     path="/alerts",
     *     operationId="getAlerts",
     *     tags={"Notifications"},
     *     summary="Get user alerts",
     *     description="Retrieve paginated alerts",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="severity",
     *         in="query",
     *         description="Filter by severity",
     *         @OA\Schema(type="string", enum={"info","warning","critical"})
     *     ),
     *     @OA\Parameter(
     *         name="read_status",
     *         in="query",
     *         description="Filter by read status",
     *         @OA\Schema(type="string", enum={"all","read","unread"}, default="all")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alerts retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Alerts retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(
     *                     property="alerts",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/AlertResource")
     *                 ),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $query = Alert::forUser(auth('sanctum')->id())
                ->with('sender:id,name')
                ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

            if ($request->filled('severity')) {
                $query->bySeverity($request->severity);
            }

            if ($request->read_status === 'unread') {
                $query->unread();
            } elseif ($request->read_status === 'read') {
                $query->where('is_read', true);
            }

            $alerts = $query->paginate($perPage);

            Log::info('Alerts retrieved', [
                'user_id' => auth('sanctum')->id(),
                'count' => $alerts->total(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->successResponse(
                [
                    'alerts' => AlertResource::collection($alerts),
                    'pagination' => [
                        'current_page' => $alerts->currentPage(),
                        'per_page' => $alerts->perPage(),
                        'total' => $alerts->total(),
                        'last_page' => $alerts->lastPage(),
                    ],
                ],
                'Alerts retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Alerts', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get messages
     *
     * Retrieves paginated list of messages for the authenticated user.
     * Supports pagination and sorting.
     *
     * Pagination Parameters:
     * - page: Current page number (default: 1)
     * - per_page: Items per page (default: 20, max: 100)
     * - sort_by: Field to sort by (default: created_at)
     * - sort_order: asc or desc (default: desc)
     * - type: Filter by message type (text/image/document)
     * - sender_id: Filter by sender ID
     * - read_status: all/read/unread (default: all)
     *
     * @OA\Get(
     *     path="/messages",
     *     operationId="getMessages",
     *     tags={"Notifications"},
     *     summary="Get user messages",
     *     description="Retrieve paginated messages",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type",
     *         @OA\Schema(type="string", enum={"text","image","document"})
     *     ),
     *     @OA\Parameter(
     *         name="sender_id",
     *         in="query",
     *         description="Filter by sender ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="read_status",
     *         in="query",
     *         description="Filter by read status",
     *         @OA\Schema(type="string", enum={"all","read","unread"}, default="all")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Messages retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(
     *                     property="messages",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/MessageResource")
     *                 ),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMessages(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $query = Message::forUser(auth('sanctum')->id())
                ->with(['sender:id,name,avatar_url', 'receiver:id,name'])
                ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

            if ($request->filled('type')) {
                $query->where('message_type', $request->type);
            }

            if ($request->filled('sender_id')) {
                $query->where('sender_id', $request->sender_id);
            }

            if ($request->read_status === 'unread') {
                $query->unread();
            } elseif ($request->read_status === 'read') {
                $query->where('is_read', true);
            }

            $messages = $query->paginate($perPage);

            Log::info('Messages retrieved', [
                'user_id' => auth('sanctum')->id(),
                'count' => $messages->total(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->successResponse(
                [
                    'messages' => MessageResource::collection($messages),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'last_page' => $messages->lastPage(),
                    ],
                ],
                'Messages retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Messages', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Get conversation messages
     *
     * Retrieves paginated conversation messages between authenticated user and another user.
     * Supports pagination and sorting.
     *
     * @OA\Get(
     *     path="/messages/conversation/{userId}",
     *     operationId="getConversationMessages",
     *     tags={"Notifications"},
     *     summary="Get conversation messages",
     *     description="Retrieve messages with specific user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="Other user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Conversation retrieved"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(
     *                     property="messages",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/MessageResource")
     *                 ),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function getConversationMessages(Request $request, int $userId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $messages = Message::conversation(auth('sanctum')->id(), $userId)
                ->with(['sender:id,name,avatar_url', 'receiver:id,name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            Log::info('Conversation messages retrieved', [
                'user_id' => auth('sanctum')->id(),
                'other_user_id' => $userId,
                'count' => $messages->total(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->successResponse(
                [
                    'messages' => MessageResource::collection($messages),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'last_page' => $messages->lastPage(),
                    ],
                ],
                'Conversation messages retrieved',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Conversation Messages', [
                'user_id' => auth('sanctum')->id(),
                'other_user_id' => $userId,
            ]);
        }
    }

    /**
     * Send message
     *
     * Sends a message to another user.
     * Supports text, image, document types.
     * Attachments are array of URLs.
     *
     * @OA\Post(
     *     path="/messages/send/{receiverId}",
     *     operationId="sendMessage",
     *     tags={"Notifications"},
     *     summary="Send message to user",
     *     description="Send text or attachment message",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="receiverId",
     *         in="path",
     *         required=true,
     *         description="Receiver user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_text"},
     *             @OA\Property(property="message_text", type="string", maxLength=2000, example="Hello, how are you?"),
     *             @OA\Property(property="message_type", type="string", enum={"text","image","document"}, example="text"),
     *             @OA\Property(property="attachments", type="array", nullable=true, @OA\Items(type="string"), description="Array of attachment URLs")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Message sent successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/MessageResource")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @param int $receiverId
     * @return JsonResponse
     */
    public function sendMessage(Request $request, int $receiverId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message_text' => 'required_without:attachments|string|max:2000',
                'message_type' => 'required|string|in:text,image,document',
                'attachments' => 'array',
                'attachments.*' => 'string|url',
            ]);

            $receiver = User::findOrFail($receiverId);

            Log::info('Sending message', [
                'sender_id' => auth('sanctum')->id(),
                'receiver_id' => $receiverId,
                'type' => $validated['message_type'],
                'timestamp' => now()->toIso8601String(),
            ]);

            $message = $this->notificationService->sendMessage(
                auth('sanctum')->user(),
                $receiver,
                $validated['message_text'] ?? '',
                $validated['message_type'],
                $validated['attachments'] ?? []
            );

            $this->logAudit(
                'send',
                'Message',
                $validated,
                $message->id,
                'success'
            );

            return $this->successResponse(
                new MessageResource($message),
                'Message sent',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('User', $receiverId);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Send Message', [
                'receiver_id' => $receiverId,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Mark notification as read
     *
     * Marks a specific notification as read.
     *
     * @OA\Put(
     *     path="/notifications/{id}/read",
     *     operationId="markNotificationAsRead",
     *     tags={"Notifications"},
     *     summary="Mark notification as read",
     *     description="Update notification read status",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Notification marked as read"),
     *             @OA\Property(property="data", ref="#/components/schemas/NotificationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markNotificationAsRead(int $id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);

            if ($notification->user_id !== auth('sanctum')->id()) {
                Log::warning('Unauthorized notification read attempt', [
                    'notification_id' => $id,
                    'user_id' => auth('sanctum')->id(),
                    'owner_id' => $notification->user_id,
                    'timestamp' => now()->toIso8601String(),
                ]);

                return $this->forbiddenResponse('read', 'Notification');
            }

            Log::info('Marking notification as read', [
                'notification_id' => $id,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $notification->markAsRead();

            $this->logAudit(
                'read',
                'Notification',
                ['notification_id' => $id],
                $id,
                'success'
            );

            return $this->successResponse(
                new NotificationResource($notification),
                'Notification marked as read',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Notification', $id);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Mark Notification As Read', [
                'notification_id' => $id,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Mark all notifications as read
     *
     * Marks all unread notifications as read for the authenticated user.
     *
     * @OA\Put(
     *     path="/notifications/mark-all-read",
     *     operationId="markAllNotificationsAsRead",
     *     tags={"Notifications"},
     *     summary="Mark all notifications as read",
     *     description="Update all unread notifications to read",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="All notifications marked as read"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="count", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();

            Log::info('Marking all notifications as read', [
                'user_id' => $user->id,
                'timestamp' => now()->toIso8601String(),
            ]);

            $count = $this->notificationService->markAllAsRead($user);

            $this->logAudit(
                'mark_all_read',
                'Notification',
                [],
                null,
                'success'
            );

            return $this->successResponse(
                ['count' => $count],
                'All notifications marked as read',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Mark All Notifications As Read', [
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Delete notification
     *
     * Deletes a specific notification.
     *
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     operationId="deleteNotification",
     *     tags={"Notifications"},
     *     summary="Delete notification",
     *     description="Remove notification",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Notification deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteNotification(int $id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);

            if ($notification->user_id !== auth('sanctum')->id()) {
                Log::warning('Unauthorized notification delete attempt', [
                    'notification_id' => $id,
                    'user_id' => auth('sanctum')->id(),
                    'owner_id' => $notification->user_id,
                    'timestamp' => now()->toIso8601String(),
                ]);

                return $this->forbiddenResponse('delete', 'Notification');
            }

            Log::info('Deleting notification', [
                'notification_id' => $id,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $notification->delete();

            $this->logAudit(
                'delete',
                'Notification',
                [],
                $id,
                'success'
            );

            return $this->successResponse(
                null,
                'Notification deleted',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Notification', $id);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Delete Notification', [
                'notification_id' => $id,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Mark alert as read
     *
     * Marks a specific alert as read.
     *
     * @OA\Put(
     *     path="/alerts/{id}/read",
     *     operationId="markAlertAsRead",
     *     tags={"Notifications"},
     *     summary="Mark alert as read",
     *     description="Update alert read status",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Alert ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alert marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Alert marked as read"),
     *             @OA\Property(property="data", ref="#/components/schemas/AlertResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Alert not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAlertAsRead(int $id): JsonResponse
    {
        try {
            $alert = Alert::findOrFail($id);

            if ($alert->user_id !== auth('sanctum')->id()) {
                Log::warning('Unauthorized alert read attempt', [
                    'alert_id' => $id,
                    'user_id' => auth('sanctum')->id(),
                    'owner_id' => $alert->user_id,
                    'timestamp' => now()->toIso8601String(),
                ]);

                return $this->forbiddenResponse('read', 'Alert');
            }

            Log::info('Marking alert as read', [
                'alert_id' => $id,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $alert->markAsRead();

            $this->logAudit(
                'read',
                'Alert',
                ['alert_id' => $id],
                $id,
                'success'
            );

            return $this->successResponse(
                new AlertResource($alert),
                'Alert marked as read',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Alert', $id);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Mark Alert As Read', [
                'alert_id' => $id,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }

    /**
     * Mark message as read
     *
     * Marks a specific message as read.
     * Only receiver can mark as read.
     *
     * @OA\Put(
     *     path="/messages/{id}/read",
     *     operationId="markMessageAsRead",
     *     tags={"Notifications"},
     *     summary="Mark message as read",
     *     description="Update message read status",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Message ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Message marked as read"),
     *             @OA\Property(property="data", ref="#/components/schemas/MessageResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Message not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markMessageAsRead(int $id): JsonResponse
    {
        try {
            $message = Message::findOrFail($id);

            if ($message->receiver_id !== auth('sanctum')->id()) {
                Log::warning('Unauthorized message read attempt', [
                    'message_id' => $id,
                    'user_id' => auth('sanctum')->id(),
                    'receiver_id' => $message->receiver_id,
                    'timestamp' => now()->toIso8601String(),
                ]);

                return $this->forbiddenResponse('read', 'Message');
            }

            Log::info('Marking message as read', [
                'message_id' => $id,
                'user_id' => auth('sanctum')->id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $message->markAsRead();

            $this->logAudit(
                'read',
                'Message',
                ['message_id' => $id],
                $message->id,
                'success'
            );

            return $this->successResponse(
                new MessageResource($message),
                'Message marked as read',
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Message', $id);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Mark Message As Read', [
                'message_id' => $id,
                'user_id' => auth('sanctum')->id(),
            ]);
        }
    }
}
