<?php

namespace App\Models\Comms;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp conversation with one wa_id (person or group), optionally linked to a Person and a
 * record, assigned to an agent and labelled OPEN / PENDING / DONE (FRS §15.3). Inbound media is
 * stored on it through Docs (collection wa-inbound).
 *
 * @property int $id
 * @property string $wa_id
 * @property bool $is_group
 * @property ?string $title
 * @property ?string $person_code
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property ?int $assigned_to
 * @property string $label
 * @property ?Carbon $session_expires_at
 * @property ?Carbon $last_message_at
 * @property int $unread
 */
class WaThread extends BaseModel
{
    use HasCommunications, HasDocuments;

    protected $table = 'xlr8_comm_wa_thread';

    protected $fillable = ['wa_id', 'is_group', 'title', 'person_code', 'ref_type', 'ref_id', 'assigned_to', 'label', 'session_expires_at', 'last_message_at', 'unread'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_group' => 'boolean', 'session_expires_at' => 'datetime', 'last_message_at' => 'datetime']);
    }

    /** @return HasMany<WaMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sessionOpen(): bool
    {
        return $this->session_expires_at !== null && $this->session_expires_at->isFuture();
    }

    /** Inbox agents (UTL_COMM_WA_INBOX) see threads; others only their assigned ones. */
    public function chatCanView(int $userId): bool
    {
        return (int) $this->assigned_to === $userId || (bool) User::query()->find($userId)?->can('UTL_COMM_WA_INBOX');
    }
}
