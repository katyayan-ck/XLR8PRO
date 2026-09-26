<?php

namespace App\Models\Utilities\Docs;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class DocAccess extends BaseModel implements Auditable
{
    use AuditableTrait;
    use SoftDeletes;

    protected $table = 'xlr8_docs_access';

    protected $fillable = [
        'document_id',
        'user_id',
        'access_type',
        'access_combo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'access_combo' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
