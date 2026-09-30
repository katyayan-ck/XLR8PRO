<?php

namespace App\Models\IAM;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of Spatie's model → role pivot (`permission.table_names.model_has_roles`, DEC-093). Roles are assigned
 * only through Spatie (`assignRole` / `syncRoles`), never here.
 *
 * @property int $role_id
 * @property string $model_type
 * @property int $model_id
 */
class ModelHasRole extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'role_id';

    public function getTable(): string
    {
        return (string) config('permission.table_names.model_has_roles', 'xlr8_iam_model_has_roles');
    }
}
