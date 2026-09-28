<?php

namespace App\Models\Utilities\Noty;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends BaseModel
{
    protected $table = 'xlr8_utils_noty_notification';

    protected $fillable = [
        'user_id',
        'sender_id',
        'type',
        'title',
        'description',
        'reference_type',
        'reference_id',
        'is_read',
        'read_at',
        'is_sent_via_fcm',
        'sent_at',
        'priority',
        'category',
        'payload',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_sent_via_fcm' => 'boolean',
        'payload' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ========== SCOPES ==========

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSentViaFCM($query)
    {
        return $query->where('is_sent_via_fcm', true);
    }

    public function scopeNotSentViaFCM($query)
    {
        return $query->where('is_sent_via_fcm', false);
    }

    // ========== METHODS ==========

    public function markAsRead(): self
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        // Update master count
        $this->user->notificationsMaster?->decrementUnreadCount();

        return $this;
    }

    public function markAsUnread(): self
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
            'updated_by' => auth()->id(),
        ]);

        // Update master count
        $this->user->notificationsMaster?->incrementUnreadCount();

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

    public function getDeepLink(): ?string
    {
        if (!$this->payload || !isset($this->payload['deep_link'])) {
            return null;
        }

        return $this->payload['deep_link'];
    }

    public function getAction(): ?string
    {
        if (!$this->payload || !isset($this->payload['action'])) {
            return null;
        }

        return $this->payload['action'];
    }
}
