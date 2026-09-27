<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval;

use App\Models\Admin\Designation;
use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalRuleLevel;
use App\Models\Approval\ApprovalTopic;
use App\Services\Platform\Approval\Entities\ApprovalRuleService;
use App\Support\Result;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Power-sheet import (FRS §8.4, TOP-04): one row per level. Rows with the same topic + scope +
 * validity form one rule. Dry-run reports without writing; apply purges and replaces the rules of
 * every topic in the sheet (Accessory-style), through ApprovalRuleService. Bad rows are returned
 * for the error sheet and never block the good topics — a topic with any bad row is skipped whole.
 */
final class PowerSheetImportService
{
    /** Canonical column => accepted header spellings (lower-case, spaces/underscores ignored). */
    private const HEADERS = [
        'topic' => ['topic', 'topiccode', 'topic_code', 'item', 'itemkey', 'item_key'],
        'level' => ['level', 'levelno', 'level_no', 'l'],
        'designation' => ['designation', 'designationcode', 'desig', 'role'],
        'value_type' => ['valuetype', 'type', 'value_type'],
        'std' => ['std', 'standard', 'stdvalue'],
        'min' => ['min', 'minimum', 'minvalue'],
        'max' => ['max', 'maximum', 'maxvalue', 'power', 'limit'],
        'valid_from' => ['validfrom', 'from', 'effectivefrom'],
        'valid_to' => ['validto', 'to', 'effectiveto'],
        'company' => ['company'], 'zone' => ['zone'], 'state' => ['state'], 'branch' => ['branch', 'branchcode'],
        'desk' => ['desk'], 'segment' => ['segment'], 'model' => ['model', 'modelcode'], 'variant' => ['variant', 'variantcode'],
        'permit' => ['permit'], 'channel' => ['channel'],
    ];

    public const TEMPLATE_HEADINGS = ['Topic', 'Level', 'Designation', 'Value Type', 'Std', 'Min', 'Max', 'Valid From', 'Valid To',
        'Company', 'Zone', 'State', 'Branch', 'Desk', 'Segment', 'Model', 'Variant', 'Permit', 'Channel'];

    public function __construct(private readonly ApprovalRuleService $rules) {}

    /**
     * @return Result data: {topics: list<string>, rules: int, levels: int, errors: list<array{row: int, topic: string, message: string}>, applied: bool}
     */
    public function import(string $path, bool $apply): Result
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        } catch (Throwable $e) {
            return Result::fail('UNREADABLE', 'The file could not be read: '.$e->getMessage());
        }
        if (count($sheet) < 2) {
            return Result::fail('EMPTY', 'The sheet has no data rows.');
        }
        $map = $this->headerMap(array_shift($sheet));
        foreach (['topic', 'level', 'max'] as $required) {
            if (! isset($map[$required])) {
                return Result::fail('MISSING_COLUMN', "The sheet needs a \"{$required}\" column.");
            }
        }

        $errors = [];
        $groups = [];
        $designations = Designation::query()->pluck('code')->map(fn ($c) => strtoupper((string) $c))->flip();
        foreach ($sheet as $i => $raw) {
            $rowNo = $i + 2;
            $cell = fn (string $key) => isset($map[$key]) ? trim((string) ($raw[$map[$key]] ?? '')) : '';
            if (implode('', array_map(fn ($v) => trim((string) $v), $raw)) === '') {
                continue;
            }
            $topicKey = $cell('topic');
            $topic = ApprovalTopic::query()->where('code', strtoupper($topicKey))->orWhere('item_key', strtolower($topicKey))->first();
            if (! $topic) {
                $errors[] = ['row' => $rowNo, 'topic' => $topicKey, 'message' => "Unknown topic or item key \"{$topicKey}\"."];

                continue;
            }
            $designation = strtoupper($cell('designation'));
            if ($designation === '' || ! isset($designations[$designation])) {
                $errors[] = ['row' => $rowNo, 'topic' => $topic->code, 'message' => "Unknown designation \"{$cell('designation')}\"."];

                continue;
            }
            $scope = [];
            foreach (ApprovalRule::SCOPES as $dim) {
                $value = $cell($dim);
                $scope[$dim.'_code'] = in_array(strtoupper($value), ['', 'ANY', 'ALL', '*'], true) ? '' : $value;
            }
            $validFrom = $this->date($cell('valid_from'));
            $validTo = $this->date($cell('valid_to'));
            $key = $topic->id.'|'.json_encode($scope).'|'.$validFrom.'|'.$validTo;
            $groups[$topic->id]['topic'] = $topic;
            $groups[$topic->id]['rules'][$key]['input'] = ['topic_id' => $topic->id, 'valid_from' => $validFrom, 'valid_to' => $validTo] + $scope;
            $groups[$topic->id]['rules'][$key]['rows'][] = $rowNo;
            $groups[$topic->id]['rules'][$key]['levels'][] = [
                'level_no' => $cell('level'), 'designation_code' => $designation,
                'value_type' => $cell('value_type') ?: ($topic->value_type ?? 'AMOUNT'),
                'std_value' => $cell('std'), 'min_value' => $cell('min'), 'max_value' => $cell('max'),
            ];
        }

        // level-set checks per rule; a topic with any error is skipped whole
        $badTopics = array_flip(array_column($errors, 'topic'));
        foreach ($groups as $topicId => $group) {
            foreach ($group['rules'] as $rule) {
                foreach ($this->rules->levelErrors($rule['levels']) as $message) {
                    $errors[] = ['row' => $rule['rows'][0], 'topic' => $group['topic']->code, 'message' => $message];
                    $badTopics[$group['topic']->code] = true;
                }
            }
        }
        $good = array_filter($groups, fn ($g) => ! isset($badTopics[$g['topic']->code]));
        $summary = [
            'topics' => array_values(array_map(fn ($g) => $g['topic']->code, $good)),
            'skipped_topics' => array_keys($badTopics),
            'rules' => array_sum(array_map(fn ($g) => count($g['rules']), $good)),
            'levels' => array_sum(array_map(fn ($g) => array_sum(array_map(fn ($r) => count($r['levels']), $g['rules'])), $good)),
            'errors' => $errors,
            'applied' => false,
        ];
        if (! $apply || $good === []) {
            return Result::ok($summary);
        }

        $batch = 'PS-'.now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        try {
            DB::transaction(function () use ($good, $batch) {
                foreach ($good as $topicId => $group) {
                    // purge-replace the topic's rules (FRS §8.4)
                    $old = ApprovalRule::query()->where('topic_id', $topicId)->pluck('id');
                    ApprovalRuleLevel::query()->whereIn('rule_id', $old)->delete();
                    ApprovalRule::query()->whereIn('id', $old)->get()->each->delete();
                    foreach ($group['rules'] as $rule) {
                        $this->rules->create($rule['input'] + ['import_batch' => $batch, 'is_active' => true, 'levels' => $rule['levels']]);
                    }
                }
            });
        } catch (ValidationException $e) {
            return Result::fail('INVALID', implode(' ', $e->validator->errors()->all()), $summary);
        }
        $summary['applied'] = true;
        $summary['batch'] = $batch;

        return Result::ok($summary);
    }

    /** @return array<string, int|string> canonical column => sheet column letter */
    private function headerMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $column => $label) {
            $norm = strtolower(preg_replace('/[\s_\-]+/', '', (string) $label));
            foreach (self::HEADERS as $canonical => $spellings) {
                if (! isset($map[$canonical]) && in_array($norm, array_map(fn ($s) => str_replace('_', '', $s), $spellings), true)) {
                    $map[$canonical] = $column;
                }
            }
        }

        return $map;
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return is_numeric($value)
                ? Date::excelToDateTimeObject((float) $value)->format('Y-m-d')
                : Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
