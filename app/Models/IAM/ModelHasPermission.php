<?php

namespace App\Models\IAM;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model of Spatie's direct model → permission pivot (`permission.table_names.model_has_permissions`, DEC-093).
 * Direct permissions are given only through Spatie, never here.
 *
 * @property int $permission_id
 * @property string $model_type
 * @property int $model_id
 */
class ModelHasPermission extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'permission_id';

    public function getTable(): string
    {
        return (string) config('permission.table_names.model_has_permissions', 'xlr8_iam_model_has_permissions');
    }
}
