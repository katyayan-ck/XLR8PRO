<?php

namespace App\Models\Utilities\Noty;

use Illuminate\Database\Eloquent\Model;

/**
 * The master message of one Notify::send() (FRS NOT-01): one row per send, one inbox row per
 * recipient in noty_notification / noty_alert. Written only by NotifyService.
 */
class NotificationDispatch extends Model
{
    protected $table = 'xlr8_utils_noty_dispatch';

    protected $fillable = ['kind', 'title', 'body', 'ref_type', 'ref_id', 'channels', 'data', 'template', 'idempotency_key', 'sender_id', 'recipient_count', 'created_by', 'updated_by'];

    protected $casts = ['channels' => 'array', 'data' => 'array', 'recipient_count' => 'integer'];
}
