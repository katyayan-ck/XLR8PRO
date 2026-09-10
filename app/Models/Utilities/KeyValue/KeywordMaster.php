<?php

namespace App\Models\Utilities\KeyValue;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class KeywordMaster extends BaseModel
{
    use CrudTrait, HasFactory, HasColumnTransformations;

    protected $table = 'xlr8_utils_keyword_master';

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

    protected array $columnTransformations = [

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'keyword' => [
            'trim_spaces',
            'title_case'
        ],

        'description' => [
            'trim_spaces'
        ],

        'details' => [
            'trim_spaces'
        ],
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
