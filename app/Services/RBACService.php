<?php

namespace App\Services;

use App\Models\Admin\Branch;
use App\Models\Admin\Designation;
use App\Models\Admin\Division;
use App\Models\Core\Brand;
use App\Models\Core\Color;
use App\Models\Core\Department;
use App\Models\Core\Location;
use App\Models\Core\Segment;
use App\Models\Core\SubSegment;
use App\Models\Core\Variant;
use App\Models\Core\VehicleModel;
use App\Models\Core\Vertical;
use App\Models\IAM\UserRoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * RBACService - Role-Based Access Control Service
 *
 * Centralized RBAC management including permission checking,
 * role assignment, and wildcard access handling.
 */
class RBACService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Check if user can access a specific resource/action
     *
     * @param  string  $resource  Resource name (e.g., 'branch', 'employee')
     * @param  string  $action  Action name (e.g., 'view', 'create', 'edit', 'delete')
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
                    if (! $assignment->isActive()) {
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
     * @param  string  $permission  Permission name (e.g., 'branch.edit')
     * @param  string  $grantedBy  Who granted this permission
     */
    public function grantPermission(
        User $user,
        string $permission,
        string $grantedBy = 'manual'
    ): bool {
        $perm = Permission::firstOrCreate(
            ['name' => $permission, 'guard_name' => 'web']
        );

        $user->givePermissionTo($perm);

        // Clear cache
        $this->clearUserPermissionCache($user);

        return true;
    }

    /**
     * Revoke permission from user
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
     */
    public function hasWildcardAccess(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Assign an ADDITIONAL role to a user for a given date range (e.g. a
     * temporary "additional charge"), on top of whatever role they already
     * have — this does not replace their primary role. Was previously a
     * complete no-op for real access control: it only wrote to
     * \App\Models\Core\UserRoleAssignment, a class that doesn't exist (the
     * real model is \App\Models\IAM\UserRoleAssignment), and even if that
     * were fixed, that table is a separate temporal-history record that
     * nothing in the permission-checking path reads — see
     * known-bugs-report.md BUG-071. Now does both: the temporal history
     * record AND the real Spatie grant that actually affects `->can()`.
     *
     * @param  string|Role  $role  Role name or instance
     * @param  \DateTime|null  $fromDate  Start date for role assignment
     * @param  \DateTime|null  $toDate  End date for role assignment — NOTE: nothing currently
     *                                  revokes the real Spatie grant automatically when this date
     *                                  passes; that would need a scheduled job, not built yet.
     * @return UserRoleAssignment
     */
    public function assignRole(
        User $user,
        $role,
        ?\DateTime $fromDate = null,
        ?\DateTime $toDate = null
    ) {
        // Get role instance if string provided
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $user->assignRole($role);
        $this->clearUserPermissionCache($user);

        return UserRoleAssignment::create([
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
     * @param  string|Role  $role
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
     * @param  string  $resourceType  Type of resource (branch, department, etc.)
     * @return Collection
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
     */
    private function getModelClassForResourceType(string $resourceType): string
    {
        $mapping = [
            'branch' => Branch::class,
            'location' => Location::class,
            'department' => Department::class,
            'division' => Division::class,
            'designation' => Designation::class,
            'vertical' => Vertical::class,
            'brand' => Brand::class,
            'segment' => Segment::class,
            'subsegment' => SubSegment::class,
            'vehiclemodel' => VehicleModel::class,
            'variant' => Variant::class,
            'color' => Color::class,
        ];

        return $mapping[$resourceType] ?? throw new \InvalidArgumentException(
            "Unknown resource type: {$resourceType}"
        );
    }

    /**
     * Clear permission cache for user
     */
    public function clearUserPermissionCache(User $user): void
    {
        Cache::forget("user.{$user->id}.permissions");
    }

    /**
     * Check if permission exists in system
     */
    public function permissionExists(string $permission): bool
    {
        return Permission::where('name', $permission)->exists();
    }

    /**
     * Get all available permissions for a module
     *
     * @param  string  $module  Module name
     */
    public function getModulePermissions(string $module): array
    {
        return Permission::where('name', 'like', "{$module}.%")
            ->pluck('name')
            ->toArray();
    }
}
