<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated Its table (user_data_scopes) does not exist and has no
 *             migration. The live scope store is App\Models\Admin\UserScope
 *             (xlr8_admin_user_scopes, scope_code); resolve access through
 *             App\Services\IAM\DataScopeService. Still referenced only by
 *             the unrouted App\Imports\RulesUserImporter and
 *             App\Services\Importers\RulesUserImporter — delete with them.
 */
class UserDataScope extends Model
{
    use SoftDeletes;

    protected $table = 'user_data_scopes';

    protected $fillable = [
        'user_id',
        'scope_type',
        'scope_value',
        'hierarchy_level',
        'status',
    ];

    protected $casts = [
        'hierarchy_level' => 'integer',
        'scope_value' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Only active scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('deleted_at');
    }

    /**
     * Scope: By scope type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('scope_type', $type);
    }

    /**
     * Scope: By user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this scope is a wildcard (null = all instances)
     */
    public function isWildcard(): bool
    {
        return is_null($this->scope_value);
    }

    /**
     * Get display name for scope
     */
    public function getDisplayName(): string
    {
        if ($this->isWildcard()) {
            return "All {$this->scope_type}s";
        }

        $model = $this->getScopeModel();
        $instance = $model::find($this->scope_value);

        return $instance?->name ?? "Unknown {$this->scope_type}";
    }

    /**
     * Get the model class for this scope type
     */
    private function getScopeModel(): string
    {
        return match ($this->scope_type) {
            'branch' => Branch::class,
            'location' => Location::class,
            'department' => Department::class,
            'vertical' => Vertical::class,
            'segment' => Segment::class,
            'brand' => Brand::class,
            default => throw new \InvalidArgumentException("Unknown scope type: {$this->scope_type}"),
        };
    }
}
