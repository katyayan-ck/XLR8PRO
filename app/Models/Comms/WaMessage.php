<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One WhatsApp message, IN or OUT. Media is a Docs id, never a vendor URL (FRS §15 law 6).
 *
 * @property int $id
 * @property int $thread_id
 * @property string $direction
 * @property string $type
 * @property ?string $text
 * @property ?int $doc_id
 * @property ?array<string, mixed> $payload
 * @property ?string $sender_wa_id
 * @property ?string $provider_message_id
 * @property string $status
 * @property ?int $outbox_id
 * @property ?int $actor_id
 * @property ?Carbon $read_at
 * @property ?Carbon $created_at
 */
class WaMessage extends Model
{
    protected $table = 'xlr8_comm_wa_message';

    protected $fillable = ['thread_id', 'direction', 'type', 'text', 'doc_id', 'payload', 'sender_wa_id', 'provider_message_id', 'status', 'outbox_id', 'actor_id', 'read_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'read_at' => 'datetime'];
    }

    /** @return BelongsTo<WaThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(WaThread::class, 'thread_id');
    }
}
