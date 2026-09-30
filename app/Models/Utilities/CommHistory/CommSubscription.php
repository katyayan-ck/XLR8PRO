<?php

namespace App\Models\Utilities\CommHistory;

use Illuminate\Database\Eloquent\Model;

/**
 * A user following an entity's timeline (`xlr8_utils_comm_subscription`: comm master × user). Written only by
 * `ChatService::subscribe()` / `unsubscribe()`; read by `Notify\Audience` (DEC-093).
 *
 * @property int $id
 * @property int $comm_master_id
 * @property int $user_id
 */
class CommSubscription extends Model
{
    protected $table = 'xlr8_utils_comm_subscription';

    protected $fillable = ['comm_master_id', 'user_id'];
}
