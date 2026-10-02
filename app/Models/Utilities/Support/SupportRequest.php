<?php

namespace App\Models\Utilities\Support;

use App\Models\User;
use App\Models\Utilities\Ticket\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One "Still need help?" request (DEC-094, W16e): the ticket it opened and the diagnostic zip kept in private storage
 * (`bundle_path` on the `local` disk) until `support.bundle_retention_days`. Written only by `SupportRequestService`.
 *
 * @property int $id
 * @property ?int $ticket_id
 * @property int $requester_id
 * @property string $category
 * @property ?string $route
 * @property ?string $bundle_path
 * @property ?int $bundle_bytes
 * @property ?Carbon $purged_at
 * @property Carbon $created_at
 */
class SupportRequest extends Model
{
    protected $table = 'xlr8_utils_support_request';

    protected $fillable = ['ticket_id', 'requester_id', 'category', 'route', 'bundle_path', 'bundle_bytes', 'purged_at'];

    protected function casts(): array
    {
        return ['purged_at' => 'datetime', 'bundle_bytes' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
