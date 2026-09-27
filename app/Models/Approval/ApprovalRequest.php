<?php

namespace App\Models\Approval;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use App\Services\Platform\Approval\ApprovalService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An approval request (FRS §7): a projection over its append-only events, carrying the frozen
 * authority snapshot. Written only by ApprovalService.
 *
 * @property int $id
 * @property int $topic_id
 * @property string $topic_code
 * @property string $topic_title
 * @property ?string $item_key
 * @property string $mode
 * @property ?string $source_type
 * @property ?int $source_id
 * @property int $requester_id
 * @property string $value_type
 * @property string $asked
 * @property int $ask_revision
 * @property ?array<string, mixed> $scope
 * @property array<string, mixed> $snapshot
 * @property string $status
 * @property ?int $effective_level
 * @property ?string $effective_value
 * @property ?int $effective_actor_id
 * @property ?int $current_level
 * @property bool $auto_accepted
 * @property ?string $branch_code
 * @property ?string $fy
 * @property ?Carbon $closed_at
 * @property ?int $closed_by
 * @property ?Carbon $created_at
 * @property-read ?User $requester
 * @property-read Collection<int, ApprovalCounter> $counters
 */
class ApprovalRequest extends BaseModel
{
    use HasCommunications, HasDocuments;

    protected $table = 'xlr8_approval_request';

    protected $fillable = [
        'topic_id', 'topic_code', 'topic_title', 'item_key', 'mode', 'source_type', 'source_id', 'requester_id',
        'value_type', 'asked', 'ask_revision', 'scope', 'snapshot', 'status', 'effective_level', 'effective_value',
        'effective_actor_id', 'current_level', 'auto_accepted', 'branch_code', 'fy', 'closed_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'scope' => 'array', 'snapshot' => 'array', 'asked' => 'decimal:2', 'effective_value' => 'decimal:2',
            'auto_accepted' => 'boolean', 'closed_at' => 'datetime', 'ask_revision' => 'integer',
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return HasMany<ApprovalCounter, $this> */
    public function counters(): HasMany
    {
        return $this->hasMany(ApprovalCounter::class, 'request_id');
    }

    /** @return HasMany<ApprovalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ApprovalEvent::class, 'request_id');
    }

    /** Requester, anyone the snapshot makes visible and approval admins see the conversation. */
    public function chatCanView(int $userId): bool
    {
        return app(ApprovalService::class)->canSee($this, $userId);
    }

    protected function getCommMasterTitle(): string
    {
        return "Approval #{$this->id}: {$this->topic_title}";
    }
}
