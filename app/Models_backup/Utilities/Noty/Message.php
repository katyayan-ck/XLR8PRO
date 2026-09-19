<?php

namespace App\Models\Utilities\Noty;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends BaseModel
{
    protected $table = 'xlr8_utils_noty_message';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message_text',
        'message_type',
        'reply_to_id',
        'is_read',
        'read_at',
        'is_sent_via_fcm',
        'sent_at',
        'attachments',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_sent_via_fcm' => 'boolean',
        'attachments' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    // ========== SCOPES ==========

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeConversation($query, int $userId1, int $userId2)
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId1)
                ->where('receiver_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId2)
                ->where('receiver_id', $userId1);
        });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
        });
    }

    // ========== METHODS ==========

    public function markAsRead(): self
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function markAsSent(): self
    {
        $this->update([
            'is_sent_via_fcm' => true,
            'sent_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function getSummary(): string
    {
        return substr($this->message_text, 0, 100);
    }
}
