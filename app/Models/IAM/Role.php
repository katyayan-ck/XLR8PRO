<?php

namespace App\Models\IAM;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Extends Spatie Role.
 *
 * The table comes from config('permission.table_names.roles'), which is the designation
 * table (xlr8_admin_designation): Spatie's constructor overrides $table with that value, so
 * roles *are* designations at runtime. The old `xlr8_iam_roles` table (with the Post
 * discriminator) never existed; the is_post scopes below are kept only for the retired Post
 * code paths (DEC-018).
 */
class Role extends SpatieRole
{
    use CrudTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'is_post' => 'boolean',
        'is_active' => 'boolean',
        'is_top_mgmt' => 'boolean',
        'max_occupants' => 'integer',
        'seq_no' => 'integer',
        'metadata' => 'array',
    ];

    // ── Discriminator Scopes ─────────────────────────────────────────────

    /** Only system/non-post roles — used by RoleCrudController */
    public function scopeSystemRoles(Builder $q): Builder
    {
        return $q->where('is_post', false);
    }

    /** Only Post records */
    public function scopePosts(Builder $q): Builder
    {
        return $q->where('is_post', true);
    }

    /** Active records only */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function getPermissionsCountAttribute(): int
    {
        return $this->permissions->count();
    }
}

// namespace App\Models\IAM;

// use Spatie\Permission\Models\Role as SpatieRole;
// use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// class Role extends SpatieRole
// {
//     use CrudTrait;

//     protected $table = 'xlr8_iam_roles';
//     protected $guarded = [];

//     // Optional: Add any custom methods here

//     /**
//      * Get users with this role
//      */
//     public function users(): BelongsToMany
//     {
//         return $this->morphedByMany(
//             config('auth.providers.users.model'),
//             'model',
//             'model_has_roles',
//             'role_id',
//             'model_id'
//         );
//     }

//     /**
//      * Custom scope: Get active roles
//      */
//     public function scopeActive($query)
//     {
//         return $query->where('is_active', true);
//     }

//     /**
//      * Get permission count
//      */
//     public function getPermissionsCountAttribute()
//     {
//         return $this->permissions()->count();
//     }
// }
