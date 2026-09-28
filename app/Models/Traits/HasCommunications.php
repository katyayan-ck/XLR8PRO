<?php

namespace App\Models\Traits;

use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\Platform\Chat\ChatService;
use App\Support\Result;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;

/**
 * Opt a model into the Chat utility (FRS §3): `$model->addRemark()`, `$model->history()`,
 * `$model->recordEvent()`. `addHistory()` is the legacy name (Booking) and records an EVENT.
 */
trait HasCommunications
{
    public function commMaster(): MorphOne
    {
        return $this->morphOne(CommMaster::class, 'entityable');
    }

    public function getOrCreateCommMaster(): CommMaster
    {
        return app(ChatService::class)->master($this, $this->getCommMasterTitle());
    }

    /** @param  array<string, mixed>  $meta */
    public function recordEvent(string $action, string $summary, array $meta = []): CommThread
    {
        return app(ChatService::class)->event($this, $action, $summary, $meta);
    }

    public function addRemark(string $body, ?UploadedFile $file = null, ?int $parentId = null, bool $internal = false): Result
    {
        return app(ChatService::class)->remark($this, $body, $file, $parentId, $internal);
    }

    /** @return array{events: list<array<string, mixed>>, remarks: list<array<string, mixed>>, combined: list<array<string, mixed>>} */
    public function history(): array
    {
        return app(ChatService::class)->timeline($this);
    }

    /**
     * Legacy: records an EVENT on this record's conversation.
     *
     * @param  array<string, mixed>  $extraData
     */
    public function addHistory(string $actionSlug, string $title, ?string $body = null, array $extraData = [], $parentThread = null, $actor = null): CommThread
    {
        return app(ChatService::class)->eventOnMaster(
            $this->getOrCreateCommMaster(), $actionSlug, $title, $body, $extraData, $parentThread?->id, $actor?->id ?? (is_int($actor) ? $actor : null)
        );
    }

    protected function getCommMasterTitle(): string
    {
        return class_basename(static::class)." #{$this->getKey()}";
    }
}
