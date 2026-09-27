<?php

namespace App\Models\Approval;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authority level of a rule: who (designation, or users for STATIC) may grant what.
 * Written together with its rule by ApprovalRuleService.
 *
 * @property int $id
 * @property int $rule_id
 * @property int $level_no
 * @property ?string $designation_code
 * @property ?array<int, int> $user_ids
 * @property string $value_type
 * @property ?string $std_value
 * @property ?string $min_value
 * @property ?string $max_value
 */
class ApprovalRuleLevel extends Model
{
    protected $table = 'xlr8_approval_rule_level';

    protected $fillable = ['rule_id', 'level_no', 'designation_code', 'user_ids', 'value_type', 'std_value', 'min_value', 'max_value'];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'level_no' => 'integer', 'std_value' => 'decimal:2', 'min_value' => 'decimal:2', 'max_value' => 'decimal:2'];
    }

    /** @return BelongsTo<ApprovalRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }
}
