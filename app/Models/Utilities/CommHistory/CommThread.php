<?php

namespace App\Models\Utilities\CommHistory;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Utilities\KeyValue\Keyvalue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CommThread extends BaseModel implements HasMedia
{
    use InteractsWithMedia, NodeTrait;

    protected $table = 'xlr8_utils_comm_thread';

    protected $fillable = ['comm_master_id', 'parent_id', 'actor_id', 'action_id', 'title', 'body', 'extra_data'];

    protected $casts = ['extra_data' => 'array', 'is_internal' => 'boolean', 'edited_at' => 'datetime'];

    public function master(): BelongsTo
    {
        return $this->belongsTo(CommMaster::class, 'comm_master_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Keyvalue::class, 'action_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/*', 'application/pdf', 'audio/*', 'video/*']);
    }
}
