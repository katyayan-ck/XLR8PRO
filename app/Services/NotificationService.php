<?php

namespace App\Services;

use App\Models\Utilities\Noty\{Notification, Alert, Message};
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification to user and persist in DB
     */
    public function sendAndLogNotification(
        User $recipient,
        string $type,
        string $title,
        string $description,
        string $entityType,
        int $entityId,
        array $extra = []
    ): Notification {
        try {
            // Create payload with deep linking
            $payload = $this->firebaseService->createPayload(
                action: $type,
                entityType: $entityType,
                entityId: $entityId,
                extra: $extra
            );

            // Create notification record
            $notification = Notification::create([
                'user_id' => $recipient->id,
                'sender_id' => auth()->id(),
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'reference_type' => $entityType,
                'reference_id' => $entityId,
                'priority' => $extra['priority'] ?? 'normal',
                'category' => $extra['category'] ?? null,
                'payload' => $payload,
                'metadata' => $extra['metadata'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Send via FCM
            $fcmResult = $this->firebaseService->sendToUserDevices(
                $recipient,
                [
                    'title' => $title,
                    'body' => $description,
                    'click_action' => $payload['deep_link'],
                ],
                [
                    'action' => $type,
                    'entity_type' => $entityType,
                    'entity_id' => (string)$entityId,
                ]
            );

            // Update notification with FCM status
            if ($fcmResult['success'] > 0) {
                $this->firebaseService->logNotificationSent($notification, $fcmResult);
            }

            // Update notifications master
            $recipient->getOrCreateNotificationsMaster()->incrementUnreadCount();

            return $notification;
        } catch (Exception $e) {
            Log::error('Failed to send and log notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send to multiple users
     */
    public function sendToMultipleUsers(
        array $userIds,
        string $type,
        string $title,
        string $description,
        string $entityType,
        int $entityId,
        array $extra = []
    ): array {
        $results = [];

        foreach ($userIds as $userId) {
            try {
                $user = User::findOrFail($userId);
                $results[$userId] = [
                    'notification' => $this->sendAndLogNotification(
                        $user,
                        $type,
                        $title,
                        $description,
                        $entityType,
                        $entityId,
                        $extra
                    ),
                    'success' => true,
                ];
            } catch (Exception $e) {
                Log::error("Failed to send notification to user {$userId}: {$e->getMessage()}");
                $results[$userId] = [
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Send alert to user
     */
    public function sendAlert(
        User $recipient,
        string $severity,
        string $title,
        string $description,
        string $entityType,
        int $entityId,
        array $extra = []
    ): Alert {
        try {
            $payload = $this->firebaseService->createPayload(
                action: 'alert',
                entityType: $entityType,
                entityId: $entityId,
                extra: $extra
            );

            $alert = Alert::create([
                'user_id' => $recipient->id,
                'sender_id' => auth()->id(),
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'reference_type' => $entityType,
                'reference_id' => $entityId,
                'payload' => $payload,
                'metadata' => $extra['metadata'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Send via FCM
            $fcmResult = $this->firebaseService->sendToUserDevices(
                $recipient,
                [
                    'title' => "🚨 {$title}",
                    'body' => $description,
                ],
                [
                    'action' => 'alert',
                    'severity' => $severity,
                    'entity_type' => $entityType,
                    'entity_id' => (string)$entityId,
                ]
            );

            if ($fcmResult['success'] > 0) {
                $alert->update([
                    'is_sent_via_fcm' => true,
                    'sent_at' => now(),
                ]);
            }

            return $alert;
        } catch (Exception $e) {
            Log::error('Failed to send alert: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send message between users
     */
    public function sendMessage(
        User $sender,
        User $receiver,
        string $messageText,
        string $messageType = 'text',
        array $attachments = []
    ): Message {
        try {
            $message = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message_text' => $messageText,
                'message_type' => $messageType,
                'attachments' => !empty($attachments) ? $attachments : null,
                'created_by' => $sender->id,
                'updated_by' => $sender->id,
            ]);

            // Send FCM notification for new message
            $fcmResult = $this->firebaseService->sendToUserDevices(
                $receiver,
                [
                    'title' => "💬 New message from {$sender->name}",
                    'body' => strlen($messageText) > 50
                        ? substr($messageText, 0, 47) . '...'
                        : $messageText,
                ],
                [
                    'action' => 'open_message',
                    'message_id' => (string)$message->id,
                    'sender_id' => (string)$sender->id,
                ]
            );

            if ($fcmResult['success'] > 0) {
                $message->markAsSent();
            }

            return $message;
        } catch (Exception $e) {
            Log::error('Failed to send message: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): bool
    {
        try {
            $notification->markAsRead();
            return true;
        } catch (Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(User $user): int
    {
        try {
            $count = $user->notifications()
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            $user->getOrCreateNotificationsMaster()->markAllAsRead();

            return $count;
        } catch (Exception $e) {
            Log::error('Failed to mark all as read: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(User $user): int
    {
        return $user->notifications()
            ->where('is_read', false)
            ->count();
    }
}
