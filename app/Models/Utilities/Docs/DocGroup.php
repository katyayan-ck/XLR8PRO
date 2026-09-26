<?php

namespace App\Models\Utilities\Docs;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class DocGroup extends BaseModel implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    protected $table = 'xlr8_docs_group';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'doc_group_documents');
    }
}
