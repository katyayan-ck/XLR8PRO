<?php

namespace App\Models\Approval;

use App\Models\BaseModel;
use App\Services\Platform\Approval\Entities\ApprovalTopicService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approval topic tree node (FRS §8.2): Main → Sub → Item. `mode` null = inherit from the parent.
 * Written only through ApprovalTopicService (DEC-050).
 *
 * @property int $id
 * @property string $code
 * @property ?int $parent_id
 * @property string $title
 * @property ?string $item_key
 * @property ?string $mode
 * @property ?string $value_type
 * @property bool $is_mandatory
 * @property bool $is_active
 * @property ?string $description
 * @property-read ?ApprovalTopic $parent
 */
class ApprovalTopic extends BaseModel
{
    protected $table = 'xlr8_approval_topic';

    protected string $entityService = ApprovalTopicService::class;

    protected $fillable = ['code', 'parent_id', 'title', 'item_key', 'mode', 'value_type', 'is_mandatory', 'is_active', 'description'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_mandatory' => 'boolean', 'is_active' => 'boolean']);
    }

    /** @return BelongsTo<ApprovalTopic, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ApprovalTopic, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<ApprovalRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class, 'topic_id');
    }
}
