<?php

namespace App\Models\IAM;

use App\Models\BaseModel;

class DeviceSession extends BaseModel
{
    protected $table = 'xlr8_iam_device_session';
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'last_active_at',
    ];
}
