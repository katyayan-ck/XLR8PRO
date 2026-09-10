<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait HasTreeStructure
 * 
 * Provides hierarchical tree functionality with materialized path
 * 
 * Required fields:
 * - parent_id (nullable foreign key to self)
 * - level (integer)
 * - path (text, stores '/1/5/12' format)
 * 
 * Usage:
 * use HasTreeStructure;
 * 
 * Auto-maintains:
 * - level (depth in tree)
 * - path (materialized path for fast queries)
 */
trait HasTreeStructure
{
    /**
     * Boot the trait
     */
    protected static function bootHasTreeStructure(): void
    {
        static::creating(function ($model) {
            $model->updateTreeFields();
        });

        static::updating(function ($model) {
            if ($model->isDirty('parent_id')) {
                $model->updateTreeFields();
                $model->updateDescendantsPath();
            }
        });
    }

    /**
     * Parent relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Children relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Update level and path
     */
    protected function updateTreeFields(): void
{
    if ($this->parent_id) {
        $parent = static::find($this->parent_id);
        if ($parent) {
            $this->level = $parent->level + 1;
            $this->path = $parent->path . '/' . $this->parent_id;
        } else {
            $this->parent_id = null;
            $this->level = 0;
            $this->path = '';
        }
    } else {
        $this->level = 0;
        $this->path = '';
    }
}

    /**
     * Update all descendants' paths
     */
    protected function updateDescendantsPath(): void
    {
        foreach ($this->children as $child) {
            $child->updateTreeFields();
            $child->saveQuietly();
            $child->updateDescendantsPath();
        }
    }

    /**
     * Get all ancestors
     */
    public function ancestors()
    {
        if (!$this->path) {
            return collect();
        }

        $ids = array_filter(explode('/', $this->path));

        return static::whereIn('id', $ids)->orderBy('level')->get();
    }

    /**
     * Get all descendants
     */
    public function descendants()
    {
        return static::where('path', 'like', $this->path . '/' . $this->id . '%')->get();
    }

    /**
     * Get siblings
     */
    public function siblings()
    {
        return static::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Check if this is ancestor of another node
     */
    public function isAncestorOf($node): bool
    {
        return str_contains($node->path, '/' . $this->id . '/') ||
            str_ends_with($node->path, '/' . $this->id);
    }

    /**
     * Check if this is descendant of another node
     */
    public function isDescendantOf($node): bool
    {
        return str_contains($this->path, '/' . $node->id . '/') ||
            str_ends_with($this->path, '/' . $node->id);
    }

    /**
     * Get root nodes (level 0)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orWhere('level', 0);
    }

    /**
     * Get tree structure as nested array
     */
    public static function tree()
    {
        $items = static::orderBy('sort_order')->get();

        return static::buildTree($items);
    }

    /**
     * Build nested tree structure
     */
    protected static function buildTree($items, $parentId = null)
    {
        $branch = [];

        foreach ($items as $item) {
            if ($item->parent_id == $parentId) {
                $children = static::buildTree($items, $item->id);

                if ($children) {
                    $item->children_tree = $children;
                }

                $branch[] = $item;
            }
        }

        return $branch;
    }

    /**
     * Get breadcrumb path
     */
    public function breadcrumb($separator = ' > ')
    {
        $ancestors = $this->ancestors();
        $ancestors->push($this);

        return $ancestors->pluck('name')->implode($separator);
    }

    /**
     * Scope: By level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }
}
