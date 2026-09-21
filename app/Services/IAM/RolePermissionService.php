<?php

namespace App\Services\IAM;

use Spatie\Permission\Contracts\Role as RoleContract;

/**
 * Thin wrapper around Spatie's role<->permission assignment (Role has
 * permissions) so controllers/blades never call ->syncPermissions()/
 * ->permissions() directly — keeps the assignment logic in one place (SSOT).
 */
class RolePermissionService
{
    /** @param  array<int, string>  $permissionCodes */
    public function syncRolePermissions(RoleContract $role, array $permissionCodes): void
    {
        $role->syncPermissions($permissionCodes);
    }

    /** @return array<int, string> */
    public function currentPermissionCodes(RoleContract $role): array
    {
        return $role->permissions()->pluck('name')->all();
    }
}
