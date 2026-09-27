<?php

namespace App\Models\Approval;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only approval event (FRS §7.3). Reports read these plus the request projection.
 *
 * @property int $id
 * @property int $request_id
 * @property string $type
 * @property ?int $actor_id
 * @property ?int $ask_revision
 * @property ?int $level_no
 * @property ?string $value
 * @property ?array<string, mixed> $data
 * @property ?Carbon $created_at
 */
class ApprovalEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_approval_event';

    protected $fillable = ['request_id', 'type', 'actor_id', 'ask_revision', 'level_no', 'value', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'value' => 'decimal:2'];
    }
}
