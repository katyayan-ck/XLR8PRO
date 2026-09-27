<?php

namespace App\Models\Comms;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A phone call (FRS §16.2). The row exists before the vendor answers (DIALING); webhooks move it on.
 * The recording is a Docs row (collection call-recordings) attached to the call.
 *
 * @property int $id
 * @property string $direction
 * @property ?string $from_number
 * @property ?string $to_number
 * @property ?int $agent_user_id
 * @property ?string $person_code
 * @property string $status
 * @property ?Carbon $started_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $ended_at
 * @property ?int $duration_seconds
 * @property ?string $disposition
 * @property ?string $disposition_remark
 * @property ?int $recording_doc_id
 * @property bool $recording_missing
 * @property ?string $vendor_call_id
 * @property ?string $driver
 * @property bool $is_campaign
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property-read ?User $agent
 */
class CommCall extends BaseModel
{
    use HasCommunications, HasDocuments;

    protected $table = 'xlr8_comm_call';

    protected $fillable = [
        'direction', 'from_number', 'to_number', 'agent_user_id', 'person_code', 'status', 'started_at', 'answered_at', 'ended_at',
        'duration_seconds', 'disposition', 'disposition_remark', 'recording_doc_id', 'recording_missing', 'vendor_call_id',
        'driver', 'caller_id', 'is_campaign', 'ref_type', 'ref_id',
    ];

    protected $hidden = ['from_number', 'to_number'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'started_at' => 'datetime', 'answered_at' => 'datetime', 'ended_at' => 'datetime',
            'recording_missing' => 'boolean', 'is_campaign' => 'boolean',
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    /** The agent and call-log viewers see the call (and so its recording, through Docs). */
    public function chatCanView(int $userId): bool
    {
        return (int) $this->agent_user_id === $userId || (bool) User::query()->find($userId)?->can('UTL_COMM_VIEW');
    }
}
