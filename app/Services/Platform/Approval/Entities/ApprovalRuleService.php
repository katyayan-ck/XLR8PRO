<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval\Entities;

use App\Models\Admin\Designation;
use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalRuleLevel;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Approval rule + its levels — the only write path (DEC-050, DEC-063). Scope columns store NULL
 * for ANY. Levels arrive as `levels` => list of {level_no, designation_code|user_ids, value_type,
 * std_value, min_value, max_value} and replace the rule's levels on every save.
 *
 * @extends EntityService<ApprovalRule>
 */
class ApprovalRuleService extends EntityService
{
    protected function model(): string
    {
        return ApprovalRule::class;
    }

    protected function naturalKey(): array
    {
        return ['id'];
    }

    public function fields(): array
    {
        $scopes = [];
        foreach (ApprovalRule::SCOPES as $dim) {
            // same synonym types as the pricing scope fields
            $synonym = ['segment' => 'Segment', 'permit' => 'Permit'][$dim] ?? null;
            $scopes[] = Field::scope($dim.'_code', 50, $synonym, true)->label(ucfirst($dim).' (blank = any)')->transform('uppercase');
        }

        return [
            Field::integer('topic_id', 1)->label('Topic')->rules('exists:xlr8_approval_topic,id')->required(),
            ...$scopes,
            Field::date('valid_from')->label('Valid from'),
            Field::date('valid_to')->label('Valid to')->rules('nullable', 'after_or_equal:valid_from'),
            Field::flag('is_active')->label('Active'),
            Field::text('note', 250)->label('Note'),
            Field::text('import_batch', 40),
        ];
    }

    /** Blank scope = ANY, stored as NULL. */
    protected function derive(array $data, array $input): array
    {
        foreach (ApprovalRule::SCOPES as $dim) {
            if (array_key_exists($dim.'_code', $data) && ($data[$dim.'_code'] === '' || $data[$dim.'_code'] === null)) {
                $data[$dim.'_code'] = null;
            }
        }

        return $data;
    }

    /** Levels are validated here; a failure throws inside the save transaction, so the rule is rolled back too. */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        if (! array_key_exists('levels', $input)) {
            return;
        }
        $errors = $this->levelErrors((array) $input['levels']);
        if ($errors !== []) {
            $this->fail('levels', implode(' ', $errors));
        }
        ApprovalRuleLevel::query()->where('rule_id', $model->getKey())->delete();
        foreach ($this->normaliseLevels((array) $input['levels']) as $level) {
            ApprovalRuleLevel::create($level + ['rule_id' => $model->getKey()]);
        }
    }

    /**
     * Problems with a set of level rows (empty = valid).
     *
     * @param  list<array<string, mixed>>  $levels
     * @return list<string>
     */
    public function levelErrors(array $levels): array
    {
        $errors = [];
        $seen = [];
        if ($levels === []) {
            return ['A rule needs at least one level.'];
        }
        foreach ($this->normaliseLevels($levels) as $i => $level) {
            $row = 'Level row '.($i + 1).': ';
            if ($level['level_no'] < 1) {
                $errors[] = $row.'level number must be 1 or more.';
            }
            if (isset($seen[$level['level_no']])) {
                $errors[] = $row."level {$level['level_no']} appears twice.";
            }
            $seen[$level['level_no']] = true;
            if ($level['designation_code'] === null && $level['user_ids'] === null) {
                $errors[] = $row.'give a designation (or users for a STATIC topic).';
            }
            if ($level['designation_code'] !== null && ! Designation::query()->where('code', $level['designation_code'])->exists()) {
                $errors[] = $row."unknown designation {$level['designation_code']}.";
            }
            if (! in_array($level['value_type'], ApprovalTopicService::VALUE_TYPES, true)) {
                $errors[] = $row.'value type must be AMOUNT, PERCENTAGE or FLAG.';
            }
            if ($level['min_value'] !== null && $level['max_value'] !== null && (float) $level['min_value'] > (float) $level['max_value']) {
                $errors[] = $row.'min is above max.';
            }
            if ($level['value_type'] === 'PERCENTAGE' && (float) $level['max_value'] > 100) {
                $errors[] = $row.'a percentage cannot exceed 100.';
            }
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     * @return list<array{level_no: int, designation_code: ?string, user_ids: ?list<int>, value_type: string, std_value: ?string, min_value: ?string, max_value: ?string}>
     */
    public function normaliseLevels(array $levels): array
    {
        $number = fn ($v) => $v === null || trim((string) $v) === '' ? null : (Field::parseNumber((string) $v) ?? null);

        return array_values(array_map(fn (array $l) => [
            'level_no' => (int) ($l['level_no'] ?? 0),
            'designation_code' => ($d = strtoupper(trim((string) ($l['designation_code'] ?? '')))) === '' ? null : $d,
            'user_ids' => empty($l['user_ids']) ? null : array_values(array_map('intval', (array) $l['user_ids'])),
            'value_type' => strtoupper(trim((string) ($l['value_type'] ?? 'AMOUNT'))) ?: 'AMOUNT',
            'std_value' => $number($l['std_value'] ?? null),
            'min_value' => $number($l['min_value'] ?? null),
            'max_value' => $number($l['max_value'] ?? null),
        ], array_filter($levels, 'is_array')));
    }
}
