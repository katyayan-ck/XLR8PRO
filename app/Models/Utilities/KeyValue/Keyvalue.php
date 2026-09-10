<?php

namespace App\Models\Utilities\KeyValue;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasTreeStructure;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Traits\HasColumnTransformations;

class Keyvalue extends BaseModel
{
    use CrudTrait,
        HasFactory,
        HasTreeStructure,
        HasColumnTransformations;

    protected $table = 'xlr8_utils_keyvalue';

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
        'is_active'
    ];

    protected $casts = [
        'extra_data' => 'array',
        'level'      => 'integer',
        'status'     => 'integer',
        'is_active'  => 'boolean',
    ];

    protected array $columnTransformations = [

        'keyword_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'key' => [
            'trim_spaces'
        ],

        'value' => [
            'trim_spaces'
        ],

        'details' => [
            'trim_spaces'
        ],

        'path' => [
            'trim_spaces'
        ],
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function ($model) {
            $model->keyword_code = strtoupper(trim($model->keyword_code ?? ''));
            $model->code         = strtoupper(trim($model->code ?? $model->key ?? ''));
            $model->key          = strtoupper(trim($model->key ?? ''));
        });
    }

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
