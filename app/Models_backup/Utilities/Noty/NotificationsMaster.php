<?php

namespace App\Models\Utilities\Noty;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Core\Notification;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationsMaster extends BaseModel
{
    protected $table = 'xlr8_utils_noty_master';

    protected $fillable = [
        'user_id',
        'total_count',
        'unread_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'unread_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'user_id', 'user_id');
    }

    // ========== SCOPES ==========

    public function scopeUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ========== METHODS ==========

    public function incrementUnreadCount(int $count = 1): self
    {
        $this->update([
            'unread_count' => $this->unread_count + $count,
            'total_count' => $this->total_count + $count,
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function decrementUnreadCount(int $count = 1): self
    {
        $this->update([
            'unread_count' => max(0, $this->unread_count - $count),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function markAllAsRead(): self
    {
        $this->update([
            'unread_count' => 0,
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }

    public function refresh(): self
    {
        $this->update([
            'unread_count' => $this->notifications()
                ->where('is_read', false)
                ->count(),
            'total_count' => $this->notifications()->count(),
            'updated_by' => auth()->id(),
        ]);

        return $this;
    }
}
