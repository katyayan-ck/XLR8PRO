<?php

namespace App\Models\Utilities\KeyValue;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use App\Models\Traits\HasTreeStructure;
use App\Services\Utils\KeyvalueService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Keyvalue extends BaseModel
{
    use CrudTrait,
        HasColumnTransformations,
        HasFactory,
        HasTreeStructure;

    protected $table = 'xlr8_utils_keyvalue';

    /** Field rules and transformations live in the entity service (DEC-050/055). */
    protected string $entityService = KeyvalueService::class;

    protected $fillable = [
        'keyword_code',
        'code',
        'key',
        'value',
        'details',
        'parent_id',
        'level',
        'path',
        'extra_data',
        'status',
        'is_active',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'level' => 'integer',
        'status' => 'integer',
        'is_active' => 'boolean',
    ];

    public function keywordMaster()
    {
        return $this->belongsTo(KeywordMaster::class, 'keyword_code', 'code');
    }

    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForKeyword(Builder $query, string $keywordCode): Builder
    {
        return $query->where('keyword_code', strtoupper($keywordCode));
    }

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {

            1 => 'Active',

            0 => 'Inactive',

            default => 'Unknown'
        };
    }
}
