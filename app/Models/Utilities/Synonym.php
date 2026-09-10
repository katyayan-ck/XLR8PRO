<?php

namespace App\Models\Utilities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Synonym extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_utils_synonyms';

    protected $fillable = [
        'entity_type',
        'canonical',
        'synonym',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeCanonical(Builder $query, string $canonical): Builder
    {
        return $query->where('canonical', $canonical);
    }
}
