<?php

namespace App\Models\IAM;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single explicitly-revoked permission for one user, overriding whatever
 * their role would otherwise grant. See the migration's docblock — this is
 * the "removed" half of user-level permission overrides; the "added" half is
 * Spatie's own native model_has_permissions (direct permission grants).
 */
class UserPermissionDenial extends Model
{
    protected $table = 'xlr8_iam_user_permission_denials';

    protected $fillable = [
        'user_id',
        'permission_id',
        'created_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
