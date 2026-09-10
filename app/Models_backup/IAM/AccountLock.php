<?php

namespace App\Models\IAM;

use App\Models\BaseModel;

class AccountLock extends BaseModel
{

protected $table = 'xlr8_iam_account_lock';
protected $fillable = [
        'user_id',
        'locked_until',
        'reason',
    ];
}
