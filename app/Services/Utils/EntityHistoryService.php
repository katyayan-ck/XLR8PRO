<?php
namespace App\Services\Utils;

use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\KeywordValueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Jobs\SendHistoryNotification;

class EntityHistoryService
{
    public function createMaster($entity, string $title = null): CommMaster
    {
        return CommMaster::firstOrCreate([
            'entityable_type' => get_class($entity),
            'entityable_id'   => $entity->id,
        ], [
            'title' => $title ?? class_basename($entity) . " #{$entity->id}",
        ]);
    }

    public function addThread(
        CommMaster $master,
        string $actionSlug,
        string $title,
        ?string $body = null,
        array $extraData = [],
        $parentThread = null,
        $actor = null
    ): CommThread {
        return DB::transaction(function () use ($master, $actionSlug, $title, $body, $extraData, $parentThread, $actor) {
            $actionId = KeywordValueService::getValueId('entity_actions', $actionSlug);

            $actorId = $actor?->id ?? auth()->user()?->current_post_id ?? auth()->id();

            // RBAC Check (customize as needed)
            // if (!Gate::allows('view-entity-history', $master->entityable)) {
            //     abort(403, 'You do not have permission to add history to this entity.');
            // }

            $thread = $master->threads()->create([
                'parent_id'  => $parentThread?->id,
                'actor_id'   => $actorId,
                'action_id'  => $actionId,
                'title'      => $title,
                'body'       => $body,
                'extra_data' => $extraData,
            ]);

            // Handle attachments if present in $extraData['media']
            if (!empty($extraData['media'])) {
                foreach ($extraData['media'] as $file) {
                    $thread->addMedia($file)->toMediaCollection('attachments');
                }
            }

            // Queue notification (non-blocking)
            SendHistoryNotification::dispatch($thread);

            return $thread->load('actor', 'action', 'media');
        });
    }

    public function getFullHistory($entity)
    {
        $master = $this->createMaster($entity);
        return $master->load('threads.children.actor', 'threads.children.action', 'threads.media');
    }
}