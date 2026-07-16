<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\HasColumnTransformations;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends SpatieRole implements HasMedia
{
    use CrudTrait, HasFactory, InteractsWithMedia, HasColumnTransformations, SoftDeletes;

    protected $table = 'xlr8_admin_designation';

    protected $fillable = [
        'code',
        'name',
        'guard_name',
        'description',
        'hierarchy_level',
        'rank',
        'category',
        'is_top_mgmt',
        'parent_desig_code',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_top_mgmt' => 'boolean',
    ];

    protected array $columnTransformations = [
        'code'              => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'parent_desig_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'              => ['trim_spaces', 'title_case'],
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // MANUAL AUDIT + SOFT DELETE HANDLING (Because it doesn't extend BaseModel)
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

        static::deleting(function (self $model): void {
            if ($model->isForceDeleting()) {
                return;
            }
            $userId = self::resolveActorId();
            if ($userId) {
                $model->deleted_by = $userId;
                $model->saveQuietly();
            }
        });

        static::restoring(function (self $model): void {
            $model->deleted_by = null;
            $model->saveQuietly();
        });

        // ── Parent Designation Validation ─────────────────────────────
        static::saving(function (self $model): void {
            if (!empty($model->parent_desig_code)) {

                // 1. Prevent self-parenting
                if ($model->parent_desig_code === $model->code) {
                    throw new \InvalidArgumentException('A designation cannot be its own parent.');
                }

                // 2. Check if parent exists
                $exists = static::where('code', $model->parent_desig_code)
                    ->when($model->exists, fn($q) => $q->where('id', '!=', $model->id))
                    ->exists();

                if (!$exists) {
                    throw new \InvalidArgumentException(
                        "Parent designation code '{$model->parent_desig_code}' does not exist."
                    );
                }

                // 3. Prevent circular hierarchy
                if ($model->wouldCreateCircularHierarchy($model->parent_desig_code)) {
                    throw new \InvalidArgumentException(
                        "Cannot set '{$model->parent_desig_code}' as parent — it is a descendant of this designation."
                    );
                }
            }
        });
    }

    /**
     * Check if setting the given code as parent would create a circular reference.
     */
    public function wouldCreateCircularHierarchy(string $proposedParentCode): bool
    {
        if (empty($proposedParentCode) || empty($this->code)) {
            return false;
        }
        if ($proposedParentCode === $this->code) {
            return true;
        }

        $current = $proposedParentCode;
        $visited = [];
        $depth = 0;

        while ($current && $depth < 50) {
            if (in_array($current, $visited, true)) return true;
            $visited[] = $current;

            if ($current === $this->code) return true;

            $current = static::where('code', $current)->value('parent_desig_code');
            $depth++;
        }

        return false;
    }

    // MEDIA COLLECTIONS

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('designation_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    // RELATIONS (Code-based)

    public function parentDesignation()
    {
        return $this->belongsTo(self::class, 'parent_desig_code', 'code');
    }

    public function childDesignations()
    {
        return $this->hasMany(self::class, 'parent_desig_code', 'code');
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\Admin\Employee::class, 'designation_code', 'code');
    }

    // ACCESSORS

    public function getRankLabelAttribute(): string
    {
        return match ((int) $this->rank) {
            1 => 'A',
            2 => 'B',
            3 => 'C',
            4 => 'D',
            5 => 'E',
            default => '-',
        };
    }
}
