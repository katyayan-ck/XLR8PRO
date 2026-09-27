<?php

namespace App\Models\Approval;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An approver's counter on one ask revision (FRS APR-02). Never updated: a newer counter at the
 * same level supersedes it.
 *
 * @property int $id
 * @property int $request_id
 * @property int $ask_revision
 * @property int $level_no
 * @property int $actor_id
 * @property string $value
 * @property ?string $remark
 * @property bool $is_system
 * @property ?Carbon $created_at
 * @property-read ?User $actor
 */
class ApprovalCounter extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_approval_counter';

    protected $fillable = ['request_id', 'ask_revision', 'level_no', 'actor_id', 'value', 'remark', 'is_system'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'is_system' => 'boolean', 'level_no' => 'integer', 'ask_revision' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
