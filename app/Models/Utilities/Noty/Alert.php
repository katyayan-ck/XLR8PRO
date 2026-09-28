<?php

namespace App\Models\Utilities\Noty;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends BaseModel
{
    protected $table = 'xlr8_utils_noty_alert';

    protected $fillable = [
        'user_id',
        'sender_id',
        'severity',
        'title',
        'description',
        'reference_type',
        'reference_id',
        'is_read',
        'read_at',
        'is_sent_via_fcm',
        'sent_at',
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

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeWarning($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeInfo($query)
    {
        return $query->where('severity', 'info');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
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

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function getDeepLink(): ?string
    {
        return $this->payload['deep_link'] ?? null;
    }
}
