<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait HasAuditFields
 * 
 * Adds automatic tracking of who created, updated, and deleted records
 * 
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
trait HasAuditFields
{
    protected static function bootHasAuditFields(): void
    {
        // Automatically set created_by and updated_by on creation
        static::creating(function ($model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id();
            }

            if (auth()->check() && !$model->updated_by) {
                $model->updated_by = auth()->id();
            }
        });

        // Automatically set updated_by on update
        static::updating(function ($model) {
            if (auth()->check() && !$model->isDirty('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });

        // Automatically set deleted_by on soft delete
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                if (auth()->check() && !$model->deleted_by) {
                    $model->deleted_by = auth()->id();
                    $model->saveQuietly(); // Save without triggering events
                }
            }
        });
    }


    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    /**
     * Get the user who deleted this record
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by');
    }

    /**
     * Scope to filter by creator
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter by updater
     */
    public function scopeUpdatedBy($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }

    /**
     * Scope to filter by deleter
     */
    public function scopeDeletedBy($query, $userId)
    {
        return $query->where('deleted_by', $userId);
    }
}
