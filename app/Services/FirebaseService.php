<?php

namespace App\Services;

use App\Models\Utilities\Noty\{Notification, Alert, Message};
use App\Models\IAM\UserDeviceToken;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FCMNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class FirebaseService
{
    protected $messaging;
    protected $projectId;

    public function __construct()
    {
        try {
            $factory = (new Factory())
                ->withServiceAccount(config('firebase.credentials'))
                ->withDefaultAuth();

            $this->messaging = $factory->createMessaging();
            $this->projectId = config('firebase.project_id');
        } catch (Exception $e) {
            Log::error('Firebase initialization failed: ' . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Register or update device FCM token
     */
    public function registerDeviceToken(
        User $user,
        string $deviceId,
        string $deviceName,
        string $platform,
        string $fcmToken,
        array $metadata = []
    ): UserDeviceToken {
        try {
            $device = UserDeviceToken::where('device_id', $deviceId)
                ->where('user_id', $user->id)
                ->first();

            if ($device) {
                // Update existing token
                $device->update([
                    'device_name' => $deviceName,
                    'platform' => $platform,
                    'fcm_token' => $fcmToken,
                    'is_active' => true,
                    'metadata' => array_merge($device->metadata ?? [], $metadata),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'updated_by' => $user->id,
                ]);
            } else {
                // Create new device token
                $device = UserDeviceToken::create([
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'platform' => $platform,
                    'fcm_token' => $fcmToken,
                    'is_active' => true,
                    'metadata' => $metadata,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            Log::info('Device token registered', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => $platform,
            ]);

            return $device;
        } catch (Exception $e) {
            Log::error('Failed to register device token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send FCM message to single device
     */
    public function sendToDevice(UserDeviceToken $device, array $notification, array $data = []): bool
    {
        try {
            if (!$device->isValid()) {
                Log::warning('Device token invalid or expired', ['device_id' => $device->device_id]);
                return false;
            }

            if (!$this->messaging) {
                Log::warning('Firebase not initialized');
                return false;
            }

            $message = $this->buildMessage($device, $notification, $data);

            $result = $this->messaging->send($message);

            $device->recordNotificationSent();

            Log::info('FCM message sent to device', [
                'device_id' => $device->device_id,
                'message_id' => $result,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send FCM message to device', [
                'device_id' => $device->device_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send FCM to all user devices
     */
    public function sendToUserDevices(User $user, array $notification, array $data = []): array
    {
        $devices = $user->deviceTokens()->active()->get();

        $results = [
            'success' => 0,
            'failed' => 0,
            'devices' => [],
        ];

        foreach ($devices as $device) {
            if ($this->sendToDevice($device, $notification, $data)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            $results['devices'][] = [
                'device_id' => $device->device_id,
                'sent' => $results['success'] > 0,
            ];
        }

        Log::info('Notifications sent to user devices', [
            'user_id' => $user->id,
            'success' => $results['success'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Send to multiple users
     */
    public function sendToMultipleUsers(
        array $userIds,
        array $notification,
        array $data = []
    ): array {
        $users = User::whereIn('id', $userIds)->get();

        $results = [
            'total' => count($userIds),
            'success' => 0,
            'failed' => 0,
            'by_user' => [],
        ];

        foreach ($users as $user) {
            $userResults = $this->sendToUserDevices($user, $notification, $data);
            if ($userResults['success'] > 0) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            $results['by_user'][$user->id] = $userResults;
        }

        return $results;
    }

    /**
     * Build platform-specific message
     */
    protected function buildMessage(
        UserDeviceToken $device,
        array $notification,
        array $data = []
    ): CloudMessage {
        $title = $notification['title'] ?? 'Notification';
        $body = $notification['body'] ?? '';

        $fcmNotification = FCMNotification::create($title, $body);

        $message = CloudMessage::withTarget('token', $device->fcm_token)
            ->withNotification($fcmNotification)
            ->withData($data);

        // Platform-specific options
        if ($device->platform === 'Android') {
            $message = $this->addAndroidOptions($message, $notification);
        } elseif ($device->platform === 'iOS') {
            $message = $this->addIOSOptions($message, $notification);
        } elseif ($device->platform === 'Web') {
            $message = $this->addWebOptions($message, $notification);
        }

        return $message;
    }

    /**
     * Add Android-specific options
     */
    protected function addAndroidOptions(CloudMessage $message, array $notification): CloudMessage
    {
        return $message->withAndroidConfig([
            'priority' => 'high',
            'notification' => [
                'sound' => 'default',
                'click_action' => $notification['click_action'] ?? null,
                'body_loc_key' => null,
                'body_loc_args' => [],
            ],
            'ttl' => '86400s', // 24 hours
        ]);
    }

    /**
     * Add iOS-specific options
     */
    protected function addIOSOptions(CloudMessage $message, array $notification): CloudMessage
    {
        return $message->withApnsConfig([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $notification['title'] ?? '',
                        'body' => $notification['body'] ?? '',
                    ],
                    'sound' => 'default',
                    'badge' => 1,
                    'content_available' => true,
                ],
            ],
        ]);
    }

    /**
     * Add Web-specific options
     */
    protected function addWebOptions(CloudMessage $message, array $notification): CloudMessage
    {
        return $message->withWebpushConfig([
            'notification' => [
                'title' => $notification['title'] ?? '',
                'body' => $notification['body'] ?? '',
                'icon' => asset('images/notification-icon.png'),
                'badge' => asset('images/notification-badge.png'),
            ],
            'fcm_options' => [
                'link' => $notification['click_action'] ?? url('/'),
            ],
        ]);
    }

    /**
     * Generate deep link for entity
     */
    public function generateDeepLink(string $entityType, int $entityId): string
    {
        $scheme = strtolower($entityType);
        return "vdms://{$scheme}/{$entityId}";
    }

    /**
     * Create payload with deep linking
     */
    public function createPayload(string $action, string $entityType, int $entityId, array $extra = []): array
    {
        return array_merge([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'deep_link' => $this->generateDeepLink($entityType, $entityId),
        ], $extra);
    }

    /**
     * Log notification sending
     */
    public function logNotificationSent(Notification $notification, array $fcmResult): void
    {
        $notification->update([
            'is_sent_via_fcm' => true,
            'sent_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        Log::info('Notification sent via FCM', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'fcm_result' => $fcmResult,
        ]);
    }

    /**
     * Get active devices for user
     */
    public function getUserActiveDevices(User $user): Collection
    {
        return $user->deviceTokens()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Revoke device token
     */
    public function revokeDevice(UserDeviceToken $device): bool
    {
        try {
            $device->deactivate();

            Log::info('Device token revoked', [
                'device_id' => $device->device_id,
                'user_id' => $device->user_id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to revoke device: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke all user devices
     */
    public function revokeAllUserDevices(User $user): int
    {
        $count = $user->deviceTokens()->update([
            'is_active' => false,
            'updated_by' => $user->id,
        ]);

        Log::info('All user devices revoked', [
            'user_id' => $user->id,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Cleanup expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $count = UserDeviceToken::where('token_expires_at', '<', now())
            ->update(['is_active' => false]);

        Log::info('Expired tokens cleaned up', ['count' => $count]);

        return $count;
    }

    /**
     * Test FCM connection
     */
    public function testConnection(): array
    {
        try {
            if (!$this->messaging) {
                return [
                    'success' => false,
                    'message' => 'Firebase not initialized',
                ];
            }

            return [
                'success' => true,
                'message' => 'Firebase connection successful',
                'project_id' => $this->projectId,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
