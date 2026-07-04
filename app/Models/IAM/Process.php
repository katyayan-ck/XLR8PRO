<?php

namespace App\Models\IAM;

use App\Models\BaseModel;
use App\Models\IAM\Module;
use App\Models\IAM\Permission;

class Process extends BaseModel
{

    protected $table = 'xlr8_iam_process';

    protected $fillable = [
        'module_code',
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
        'code'        => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'module_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'        => ['trim_spaces', 'title_case'],
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONS (Code-based)
    // ─────────────────────────────────────────────────────────────

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'process_code', 'code');
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    // public function scopeActive($query)
    // {
    //     return $query->where('is_active', true);
    // }
}