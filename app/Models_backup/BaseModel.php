<?php

namespace App\Models;

use App\Models\Traits\HasColumnTransformations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;


abstract class BaseModel extends Model implements HasMedia
{
    use SoftDeletes,
        InteractsWithMedia,
        HasColumnTransformations,
        CrudTrait,
        HasFactory;

    protected $guarded = ['id'];

    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'extra_data' => 'array',
        'is_active'  => 'boolean',
    ];

    protected $appends = [];

    protected $hidden = [];

    protected array $columnTransformations = [];

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
    }

    protected static function resolveActorId(): ?int
    {
        if (app()->runningInConsole()) {
            return config('app.system_user_id');
        }

        return auth()->check() ? auth()->id() : 1;
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->toIso8601String();
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->useDisk('public');

        $this->addMediaCollection('photos')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
            ])
            ->useDisk('public');

        $this->addMediaCollection('attachments')
            ->useDisk('public');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->withTrashed()->whereNotNull('deleted_at');
    }

    public function scopeIncludingTrashed(Builder $query): Builder
    {
        return $query->withTrashed();
    }

    public function scopeOnlyRestored(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->whereNotNull('deleted_by');
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy('created_at');
    }

    public function scopeDateRange(Builder $query, string $column, $from, $to): Builder
    {
        return $query->whereBetween($column, [$from, $to]);
    }

    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    public function scopeUpdatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('updated_by', $userId);
    }

    public function scopeDeletedBy(Builder $query, int $userId): Builder
    {
        return $query->withTrashed()->where('deleted_by', $userId);
    }

    public function getCreatedAtForHumans(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getUpdatedAtForHumans(): string
    {
        return $this->updated_at->diffForHumans();
    }

    public function isRecentlyCreated(int $minutes = 5): bool
    {
        return now()->diffInMinutes($this->created_at) <= $minutes;
    }

    public function isRecentlyUpdated(int $minutes = 5): bool
    {
        return now()->diffInMinutes($this->updated_at) <= $minutes;
    }

    public function getCreationDetails(): array
    {
        return [
            'created_at'      => $this->created_at?->toIso8601String(),
            'created_by_id'   => $this->created_by,
            'created_by_name' => $this->createdByUser?->name ?? 'System',
        ];
    }

    public function getUpdateDetails(): array
    {
        return [
            'updated_at'      => $this->updated_at?->toIso8601String(),
            'updated_by_id'   => $this->updated_by,
            'updated_by_name' => $this->updatedByUser?->name ?? 'System',
        ];
    }

    public function getDeletionDetails(): ?array
    {
        if (! $this->deleted_at) {
            return null;
        }

        return [
            'deleted_at'      => $this->deleted_at->toIso8601String(),
            'deleted_by_id'   => $this->deleted_by,
            'deleted_by_name' => $this->deletedByUser?->name ?? 'System',
        ];
    }

    public function getAllAuditDetails(): array
    {
        return [
            'created' => $this->getCreationDetails(),
            'updated' => $this->getUpdateDetails(),
            'deleted' => $this->getDeletionDetails(),
        ];
    }

    public function isSoftDeleted(): bool
    {
        return $this->trashed();
    }

    public function wasEverDeleted(): bool
    {
        return $this->deleted_by !== null;
    }
}