<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval;

use App\Models\Approval\ApprovalTopic;
use Illuminate\Support\Facades\Log;

/**
 * Topic tree reads (FRS §8.2, TOP-02): resolve an item key or code to its chain and effective mode.
 * Writes go through Entities\ApprovalTopicService.
 */
final class TopicService
{
    public const DEFAULT_MODE = 'OPEN_TO_ALL';

    /**
     * The node for an item key (extra_disc) or a topic code (DISCOUNT.EXTRA), its chain from the
     * root and its effective mode (inherited downward; VARIABLE without a resolver → OPEN_TO_ALL).
     *
     * @return array{node: ApprovalTopic, chain: list<ApprovalTopic>, mode: string, value_type: string}|null
     */
    public function resolve(string $itemKeyOrCode): ?array
    {
        $node = ApprovalTopic::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('item_key', strtolower(trim($itemKeyOrCode)))->orWhere('code', strtoupper(trim($itemKeyOrCode))))
            ->first();
        if (! $node) {
            return null;
        }

        $chain = [];
        for ($n = $node; $n !== null; $n = $n->parent) {
            array_unshift($chain, $n);
        }

        $mode = null;
        $valueType = null;
        foreach (array_reverse($chain) as $n) {
            $mode ??= $n->mode;
            $valueType ??= $n->value_type;
        }
        $mode ??= self::DEFAULT_MODE;
        if ($mode === 'VARIABLE') {
            // no runtime resolver is configured yet (UC-TOP-5)
            Log::warning('[Approval] VARIABLE topic has no resolver; treated as OPEN_TO_ALL', ['topic' => $node->code]);
            $mode = self::DEFAULT_MODE;
        }

        return ['node' => $node, 'chain' => $chain, 'mode' => $mode, 'value_type' => $valueType ?? 'AMOUNT'];
    }

    /**
     * The whole tree for the admin editor, depth-first.
     *
     * @return list<array{topic: ApprovalTopic, depth: int, rules: int}>
     */
    public function tree(): array
    {
        $all = ApprovalTopic::query()->withCount('rules')->orderBy('code')->get();
        $byParent = $all->groupBy(fn ($t) => (int) $t->parent_id);
        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $topic) {
                $out[] = ['topic' => $topic, 'depth' => $depth, 'rules' => (int) $topic->rules_count];
                $walk($topic->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    /** @return array<int, string> id => "CODE — Title" for pickers */
    public function options(): array
    {
        return ApprovalTopic::query()->orderBy('code')->get(['id', 'code', 'title'])
            ->mapWithKeys(fn ($t) => [$t->id => "{$t->code} — {$t->title}"])->all();
    }
}
