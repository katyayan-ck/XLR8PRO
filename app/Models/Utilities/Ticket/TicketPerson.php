<?php

namespace App\Models\Utilities\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's role on a ticket: REQUESTER, OWNER, ASSIGNEE, FOLLOWER or SNOOPER.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $role
 * @property-read ?User $user
 */
class TicketPerson extends Model
{
    protected $table = 'xlr8_utils_ticket_person';

    protected $fillable = ['ticket_id', 'user_id', 'role'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
