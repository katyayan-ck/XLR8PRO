<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval\Entities;

use App\Models\Approval\ApprovalTopic;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Approval topic — the only write path (DEC-050, DEC-063). Codes are stable dotted strings
 * (DISCOUNT.EXTRA); snapshots store code + title, so a rename never breaks old requests (TOP-01).
 *
 * @extends EntityService<ApprovalTopic>
 */
class ApprovalTopicService extends EntityService
{
    public const MODES = ['STATIC', 'LINEAR', 'OPEN_TO_ALL', 'VARIABLE'];

    public const VALUE_TYPES = ['AMOUNT', 'PERCENTAGE', 'FLAG'];

    protected function model(): string
    {
        return ApprovalTopic::class;
    }

    public function fields(): array
    {
        return [
            Field::make('code')->label('Topic code')->format('Dotted code, e.g. DISCOUNT.EXTRA; A-Z 0-9 . _ -')
                ->transform('trim', 'uppercase')->rules('string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9_.\-]*$/')->required()->unique()->immutable(),
            Field::integer('parent_id', 1)->label('Parent topic')->rules('exists:xlr8_approval_topic,id'),
            Field::text('title', 150)->label('Title')->required(),
            Field::make('item_key')->label('Item key')->format('lower_snake, e.g. extra_disc')
                ->transform('trim', 'lowercase')->rules('string', 'max:60', 'regex:/^[a-z0-9][a-z0-9_]*$/')->unique(),
            Field::choice('mode', self::MODES)->label('Mode (blank = inherit)'),
            Field::choice('value_type', self::VALUE_TYPES)->label('Value type'),
            Field::flag('is_mandatory', false)->label('Mandatory for the source document'),
            Field::flag('is_active')->label('Active'),
            Field::text('description', 5000)->label('Description'),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $this->guardParent(null, $data);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->guardParent($model, $data);
    }

    /** A topic may not be its own ancestor. */
    private function guardParent(?Model $model, array $data): void
    {
        $parentId = $data['parent_id'] ?? null;
        if ($model === null || ! $parentId) {
            return;
        }
        for ($node = ApprovalTopic::query()->find($parentId); $node !== null; $node = $node->parent) {
            if ($node->id === $model->getKey()) {
                $this->fail('parent_id', 'A topic cannot sit under itself or one of its own children.');
            }
        }
    }
}
