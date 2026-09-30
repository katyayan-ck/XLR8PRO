<?php

namespace App\Models\IAM;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of Spatie's role → permission pivot (`permission.table_names.role_has_permissions`, DEC-093). Grants are
 * written only through Spatie (`givePermissionTo` / `syncPermissions`) or `RolePermissionService`, never here.
 *
 * @property int $permission_id
 * @property int $role_id
 */
class RoleHasPermission extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'permission_id';

    public function getTable(): string
    {
        return (string) config('permission.table_names.role_has_permissions', 'xlr8_iam_role_has_permissions');
    }
}
