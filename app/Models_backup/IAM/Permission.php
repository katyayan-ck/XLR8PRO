<?php

namespace App\Models\IAM;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;
use App\Models\Traits\HasColumnTransformations;
use App\Models\IAM\Module;
use App\Models\IAM\Process;

class Permission extends SpatiePermission
{
    use CrudTrait, HasFactory, HasColumnTransformations;

    protected $fillable = [
        'name',
        'guard_name',
        'module_code',
        'process_code',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            // No additional casts needed for now
        ]);
    }

    protected array $columnTransformations = [
        'module_code'  => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'process_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
    ];

    // MANUAL AUDIT (Because it extends SpatiePermission, not BaseModel)

    protected static function resolveActorId(): ?int
    {
        if (app()->runningInConsole()) {
            return config('app.system_user_id');
        }
        return auth()->check() ? auth()->id() : null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $userId = self::resolveActorId();
            if ($userId && empty($model->created_by)) {
                $model->created_by = $userId;
            }
            if ($userId && empty($model->updated_by)) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function (self $model): void {
            $userId = self::resolveActorId();
            if ($userId) {
                $model->updated_by = $userId;
            }
        });
    }

    // RELATIONS (Code-based)

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function process()
    {
        return $this->belongsTo(Process::class, 'process_code', 'code');
    }
}

