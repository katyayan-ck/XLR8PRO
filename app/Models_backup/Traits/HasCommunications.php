<?php
namespace App\Models\Traits;

use App\Models\Utilities\CommHistory\CommMaster;
use App\Services\Utils\EntityHistoryService;

trait HasCommunications
{
    public function commMaster()
    {
        return $this->morphOne(CommMaster::class, 'entityable');
    }

    public function getOrCreateCommMaster(): CommMaster
    {
        return $this->commMaster()->firstOrCreate([
            'entityable_type' => static::class,
            'entityable_id'   => $this->id,
        ], ['title' => $this->getCommMasterTitle()]);
    }

    public function addHistory(string $actionSlug, string $title, ?string $body = null, array $extraData = [], $parentThread = null, $actor = null)
    {
        return app(EntityHistoryService::class)->addThread(
            $this->getOrCreateCommMaster(), $actionSlug, $title, $body, $extraData, $parentThread, $actor
        );
    }

    protected function getCommMasterTitle(): string
    {
        return class_basename(static::class) . " #{$this->id}";
    }
}