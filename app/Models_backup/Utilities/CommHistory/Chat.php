<?php

namespace App\Models\Utilities\CommHistory;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chat extends BaseModel
{
    use SoftDeletes;

protected $table = 'xlr8_chat';
    protected $fillable = [
        'type',  // 'group' or 'one_to_one'
        'participants',  // JSON array of user IDs
    ];

    protected $casts = [
        'participants' => 'array',
    ];

    public function commMaster(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(CommMaster::class, 'entityable');
    }
}
