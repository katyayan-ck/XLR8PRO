<?php

namespace App\Models\Utilities\KeyValue;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Utils\KeywordMasterService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KeywordMaster extends BaseModel
{
    use CrudTrait, HasColumnTransformations, HasFactory;

    protected $table = 'xlr8_utils_keyword_master';

    /** Field rules and transformations live in the entity service (DEC-050/055). */
    protected string $entityService = KeywordMasterService::class;

    protected $fillable = [
        'code',
        'keyword',
        'description',
        'details',
        'extra_data',
        'status',
        'is_recursive',
        'is_active',
    ];

    protected $casts = [

        'is_active' => 'boolean',

        'is_recursive' => 'boolean',

        'extra_data' => 'array',

        'status' => 'integer',

    ];

    public function keyvalues()
    {
        return $this->hasMany(Keyvalue::class, 'keyword_code', 'code');
    }

    public function scopeRecursive(Builder $query): Builder
    {
        return $query->where('is_recursive', true);
    }

    public function scopeNonRecursive(Builder $query): Builder
    {
        return $query->where('is_recursive', false);
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
