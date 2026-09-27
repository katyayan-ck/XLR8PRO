<?php

namespace App\Services;

use App\Models\User;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Noty\Message;
use App\Models\Utilities\Noty\Notification;
use App\Services\Platform\Notify\NotifyService;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Legacy notification API kept as a thin adapter over the Notify platform service (DEC-061) for the
 * mobile v1 endpoints. New code uses the Notify facade. Direct messages (Message) stay here.
 */
class NotificationService
{
    public function __construct(
        protected FirebaseService $firebaseService,
        protected NotifyService $notify,
    ) {}

    /** @param  array<string, mixed>  $extra */
    public function sendAndLogNotification(User $recipient, string $type, string $title, string $description, string $entityType, int $entityId, array $extra = []): Notification
    {
        $dispatchId = $this->send('N', $recipient, $title, $description, $entityType, $entityId, $extra + ['action' => $type]);

        return Notification::query()->where('dispatch_id', $dispatchId)->where('user_id', $recipient->id)->firstOrFail();
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $extra
     * @return array<int, array<string, mixed>>
     */
    public function sendToMultipleUsers(array $userIds, string $type, string $title, string $description, string $entityType, int $entityId, array $extra = []): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            try {
                $results[$userId] = ['notification' => $this->sendAndLogNotification(User::query()->findOrFail($userId), $type, $title, $description, $entityType, $entityId, $extra), 'success' => true];
            } catch (Exception $e) {
                Log::error("Failed to send notification to user {$userId}: {$e->getMessage()}");
                $results[$userId] = ['error' => $e->getMessage(), 'success' => false];
            }
        }

        return $results;
    }

    /** @param  array<string, mixed>  $extra */
    public function sendAlert(User $recipient, string $severity, string $title, string $description, string $entityType, int $entityId, array $extra = []): Alert
    {
        $dispatchId = $this->send('A', $recipient, $title, $description, $entityType, $entityId, $extra + ['severity' => $severity]);

        return Alert::query()->where('dispatch_id', $dispatchId)->where('user_id', $recipient->id)->firstOrFail();
    }

    /** @param  array<int, mixed>  $attachments */
    public function sendMessage(User $sender, User $receiver, string $messageText, string $messageType = 'text', array $attachments = []): Message
    {
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'attachments' => $attachments !== [] ? $attachments : null,
            'created_by' => $sender->id,
            'updated_by' => $sender->id,
        ]);

        $fcmResult = $this->firebaseService->sendToUserDevices(
            $receiver,
            ['title' => "New message from {$sender->name}", 'body' => mb_strimwidth($messageText, 0, 50, '...')],
            ['action' => 'open_message', 'message_id' => (string) $message->id, 'sender_id' => (string) $sender->id]
        );
        if (($fcmResult['success'] ?? 0) > 0) {
            $message->markAsSent();
        }

        return $message;
    }

    public function markAsRead(Notification $notification): bool
    {
        return $this->notify->mark((int) $notification->user_id, (string) ($notification->kind ?: 'N'), $notification->id, NotifyService::READ)->ok;
    }

    public function markAllAsRead(User $user): int
    {
        $count = $this->getUnreadCount($user);
        $this->notify->markAll($user->id, 'N');

        return $count;
    }

    public function getUnreadCount(User $user): int
    {
        return $this->notify->counts($user->id)['notifications']['unread'];
    }

    /** @param  array<string, mixed>  $extra */
    private function send(string $kind, User $recipient, string $title, string $description, string $entityType, int $entityId, array $extra): int
    {
        $pending = $this->notify->to($recipient->id)->kind($kind)->about($entityType, $entityId)
            ->title($title)->body($description)->data(array_diff_key($extra, ['priority' => 1]))->notifySelf();
        if (isset($extra['priority'])) {
            $pending->priority((string) $extra['priority']);
        }
        $result = $pending->send();
        if (! $result->ok || ! $result->get('dispatch_id')) {
            throw new RuntimeException($result->message ?: 'Notification was not sent.');
        }

        return (int) $result->get('dispatch_id');
    }
}
