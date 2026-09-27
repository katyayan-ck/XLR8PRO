<?php

namespace App\Models\Utilities\Ticket;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use App\Services\Platform\Ticket\TicketService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operational / IT incident (FRS §6). Written only by TicketService.
 *
 * @property int $id
 * @property string $number
 * @property string $branch_code
 * @property string $fy
 * @property int $seq
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property string $title
 * @property ?string $details
 * @property int $requester_id
 * @property ?int $owner_id
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property ?\Illuminate\Support\Carbon $due_at
 * @property ?\Illuminate\Support\Carbon $sla_paused_at
 * @property int $sla_paused_minutes
 * @property ?\Illuminate\Support\Carbon $breached_at
 * @property ?\Illuminate\Support\Carbon $acknowledged_at
 * @property ?\Illuminate\Support\Carbon $resolved_at
 * @property ?\Illuminate\Support\Carbon $closed_at
 * @property ?string $close_reason
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketPerson> $people
 */
class Ticket extends BaseModel
{
    use HasCommunications, HasDocuments;

    protected $table = 'xlr8_utils_ticket';

    protected $fillable = [
        'number', 'branch_code', 'fy', 'seq', 'category', 'priority', 'status', 'title', 'details',
        'requester_id', 'owner_id', 'ref_type', 'ref_id', 'due_at', 'sla_paused_at', 'sla_paused_minutes',
        'breached_at', 'acknowledged_at', 'resolved_at', 'closed_at', 'close_reason',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'due_at' => 'datetime',
            'sla_paused_at' => 'datetime',
            'breached_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'sla_paused_minutes' => 'integer',
        ]);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<TicketPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(TicketPerson::class, 'ticket_id');
    }

    public function chatCanView(int $userId): bool
    {
        return app(TicketService::class)->canView($this, $userId);
    }

    /** Snoopers read silently. */
    public function chatCanRemark(int $userId): bool
    {
        return in_array('remark', app(TicketService::class)->rights($this, $userId), true);
    }

    protected function getCommMasterTitle(): string
    {
        return "Ticket {$this->number}: {$this->title}";
    }
}
