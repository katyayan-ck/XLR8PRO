<?php

namespace App\Models\IAM;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy user → branch id pairs (`xlr8_user_branches`, from the booking team's schema; no code reads it — scopes live in
 * `xlr8_admin_user_scopes`). Modelled only so `UserResetService` can remove a removed user's rows (DEC-093).
 *
 * @property int $user_id
 * @property int $branch_id
 */
class LegacyUserBranch extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $table = 'xlr8_user_branches';

    protected $fillable = ['user_id', 'branch_id'];
}
