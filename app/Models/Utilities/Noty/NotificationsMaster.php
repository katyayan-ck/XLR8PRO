<?php

namespace App\Models\Utilities\Noty;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user inbox counters (`xlr8_utils_noty_master`), read by the mobile v1 unread-count endpoint.
 * NotifyService recomputes them after every inbox write (BUG-181: the model and the User accessor
 * the v1 controller and NotificationService called did not exist).
 */
class NotificationsMaster extends Model
{
    protected $table = 'xlr8_utils_noty_master';

    protected $fillable = ['user_id', 'total_count', 'unread_count', 'created_by', 'updated_by'];

    protected $casts = ['total_count' => 'integer', 'unread_count' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Recount the user's inbox (notifications, alerts and messages that are not archived). */
    public function recalculate(): self
    {
        $total = Notification::query()->where('user_id', $this->user_id)->whereNull('archived_at')->count()
            + Alert::query()->where('user_id', $this->user_id)->whereNull('archived_at')->count();
        $unread = Notification::query()->where('user_id', $this->user_id)->whereNull('archived_at')->where('is_read', false)->count()
            + Alert::query()->where('user_id', $this->user_id)->whereNull('archived_at')->where('is_read', false)->count();

        $this->forceFill(['total_count' => $total, 'unread_count' => $unread])->save();

        return $this;
    }

    /** Legacy NotificationService API. */
    public function incrementUnreadCount(): self
    {
        return $this->recalculate();
    }

    /** Legacy NotificationService API. */
    public function markAllAsRead(): self
    {
        return $this->recalculate();
    }
}
