<?php

namespace App\Services\Utils;

use App\Jobs\SendHistoryNotification;
use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\Platform\Chat\ChatService;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy entity-history API kept as a thin adapter over ChatService (DEC-061), so existing callers
 * (Booking's `addHistory()`, the mobile v1 history endpoints) keep working. New code uses the
 * Chat facade: Chat::event(), Chat::remark(), Chat::timeline().
 */
class EntityHistoryService
{
    public function __construct(private readonly ChatService $chat) {}

    public function createMaster(Model $entity, ?string $title = null, mixed ...$ignored): CommMaster
    {
        return $this->chat->master($entity, $title);
    }

    /**
     * @param  array<string, mixed>  $extraData
     */
    public function addThread(
        CommMaster $master,
        string $actionSlug,
        ?string $title,
        ?string $body = null,
        array $extraData = [],
        ?CommThread $parentThread = null,
        mixed $actor = null
    ): CommThread {
        $media = $extraData['media'] ?? [];
        unset($extraData['media']);

        $thread = $this->chat->eventOnMaster(
            $master,
            $actionSlug,
            $title ?? ucwords(str_replace('_', ' ', $actionSlug)),
            $body,
            $extraData,
            $parentThread?->id,
            $actor?->id ?? (is_int($actor) ? $actor : null),
        );

        foreach ($media as $file) {
            $thread->addMedia($file)->toMediaCollection('attachments');
        }

        if (class_exists(SendHistoryNotification::class)) {
            SendHistoryNotification::dispatch($thread);
        }

        return $thread->load('actor', 'action', 'media');
    }

    public function getFullHistory(Model $entity): CommMaster
    {
        return $this->createMaster($entity)->load('threads.children.actor', 'threads.children.action', 'threads.media');
    }
}
