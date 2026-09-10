<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * RBACService - Role-Based Access Control Service
 * 
 * Centralized RBAC management including permission checking,
 * role assignment, and wildcard access handling.
 * 
 * @package App\Services
 */
class RBACService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if user can access a specific resource/action
     * 
     * @param User $user
     * @param string $resource Resource name (e.g., 'branch', 'employee')
     * @param string $action Action name (e.g., 'view', 'create', 'edit', 'delete')
     * @return bool
     */
    public function canUserAccess(
        User $user,
        string $resource,
        string $action
    ): bool {
        // SuperAdmin has blanket access
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Build permission string
        $permission = "{$resource}.{$action}";

        // Check if user has permission via Spatie
        return $user->hasPermissionTo($permission);
    }

    /**
     * Get all permissions for a user from multiple sources
     * 
     * @param User $user
     * @return array Array of permission names
     */
    public function getUserPermissions(User $user): array
    {
        return Cache::remember(
            "user.{$user->id}.permissions",
            self::CACHE_TTL,
            function () use ($user) {
                if ($user->isSuperAdmin()) {
                    return ['*']; // Wildcard for SuperAdmin
                }

                $permissions = [];

                // From roles (Spatie)
                foreach ($user->roles as $role) {
                    $permissions = array_merge(
                        $permissions,
                        $role->permissions->pluck('name')->toArray()
                    );
                }

                // From post assignments (if employee)
                if ($user->employee) {
                    foreach ($user->employee->posts as $post) {
                        $permissions = array_merge(
                            $permissions,
                            $post->permissions
                                ->where('is_active', true)
                                ->pluck('name')
                                ->toArray()
                        );
                    }
                }

                // From user role assignments (with temporal checking)
                foreach ($user->userRoleAssignments as $assignment) {
                    if (!$assignment->isActive()) {
                        continue;
                    }

                    $permissions = array_merge(
                        $permissions,
                        $assignment->role->permissions->pluck('name')->toArray()
                    );
                }

                return array_unique($permissions);
            }
        );
    }

    /**
     * Grant permission to user
     * 
     * @param User $user
     * @param string $permission Permission name (e.g., 'branch.edit')
     * @param string $grantedBy Who granted this permission
     * @return bool
     */
    public function grantPermission(
        User $user,
        string $permission,
        string $grantedBy = 'manual'
    ): bool {
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => $permission, 'guard_name' => 'web']
        );

        $user->givePermissionTo($perm);

        // Clear cache
        $this->clearUserPermissionCache($user);

        return true;
    }

    /**
     * Revoke permission from user
     * 
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function revokePermission(User $user, string $permission): bool
    {
        $user->revokePermissionTo($permission);

        // Clear cache
        $this->clearUserPermissionCache($user);

        return true;
    }

    /**
     * Check if user has wildcard access (SuperAdmin)
     * 
     * @param User $user
     * @return bool
     */
    public function hasWildcardAccess(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Assign role to user with optional date range
     * 
     * @param User $user
     * @param string|\Spatie\Permission\Models\Role $role Role name or instance
     * @param \DateTime|null $fromDate Start date for role assignment
     * @param \DateTime|null $toDate End date for role assignment
     * @return \App\Models\UserRoleAssignment
     */
    public function assignRole(
        User $user,
        $role,
        ?\DateTime $fromDate = null,
        ?\DateTime $toDate = null
    ) {
        // Get role instance if string provided
        if (is_string($role)) {
            $role = \Spatie\Permission\Models\Role::where('name', $role)->firstOrFail();
        }

        return \App\Models\Core\UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'from_date' => $fromDate ?? now(),
            'to_date' => $toDate,
            'is_current' => true,
        ]);
    }

    /**
     * Remove role from user
     * 
     * @param User $user
     * @param string|\Spatie\Permission\Models\Role $role
     * @return bool
     */
    public function removeRole(User $user, $role): bool
    {
        $user->removeRole($role);

        // Clear cache
        $this->clearUserPermissionCache($user);

        return true;
    }

    /**
     * Get all accessible resources for user filtered by scopes
     * 
     * @param User $user
     * @param string $resourceType Type of resource (branch, department, etc.)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccessibleResources(
        User $user,
        string $resourceType
    ) {
        if ($user->isSuperAdmin()) {
            $modelClass = $this->getModelClassForResourceType($resourceType);
            return $modelClass::active()->get();
        }

        // Get scoped resources
        $scopeService = app(DataScopeService::class);
        $accessibleIds = $scopeService->getAccessibleIds($user, $resourceType);

        $modelClass = $this->getModelClassForResourceType($resourceType);

        if ($accessibleIds === null) {
            // Wildcard - all records
            return $modelClass::active()->get();
        }

        if (empty($accessibleIds)) {
            // No access
            return collect();
        }

        // Specific IDs
        return $modelClass::active()
            ->whereIn('id', $accessibleIds)
            ->get();
    }

    /**
     * Get model class for resource type
     * 
     * @param string $resourceType
     * @return string
     */
    private function getModelClassForResourceType(string $resourceType): string
    {
        $mapping = [
            'branch' => \App\Models\Admin\Branch::class,
            'location' => \App\Models\Core\Location::class,
            'department' => \App\Models\Core\Department::class,
            'division' => \App\Models\Admin\Division::class,
            'designation' => \App\Models\Admin\Designation::class,
            'vertical' => \App\Models\Core\Vertical::class,
            'brand' => \App\Models\Core\Brand::class,
            'segment' => \App\Models\Core\Segment::class,
            'subsegment' => \App\Models\Core\SubSegment::class,
            'vehiclemodel' => \App\Models\Core\VehicleModel::class,
            'variant' => \App\Models\Core\Variant::class,
            'color' => \App\Models\Core\Color::class,
        ];

        return $mapping[$resourceType] ?? throw new \InvalidArgumentException(
            "Unknown resource type: {$resourceType}"
        );
    }

    /**
     * Clear permission cache for user
     * 
     * @param User $user
     * @return void
     */
    public function clearUserPermissionCache(User $user): void
    {
        Cache::forget("user.{$user->id}.permissions");
    }

    /**
     * Check if permission exists in system
     * 
     * @param string $permission
     * @return bool
     */
    public function permissionExists(string $permission): bool
    {
        return \Spatie\Permission\Models\Permission::where('name', $permission)->exists();
    }

    /**
     * Get all available permissions for a module
     * 
     * @param string $module Module name
     * @return array
     */
    public function getModulePermissions(string $module): array
    {
        return \Spatie\Permission\Models\Permission::where('name', 'like', "{$module}.%")
            ->pluck('name')
            ->toArray();
    }
}
