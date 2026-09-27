<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval;

use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalTopic;
use Illuminate\Support\Carbon;

/**
 * Rule matching (FRS §8.3, TOP-03), locked precedence:
 *  1. deepest topic node (Item > Sub > Main) that has a valid rule matching the scope;
 *  2. then scope specificity variant > model > segment > permit > desk > branch > zone > company;
 *  3. then latest rule id.
 * A rule matches when every non-ANY (non-null) dimension equals the request scope's value.
 */
final class RuleService
{
    /** Weight per dimension: a more specific dimension outweighs all less specific ones together. */
    private const WEIGHTS = [
        'variant' => 512, 'model' => 256, 'segment' => 128, 'permit' => 64, 'desk' => 32,
        'branch' => 16, 'zone' => 8, 'state' => 4, 'channel' => 2, 'company' => 1,
    ];

    /**
     * @param  ApprovalTopic|array{chain: list<ApprovalTopic>}  $topic  a node or a TopicService::resolve() result
     * @param  array<string, mixed>  $scope  e.g. ['model' => 'NEXON', 'branch' => 'JAIPUR']
     */
    public function match(ApprovalTopic|array $topic, array $scope, ?Carbon $on = null): ?ApprovalRule
    {
        $chain = is_array($topic) ? $topic['chain'] : $this->chainOf($topic);
        $scope = $this->normaliseScope($scope);
        $on ??= now();

        foreach (array_reverse($chain) as $node) {
            $best = null;
            $bestScore = -1;
            $rules = ApprovalRule::query()->with('levels')->where('topic_id', $node->id)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $on->toDateString()))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $on->toDateString()))
                ->orderByDesc('id')->get();
            foreach ($rules as $rule) {
                $score = $this->score($rule, $scope);
                if ($score > $bestScore) {
                    [$best, $bestScore] = [$rule, $score];
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /** Specificity score, or -1 when a set dimension does not match. Equal scores keep the latest id (query order). */
    public function score(ApprovalRule $rule, array $scope): int
    {
        $score = 0;
        foreach (self::WEIGHTS as $dim => $weight) {
            $value = $rule->{$dim.'_code'};
            if ($value === null || $value === '') {
                continue;
            }
            if (strtoupper((string) ($scope[$dim] ?? '')) !== strtoupper($value)) {
                return -1;
            }
            $score += $weight;
        }

        return $score;
    }

    /** @return array<string, string> dimension => upper-cased value, known dimensions only */
    public function normaliseScope(array $scope): array
    {
        $out = [];
        foreach ($scope as $key => $value) {
            $dim = strtolower(preg_replace('/_code$/', '', (string) $key));
            if (isset(self::WEIGHTS[$dim]) && $value !== null && trim((string) $value) !== '') {
                $out[$dim] = strtoupper(trim((string) $value));
            }
        }

        return $out;
    }

    /** @return list<ApprovalTopic> */
    private function chainOf(ApprovalTopic $node): array
    {
        $chain = [];
        for ($n = $node; $n !== null; $n = $n->parent) {
            array_unshift($chain, $n);
        }

        return $chain;
    }
}
