<?php
namespace App\Models\Utilities\CommHistory;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CommThread extends BaseModel implements HasMedia
{
    use NodeTrait, InteractsWithMedia;

    protected $table = 'xlr8_utils_comm_thread';
    protected $fillable = ['comm_master_id', 'parent_id', 'actor_id', 'action_id', 'title', 'body', 'extra_data'];
    protected $casts = ['extra_data' => 'array'];

    public function master(): BelongsTo { return $this->belongsTo(CommMaster::class, 'comm_master_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function actor() { return $this->belongsTo(\App\Models\User::class, 'actor_id'); }
    public function action() { return $this->belongsTo(\App\Models\Utilities\KeyValue\KeyValue::class, 'action_id'); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
             ->useDisk('public')
             ->acceptsMimeTypes(['image/*','application/pdf','audio/*','video/*']);
    }
}