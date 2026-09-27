<?php

namespace App\Models\Approval;

use App\Models\BaseModel;
use App\Services\Platform\Approval\Entities\ApprovalRuleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Authority rule (FRS §8.3): topic + scope tuple (NULL = ANY) + ordered levels, effective-dated.
 * Written only through ApprovalRuleService (DEC-050).
 *
 * @property int $id
 * @property int $topic_id
 * @property ?string $company_code
 * @property ?string $zone_code
 * @property ?string $state_code
 * @property ?string $branch_code
 * @property ?string $desk_code
 * @property ?string $segment_code
 * @property ?string $model_code
 * @property ?string $variant_code
 * @property ?string $permit_code
 * @property ?string $channel_code
 * @property ?Carbon $valid_from
 * @property ?Carbon $valid_to
 * @property bool $is_active
 * @property ?string $note
 * @property ?string $import_batch
 * @property-read ApprovalTopic $topic
 * @property-read Collection<int, ApprovalRuleLevel> $levels
 */
class ApprovalRule extends BaseModel
{
    /** Scope dimensions in match specificity order, most specific first (FRS §8.3). */
    public const SCOPES = ['variant', 'model', 'segment', 'permit', 'desk', 'branch', 'zone', 'state', 'channel', 'company'];

    protected $table = 'xlr8_approval_rule';

    protected string $entityService = ApprovalRuleService::class;

    protected $fillable = [
        'topic_id', 'company_code', 'zone_code', 'state_code', 'branch_code', 'desk_code', 'segment_code',
        'model_code', 'variant_code', 'permit_code', 'channel_code', 'valid_from', 'valid_to', 'is_active', 'note', 'import_batch',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['valid_from' => 'date', 'valid_to' => 'date', 'is_active' => 'boolean']);
    }

    /** @return BelongsTo<ApprovalTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ApprovalTopic::class, 'topic_id');
    }

    /** @return HasMany<ApprovalRuleLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(ApprovalRuleLevel::class, 'rule_id')->orderBy('level_no');
    }

    /** @return array<string, ?string> scope dimension => value (null = ANY) */
    public function scopeTuple(): array
    {
        return collect(self::SCOPES)->mapWithKeys(fn (string $dim) => [$dim => $this->{$dim.'_code'}])->all();
    }
}
