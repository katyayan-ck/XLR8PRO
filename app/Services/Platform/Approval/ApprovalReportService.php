<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval;

use App\Models\Approval\ApprovalCounter;
use App\Models\Approval\ApprovalRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approval reports (FRS §8.4, TOP-06): read the request projection and counters — never re-run
 * matching. Filters: fy, branch, topic (code prefix), item, level (winning), actor, source type.
 */
final class ApprovalReportService
{
    public const GROUPS = ['topic_code', 'branch_code', 'effective_level', 'item_key', 'source_type', 'fy'];

    /**
     * @param  array{fy?: string, branch?: string, topic?: string, item?: string, level?: int|string, actor?: int|string, source?: string}  $filters
     * @return array{totals: array<string, mixed>, rows: list<array<string, mixed>>, by_level: list<array<string, mixed>>}
     */
    public function summary(array $filters = [], string $groupBy = 'topic_code'): array
    {
        $groupBy = in_array($groupBy, self::GROUPS, true) ? $groupBy : 'topic_code';
        $requests = $this->query($filters)->get();

        return [
            'totals' => $this->figures($requests),
            'rows' => $requests->groupBy(fn (ApprovalRequest $r) => (string) ($r->{$groupBy} ?? '—'))
                ->map(fn ($set, $key) => ['group' => $key] + $this->figures($set))->sortByDesc('opened')->values()->all(),
            'by_level' => ApprovalCounter::query()->whereIn('request_id', $requests->pluck('id'))->where('is_system', false)
                ->selectRaw('level_no, count(*) as counters, count(distinct actor_id) as actors, sum(value) as total_value')
                ->groupBy('level_no')->orderBy('level_no')->get()
                ->map(fn ($r) => ['level' => (int) $r->level_no, 'counters' => (int) $r->counters, 'actors' => (int) $r->actors, 'total_value' => (float) $r->total_value])->all(),
        ];
    }

    /**
     * Flat request rows for export and drill-down.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(array $filters = []): array
    {
        return $this->query($filters)->with('requester')->latest('id')->limit(20000)->get()->map(fn (ApprovalRequest $r) => [
            'id' => $r->id,
            'fy' => $r->fy,
            'branch' => $r->branch_code,
            'topic' => $r->topic_code,
            'item' => $r->item_key,
            'source' => $r->source_type ? "{$r->source_type} #{$r->source_id}" : '',
            'requester' => $r->requester?->display_name,
            'value_type' => $r->value_type,
            'asked' => (float) $r->asked,
            'granted' => $r->status === ApprovalService::ACCEPTED ? (float) $r->effective_value : null,
            'winning_level' => $r->effective_level,
            'status' => $r->status,
            'auto' => $r->auto_accepted ? 'Yes' : 'No',
            'revisions' => $r->ask_revision,
            'opened' => $r->created_at?->format('Y-m-d H:i'),
            'closed' => $r->closed_at?->format('Y-m-d H:i'),
            'hours_to_close' => $r->closed_at ? round($r->created_at->diffInMinutes($r->closed_at) / 60, 1) : null,
        ])->all();
    }

    /** @return Builder<ApprovalRequest> */
    private function query(array $filters): Builder
    {
        return ApprovalRequest::query()
            ->when(($filters['fy'] ?? '') !== '', fn ($q) => $q->where('fy', $filters['fy']))
            ->when(($filters['branch'] ?? '') !== '', fn ($q) => $q->where('branch_code', strtoupper($filters['branch'])))
            ->when(($filters['topic'] ?? '') !== '', fn ($q) => $q->where('topic_code', 'like', strtoupper($filters['topic']).'%'))
            ->when(($filters['item'] ?? '') !== '', fn ($q) => $q->where('item_key', strtolower($filters['item'])))
            ->when(($filters['level'] ?? '') !== '', fn ($q) => $q->where('effective_level', (int) $filters['level']))
            ->when(($filters['actor'] ?? '') !== '', fn ($q) => $q->whereIn('id', ApprovalCounter::query()->where('actor_id', (int) $filters['actor'])->select('request_id')))
            ->when(($filters['source'] ?? '') !== '', fn ($q) => $q->where('source_type', strtoupper($filters['source'])));
    }

    /**
     * @param  iterable<ApprovalRequest>  $requests
     * @return array<string, mixed>
     */
    private function figures(iterable $requests): array
    {
        $set = collect($requests);
        $accepted = $set->where('status', ApprovalService::ACCEPTED);
        $closed = $set->whereNotNull('closed_at');
        $asked = (float) $accepted->sum(fn ($r) => (float) $r->asked);
        $granted = (float) $accepted->sum(fn ($r) => (float) $r->effective_value);

        return [
            'opened' => $set->count(),
            'accepted' => $accepted->count(),
            'withdrawn' => $set->where('status', ApprovalService::WITHDRAWN)->count(),
            'open' => $set->where('status', ApprovalService::OPEN)->count(),
            'asked_accepted' => $asked,
            'granted' => $granted,
            'grant_ratio' => $asked > 0 ? round($granted / $asked * 100, 1) : null,
            'auto_share' => $accepted->count() > 0 ? round($accepted->where('auto_accepted', true)->count() / $accepted->count() * 100, 1) : null,
            'avg_hours_to_close' => $closed->isEmpty() ? null : round($closed->avg(fn ($r) => $r->created_at->diffInMinutes($r->closed_at)) / 60, 1),
        ];
    }
}
