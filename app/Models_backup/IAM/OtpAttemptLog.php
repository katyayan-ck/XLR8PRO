<?php

namespace App\Models\IAM;

use App\Models\BaseModel;

class OtpAttemptLog extends BaseModel
{
protected $table = 'xlr8_iam_otp_attempt_log';    
protected $fillable = [
        'user_id',
        'mobile',
        'action',
        'ip_address',
        'user_agent',
        'reason',
    ];
}
