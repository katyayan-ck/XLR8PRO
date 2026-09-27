<?php

namespace App\Models\Comms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Outbox row (FRS Part B law 2): written before any vendor call. `payload` (rendered copy + vars +
 * attachments) is encrypted and replayed exactly by resend; `body_preview` is PII-masked.
 * Written only by the comms services.
 *
 * @property int $id
 * @property string $channel
 * @property string $status
 * @property ?string $driver
 * @property ?string $to_address
 * @property ?string $to_person_code
 * @property ?array<string, mixed> $envelope
 * @property ?string $subject
 * @property ?string $body_preview
 * @property ?array<string, mixed> $payload
 * @property ?string $template_code
 * @property ?int $template_version
 * @property ?string $category
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property string $idempotency_key
 * @property ?string $provider_message_id
 * @property int $attempts
 * @property ?string $error
 * @property ?int $parent_outbox_id
 * @property ?int $actor_id
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $created_at
 */
class CommOutbox extends Model
{
    public const FINAL = ['DELIVERED', 'FAILED', 'BOUNCED', 'SUPPRESSED', 'READ'];

    protected $table = 'xlr8_comm_outbox';

    protected $fillable = [
        'channel', 'status', 'driver', 'to_address', 'to_person_code', 'envelope', 'subject', 'body_preview', 'payload',
        'template_code', 'template_version', 'category', 'ref_type', 'ref_id', 'idempotency_key', 'provider_message_id',
        'attempts', 'error', 'units', 'parent_outbox_id', 'actor_id', 'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['envelope' => 'array', 'payload' => 'encrypted:array', 'sent_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Envelope without BCC — for Chat excerpts and any customer-visible log (EML-05). */
    public function publicEnvelope(): array
    {
        $envelope = (array) $this->envelope;
        unset($envelope['bcc']);

        return $envelope;
    }
}
