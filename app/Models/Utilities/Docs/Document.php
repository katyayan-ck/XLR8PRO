<?php

namespace App\Models\Utilities\Docs;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends BaseModel implements Auditable, HasMedia
{
    use SoftDeletes;
    use AuditableTrait;
    use InteractsWithMedia;
protected $table = 'xlr8_docs_document';
    //Add to entities (e.g., Branch.php):
    //     public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    // {
    //     return $this->morphMany(Document::class, 'documentable');
    // }

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'title',
        'description',
        'category_id',
        'expiry_date',
        'tags',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Keyvalue::class, 'category_id');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(DocAccess::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(DocGroup::class, 'doc_group_documents');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['image/*', 'application/pdf', 'audio/*', 'video/*'])
            ->withResponsiveImages();
    }
}
