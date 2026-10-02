<?php

namespace App\Models\IAM;

use Illuminate\Database\Eloquent\Model;

/**
 * Hash of a password a user has set (N4, DEC-095 #28), kept so `MyAccountService::changePassword()` can refuse a reuse
 * of the last `account.password_history_count` passwords. Append-only; written only by that service.
 *
 * @property int $id
 * @property int $user_id
 * @property string $password
 */
class PasswordHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_iam_password_history';

    protected $fillable = ['user_id', 'password'];

    protected $hidden = ['password'];
}
