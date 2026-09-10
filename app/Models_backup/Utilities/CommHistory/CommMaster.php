<?php
namespace App\Models\Utilities\CommHistory;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CommMaster extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'xlr8_utils_comm_master';
    protected $fillable = ['entityable_type', 'entityable_id', 'title', 'description', 'status_id', 'action_id', 'extra_data'];
    protected $casts = ['extra_data' => 'array'];

    public function entityable(): MorphTo { return $this->morphTo(); }
    public function threads(): HasMany { return $this->hasMany(CommThread::class, 'comm_master_id'); }
    public function rootThreads(): HasMany { return $this->threads()->whereNull('parent_id')->with('children'); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('master_attachments')->useDisk('public');
    }
}