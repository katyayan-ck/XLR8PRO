<?php

namespace App\Models\IAM;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;
use App\Models\IAM\Process;
use App\Models\IAM\Permission;

class Module extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_iam_module';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
        ]);
    }

    protected array $columnTransformations = [

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'description' => [
            'strip_tags',
            'trim_spaces'
        ],
    ];

    public function processes()
    {
        return $this->hasMany(
            Process::class,
            'module_code',
            'code'
        );
    }

    public function permissions()
    {
        return $this->hasManyThrough(
            Permission::class,
            Process::class,
            'module_code',
            'process_code',
            'code',
            'code'
        );
    }
}