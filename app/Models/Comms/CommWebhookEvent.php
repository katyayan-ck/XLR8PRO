<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 * One delivery-status webhook received from a comms vendor (`xlr8_comm_webhook_event`, append-only; unique per channel
 * + `event_id`, so a repeated delivery is ignored). The payload is stored redacted. Written only by
 * `Api\CommsWebhookController` (DEC-093).
 *
 * @property int $id
 * @property string $channel
 * @property string $event_id
 * @property ?string $payload
 * @property ?string $result
 */
class CommWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_comm_webhook_event';

    protected $fillable = ['channel', 'event_id', 'payload', 'result'];
}
