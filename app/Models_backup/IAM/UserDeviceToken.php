<?php

namespace App\Models\IAM;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceToken extends BaseModel
{
    protected $table = 'xlr8_iam_user_device_token';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'platform_version',
        'fcm_token',
        'is_active',
        'token_expires_at',
        'last_used_at',
        'last_notification_sent_at',
        'notification_count',
        'metadata',
        'ip_address',
        'user_agent',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notification_count' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'last_notification_sent_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('deleted_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeRecentlyUsed($query, int $days = 7)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    // ========== METHODS ==========

    public function isValid(): bool
    {
        if ($this->token_expires_at && $this->token_expires_at < now()) {
            return false;
        }

        return $this->is_active && !$this->deleted_at;
    }

    public function markAsUsed(): self
    {
        $this->update([
            'last_used_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function recordNotificationSent(): self
    {
        $this->update([
            'notification_count' => $this->notification_count + 1,
            'last_notification_sent_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function deactivate(): self
    {
        $this->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function activate(): self
    {
        $this->update([
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->device_name} ({$this->platform})";
    }
}
