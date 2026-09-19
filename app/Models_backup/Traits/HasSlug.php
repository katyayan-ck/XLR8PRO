<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasSlug
 * 
 * Automatically generates slug from specified field
 * 
 * @property string $slug
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty($model->getSlugSourceField())) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Generate a unique slug
     */
    protected function generateSlug(): string
    {
        $sourceField = $this->getSlugSourceField();
        $slug = Str::slug($this->{$sourceField});
        $originalSlug = $slug;
        $count = 1;

        // Ensure uniqueness
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    /**
     * Get the field to generate slug from
     * Override this in your model if needed
     */
    protected function getSlugSourceField(): string
    {
        return property_exists($this, 'slugSourceField')
            ? $this->slugSourceField
            : 'name';
    }

    /**
     * Scope to find by slug
     */
    public function scopeSlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
