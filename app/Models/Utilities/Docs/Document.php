<?php

namespace App\Models\Utilities\Docs;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Utilities\KeyValue\Keyvalue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A document, image or information card (FRS §4), attached to a record and/or filed in the library
 * (path Entity → Location → Category → Sub-category → Item → FY). Written only by DocsService.
 * BUG-139: the model pointed at non-existent `xlr8_docs_*` tables; DEC-061 fixes the mapping.
 */
class Document extends BaseModel implements Auditable, HasMedia
{
    use AuditableTrait;
    use InteractsWithMedia;

    protected $table = 'xlr8_utils_docs_document';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'kind',
        'collection',
        'title',
        'description',
        'category_id',
        'expiry_date',
        'tags',
        'path_entity',
        'path_location',
        'path_category',
        'path_sub',
        'path_item',
        'fy',
        'info_body',
        'owner_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'expiry_date' => 'date',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Keyvalue::class, 'category_id');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(DocAccess::class, 'document_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(DocGroup::class, 'xlr8_utils_docs_group_pivot', 'document_id', 'doc_group_id')->withTimestamps();
    }

    /** Library breadcrumb (FRS DOC-08). @return list<string> */
    public function libraryPath(): array
    {
        return array_values(array_filter([$this->path_entity, $this->path_location, $this->path_category, $this->path_sub, $this->path_item, $this->fy]));
    }

    public function registerMediaCollections(): void
    {
        foreach (config('platform.docs.collections', ['docs']) as $collection) {
            $this->addMediaCollection($collection)->useDisk(config('platform.docs.disk', 'public'));
        }
        $this->addMediaCollection('documents')->useDisk(config('platform.docs.disk', 'public'));
    }
}
