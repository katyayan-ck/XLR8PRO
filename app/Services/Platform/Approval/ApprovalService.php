<?php

declare(strict_types=1);

namespace App\Services\Platform\Approval;

use App\Events\Platform\ApprovalChanged;
use App\Models\Admin\UserScope;
use App\Models\Approval\ApprovalCounter;
use App\Models\Approval\ApprovalEvent;
use App\Models\Approval\ApprovalRequest;
use App\Models\User;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Approval engine (FRS §7) — the only writer of approval requests, counters and events.
 *
 * - Authority is frozen on the request snapshot (topic, matched rule, levels) at open time.
 * - Visibility is live: holders of a snapshot level whose org fits the rule scope, the requester,
 *   and UTL_APPR_ADMIN holders.
 * - Decision: highest level among counters on the current ask revision wins; same level → latest.
 * - Approvers never reject; they counter (0 … asked). The requester accepts or withdraws.
 */
final class ApprovalService
{
    public const OPEN = 'OPEN';

    public const ACCEPTED = 'ACCEPTED';

    public const WITHDRAWN = 'WITHDRAWN';

    public const BOXES = ['TO_ACT', 'RAISED', 'TEAM', 'CLOSED'];

    public function __construct(
        private readonly TopicService $topics,
        private readonly RuleService $rules,
        private readonly ChatService $chat,
        private readonly NotifyService $notify,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Open a request (APR-01). `$ask` = {value_type?, asked, scope?: array, remark?}.
     *
     * @param  Model|array{type: string, id: int}|null  $source  the source document (usually a Quote)
     * @param  array{value_type?: string, asked?: int|float|string, scope?: array<string, mixed>, remark?: string}  $ask
     */
    public function open(Model|array|null $source, string $topicCode, ?string $itemKey, array $ask, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $requester = $actorId ? User::query()->with('employee')->find($actorId) : null;
        if (! $requester) {
            return Result::fail('UNAUTHORISED', 'A request needs a signed-in requester.');
        }
        $resolved = $this->topics->resolve($itemKey ?: $topicCode);
        if ($resolved === null) {
            return Result::fail('UNKNOWN_TOPIC', "No active approval topic {$topicCode}".($itemKey ? " / {$itemKey}" : '').'.');
        }
        $scope = $this->rules->normaliseScope((array) ($ask['scope'] ?? []));
        $scope['branch'] ??= strtoupper((string) ($requester->employee?->primary_branch_code ?? '')) ?: null;
        $scope = array_filter($scope);
        $rule = $this->rules->match($resolved, $scope);
        if ($rule === null) {
            return Result::fail('NO_RULE', "No approval rule covers {$resolved['node']->code} for this scope.");
        }
        $asked = (float) ($ask['asked'] ?? 0);
        $valueType = strtoupper((string) ($ask['value_type'] ?? $resolved['value_type']));
        if ($asked < 0 || ($valueType === 'PERCENTAGE' && $asked > 100)) {
            return Result::fail('INVALID_ASK', 'The ask must be zero or more (and at most 100 for a percentage).');
        }

        $node = $resolved['node'];
        $levels = $rule->levels->map(fn ($l) => [
            'level_no' => (int) $l->level_no, 'designation_code' => $l->designation_code, 'user_ids' => $l->user_ids,
            'value_type' => $l->value_type, 'std' => $l->std_value, 'min' => $l->min_value, 'max' => $l->max_value,
        ])->values()->all();
        $snapshot = [
            'topic' => ['id' => $node->id, 'code' => $node->code, 'title' => $node->title, 'item_key' => $node->item_key],
            'chain' => array_map(fn ($n) => $n->code, $resolved['chain']),
            'mode' => $resolved['mode'],
            'rule' => ['id' => $rule->id, 'topic_code' => $rule->topic?->code, 'scope' => array_filter($rule->scopeTuple())],
            'levels' => $levels,
            'taken_at' => now()->toIso8601String(),
        ];
        [$sourceType, $sourceId] = $this->sourceRef($source);

        $request = DB::transaction(function () use ($node, $resolved, $sourceType, $sourceId, $requester, $valueType, $asked, $scope, $snapshot, $levels) {
            $request = ApprovalRequest::create([
                'topic_id' => $node->id, 'topic_code' => $node->code, 'topic_title' => $node->title, 'item_key' => $node->item_key,
                'mode' => $resolved['mode'], 'source_type' => $sourceType, 'source_id' => $sourceId, 'requester_id' => $requester->id,
                'value_type' => $valueType, 'asked' => $asked, 'ask_revision' => 1, 'scope' => $scope, 'snapshot' => $snapshot,
                'status' => self::OPEN, 'current_level' => $resolved['mode'] === 'LINEAR' ? (int) collect($levels)->min('level_no') : null,
                'branch_code' => $scope['branch'] ?? null, 'fy' => $this->fy(),
            ]);
            $this->event($request, 'OPENED', $requester->id, null, $asked, ['scope' => $scope, 'rule_id' => $snapshot['rule']['id']]);

            return $request;
        });

        $this->chat->event($request, 'OPENED', "Asked {$this->money($request, $asked)} on {$node->title}", ['asked' => $asked], $requester->id);
        if ($source instanceof Model) {
            $this->chat->event($source, 'APPROVAL_REQUESTED', "Approval #{$request->id} asked: {$node->title} {$this->money($request, $asked)}", ['approval_id' => $request->id], $requester->id);
        }
        $this->chat->subscribe($request, $requester->id);

        // APR-08: an ask within the requester's own power auto-closes accepted (flag), still snapshotted
        $ownLevel = $this->levelOf($request, $requester->id);
        if ($this->settings->flag('approval.auto_accept_own_power') && $ownLevel !== null && ($asked == 0 || ($ownLevel['max'] !== null && $asked <= (float) $ownLevel['max']))) {
            $this->autoAccept($request, $requester->id, $ownLevel, $asked);
        } else {
            $this->notify->toMany($this->visibleUserIds($request, exceptRequester: true))->kind('N')->about('APPROVAL', $request->id)->actor($requester->id)
                ->title("{actor} asks {$this->money($request, $asked)}: {$node->title}")->body($ask['remark'] ?? null)->send();
        }
        ApprovalChanged::dispatch($request->id, 'OPENED', $requester->id);

        return Result::ok(['id' => $request->id, 'status' => $request->fresh()->status, 'auto_accepted' => (bool) $request->fresh()->auto_accepted]);
    }

    /**
     * Counter on the current ask revision (APR-02): actor must see the request and hold a level on
     * the snapshot; the value must be within that level's max. LINEAR accepts only the current level.
     */
    public function counter(int $requestId, ?int $actorId, int|float|string $value, ?string $remark = null): Result
    {
        $actorId ??= $this->actor();
        $request = ApprovalRequest::query()->find($requestId);
        if (! $request) {
            return Result::fail('NOT_FOUND', 'Approval request not found.');
        }
        if ($request->status !== self::OPEN) {
            return Result::fail('CLOSED', 'This request is closed.');
        }
        $level = $this->levelOf($request, (int) $actorId);
        if ($level === null || ! $this->canSee($request, (int) $actorId)) {
            return Result::fail('NO_AUTHORITY', 'You hold no level on this request.');
        }
        if ($request->mode === 'LINEAR' && (int) $request->current_level !== $level['level_no']) {
            return Result::fail('NOT_YOUR_TURN', "Only level {$request->current_level} may act now.");
        }
        $value = (float) $value;
        if ($value < 0) {
            return Result::fail('INVALID_VALUE', 'A counter cannot be negative.');
        }
        if ($request->value_type === 'FLAG' && ! in_array($value, [0.0, 1.0], true)) {
            return Result::fail('INVALID_VALUE', 'A flag counter is 0 (no) or 1 (yes).');
        }
        if ($level['max'] !== null && $value > (float) $level['max']) {
            return Result::fail('EXCEEDS_POWER', "Your level may grant at most {$this->money($request, (float) $level['max'])}.");
        }

        DB::transaction(function () use ($request, $actorId, $level, $value, $remark) {
            ApprovalCounter::create([
                'request_id' => $request->id, 'ask_revision' => $request->ask_revision, 'level_no' => $level['level_no'],
                'actor_id' => $actorId, 'value' => $value, 'remark' => $remark ? mb_substr(strip_tags($remark), 0, 1000) : null,
            ]);
            $this->event($request, 'COUNTERED', (int) $actorId, $level['level_no'], $value, ['remark' => $remark]);
            $this->project($request);
            if ($request->mode === 'LINEAR') {
                $next = collect($request->snapshot['levels'])->pluck('level_no')->filter(fn ($n) => $n > $level['level_no'])->min();
                $request->update(['current_level' => $next ?? $level['level_no']]);
            }
        });

        $this->chat->event($request, 'COUNTERED', "L{$level['level_no']} countered {$this->money($request, $value)}", ['value' => $value, 'level' => $level['level_no']], $actorId);
        if ($remark) {
            $this->chat->remark($request, $remark, null, null, false, $actorId);
        }
        $this->chat->subscribe($request, (int) $actorId);
        $this->notify->to($request->requester_id)->kind('N')->about('APPROVAL', $request->id)->actor($actorId)
            ->title("{actor} (L{$level['level_no']}) countered {$this->money($request, $value)} on {$request->topic_title}")->send();
        ApprovalChanged::dispatch($request->id, 'COUNTERED', $actorId);

        return Result::ok(['effective' => $this->effective($request->id)]);
    }

    /**
     * Effective grant (APR-03): highest level among counters on the current revision; ties at the
     * same level → latest counter. Null when nobody has countered this revision.
     *
     * @return array{level: int, actor: int, value: float, basis: string, counter_id: int}|null
     */
    public function effective(int $requestId): ?array
    {
        $request = ApprovalRequest::query()->find($requestId);
        if (! $request) {
            return null;
        }
        $winner = ApprovalCounter::query()->where('request_id', $request->id)->where('ask_revision', $request->ask_revision)
            ->orderByDesc('level_no')->orderByDesc('created_at')->orderByDesc('id')->first();

        return $winner ? [
            'level' => $winner->level_no, 'actor' => $winner->actor_id, 'value' => (float) $winner->value,
            'basis' => $winner->is_system ? 'OWN_POWER' : 'HIGHEST_LEVEL', 'counter_id' => $winner->id,
        ] : null;
    }

    /** Requester revises the ask (APR-04): new revision, older counters become stale. */
    public function reviseAsk(int $requestId, ?int $actorId, int|float|string $newAsk, ?string $remark = null): Result
    {
        $actorId ??= $this->actor();
        $request = ApprovalRequest::query()->find($requestId);
        if (! $request) {
            return Result::fail('NOT_FOUND', 'Approval request not found.');
        }
        if ((int) $request->requester_id !== (int) $actorId) {
            return Result::fail('FORBIDDEN', 'Only the requester may revise the ask.');
        }
        if ($request->status !== self::OPEN) {
            return Result::fail('CLOSED', 'This request is closed.');
        }
        $newAsk = (float) $newAsk;
        if ($newAsk < 0 || ($request->value_type === 'PERCENTAGE' && $newAsk > 100)) {
            return Result::fail('INVALID_ASK', 'The ask must be zero or more (and at most 100 for a percentage).');
        }

        $from = (float) $request->asked;
        DB::transaction(function () use ($request, $actorId, $newAsk, $from, $remark) {
            $request->update([
                'asked' => $newAsk, 'ask_revision' => $request->ask_revision + 1,
                'effective_level' => null, 'effective_value' => null, 'effective_actor_id' => null,
                'current_level' => $request->mode === 'LINEAR' ? (int) collect($request->snapshot['levels'])->min('level_no') : null,
            ]);
            $this->event($request, 'REVISED', (int) $actorId, null, $newAsk, ['from' => $from, 'remark' => $remark]);
        });

        $this->chat->event($request, 'REVISED', "Ask revised {$this->money($request, $from)} → {$this->money($request, $newAsk)} (revision {$request->ask_revision})", ['from' => $from, 'to' => $newAsk], $actorId);
        $this->notify->toMany($this->visibleUserIds($request, exceptRequester: true))->kind('N')->about('APPROVAL', $request->id)->actor($actorId)
            ->title("{actor} revised the ask to {$this->money($request, $newAsk)}: {$request->topic_title}")->body($remark)->send();
        ApprovalChanged::dispatch($request->id, 'REVISED', $actorId);

        return Result::ok(['ask_revision' => $request->ask_revision]);
    }

    /** Close ACCEPTED or WITHDRAWN (APR-05): the requester, or a UTL_APPR_ADMIN holder. */
    public function close(int $requestId, ?int $actorId, string $outcome, ?string $remark = null): Result
    {
        $actorId ??= $this->actor();
        $outcome = strtoupper($outcome);
        $request = ApprovalRequest::query()->find($requestId);
        if (! $request) {
            return Result::fail('NOT_FOUND', 'Approval request not found.');
        }
        if (! in_array($outcome, [self::ACCEPTED, self::WITHDRAWN], true)) {
            return Result::fail('INVALID_OUTCOME', 'Close as ACCEPTED or WITHDRAWN.');
        }
        $isAdmin = (bool) User::query()->find($actorId)?->can('UTL_APPR_ADMIN');
        if ((int) $request->requester_id !== (int) $actorId && ! $isAdmin) {
            return Result::fail('FORBIDDEN', 'Only the requester may close this request.');
        }
        if ($request->status !== self::OPEN) {
            return Result::fail('CLOSED', 'This request is already closed.');
        }
        $effective = $this->effective($request->id);
        if ($outcome === self::ACCEPTED && $effective === null) {
            return Result::fail('NO_GRANT', 'Nobody has countered the current ask yet; withdraw or wait.');
        }

        DB::transaction(function () use ($request, $actorId, $outcome, $effective, $remark) {
            $request->update(['status' => $outcome, 'closed_at' => now(), 'closed_by' => $actorId]);
            $this->event($request, $outcome, (int) $actorId, $effective['level'] ?? null, $effective['value'] ?? null, ['remark' => $remark]);
        });

        $text = $outcome === self::ACCEPTED ? "Accepted {$this->money($request, (float) $effective['value'])} (L{$effective['level']})" : 'Withdrawn';
        $this->chat->event($request, $outcome, $text, ['effective' => $effective], $actorId);
        if ($request->source_type && $request->source_id && ($source = $this->chat->resolve($request->source_type, (int) $request->source_id))) {
            $this->chat->event($source, 'APPROVAL_'.$outcome, "Approval #{$request->id} {$request->topic_title}: {$text}", ['approval_id' => $request->id], $actorId);
        }
        $counterers = ApprovalCounter::query()->where('request_id', $request->id)->where('is_system', false)->distinct()->pluck('actor_id')->map(fn ($id) => (int) $id)->all();
        $this->notify->toMany($counterers)->kind('N')->about('APPROVAL', $request->id)->actor($actorId)
            ->title("{actor} closed approval #{$request->id} ({$request->topic_title}): {$text}")->send();
        ApprovalChanged::dispatch($request->id, $outcome, $actorId);

        return Result::ok(['status' => $outcome, 'effective' => $effective]);
    }

    /**
     * OPEN requests the user may see now (APR-06), newest first.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function visibleTo(int $userId): Collection
    {
        return ApprovalRequest::query()->where('status', self::OPEN)->latest('id')->limit(1000)->get()
            ->filter(fn (ApprovalRequest $r) => $this->canSee($r, $userId))->values();
    }

    /**
     * Inboxes: TO_ACT (visible, you hold a level, not yours), RAISED (yours), TEAM (open requests
     * where the current grant sits below your level — monitoring, UC-APR-4), CLOSED (yours or countered).
     */
    public function inbox(int $userId, string $box, int $perPage = 20): LengthAwarePaginator
    {
        $box = strtoupper($box);
        $query = ApprovalRequest::query()->with('requester')->latest('id');

        if ($box === 'RAISED') {
            return $query->where('requester_id', $userId)->where('status', self::OPEN)->paginate($perPage)->withQueryString();
        }
        if ($box === 'CLOSED') {
            return $query->where('status', '!=', self::OPEN)
                ->where(fn ($q) => $q->where('requester_id', $userId)->orWhereIn('id', ApprovalCounter::query()->where('actor_id', $userId)->select('request_id')))
                ->paginate($perPage)->withQueryString();
        }

        $ids = $this->visibleTo($userId)->filter(function (ApprovalRequest $r) use ($userId, $box) {
            if ((int) $r->requester_id === $userId) {
                return false;
            }
            $level = $this->levelOf($r, $userId);
            if ($level === null) {
                return false;
            }

            return $box === 'TEAM' ? $r->effective_level !== null && $r->effective_level < $level['level_no'] : true;
        })->pluck('id');

        return $query->whereIn('id', $ids)->paginate($perPage)->withQueryString();
    }

    /** @return array<string, int> */
    public function inboxCounts(int $userId): array
    {
        return collect(self::BOXES)->mapWithKeys(fn ($box) => [$box => $this->inbox($userId, $box, 1)->total()])->all();
    }

    /**
     * Could this person grant this without a request (APR-07)?
     *
     * @param  array<string, mixed>  $scope
     */
    public function authorize(int $actorId, string $itemKeyOrCode, array $scope, int|float|string $value): bool
    {
        $preview = $this->preview($actorId, $itemKeyOrCode, $scope, $value);

        return $preview['ok'] && $preview['actor_level'] !== null && ($preview['actor_level']['max'] === null || (float) $value <= (float) $preview['actor_level']['max']);
    }

    /**
     * Simulation (TOP-05): matched rule, each level with its max and who is visible now, the actor's
     * own level and whether the ask would auto-pass.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function preview(int $actorId, string $itemKeyOrCode, array $scope, int|float|string $ask): array
    {
        $resolved = $this->topics->resolve($itemKeyOrCode);
        if ($resolved === null) {
            return ['ok' => false, 'message' => 'Unknown topic or item key.'];
        }
        $actor = User::query()->with('employee')->find($actorId);
        $scope = $this->rules->normaliseScope($scope);
        $scope['branch'] ??= strtoupper((string) ($actor?->employee?->primary_branch_code ?? '')) ?: null;
        $scope = array_filter($scope);
        $rule = $this->rules->match($resolved, $scope);
        if ($rule === null) {
            return ['ok' => false, 'message' => 'No rule covers this topic and scope.', 'topic' => $resolved['node']->code, 'scope' => $scope];
        }
        $draft = new ApprovalRequest(['snapshot' => ['levels' => $rule->levels->map(fn ($l) => [
            'level_no' => (int) $l->level_no, 'designation_code' => $l->designation_code, 'user_ids' => $l->user_ids,
            'value_type' => $l->value_type, 'std' => $l->std_value, 'min' => $l->min_value, 'max' => $l->max_value,
        ])->all(), 'rule' => ['scope' => array_filter($rule->scopeTuple())]], 'requester_id' => $actorId]);
        $actorLevel = $this->levelOf($draft, $actorId);
        $levels = collect($draft->snapshot['levels'])->map(function (array $l) use ($draft) {
            $users = $this->usersForLevel($draft, $l);

            return $l + ['visible_users' => $users->map(fn (User $u) => $u->display_name)->values()->all(), 'visible_count' => $users->count()];
        })->all();

        return [
            'ok' => true,
            'topic' => $resolved['node']->code,
            'chain' => array_map(fn ($n) => $n->code, $resolved['chain']),
            'mode' => $resolved['mode'],
            'scope' => $scope,
            'rule_id' => $rule->id,
            'rule_scope' => array_filter($rule->scopeTuple()),
            'levels' => $levels,
            'actor_level' => $actorLevel,
            'would_auto_pass' => $this->settings->flag('approval.auto_accept_own_power') && $actorLevel !== null
                && ((float) $ask == 0 || ($actorLevel['max'] !== null && (float) $ask <= (float) $actorLevel['max'])),
            'top_level_max' => collect($levels)->sortByDesc('level_no')->first()['max'] ?? null,
        ];
    }

    /** Requester, admins and live-visible level holders may see a request. */
    public function canSee(ApprovalRequest $request, int $userId): bool
    {
        if ((int) $request->requester_id === $userId) {
            return true;
        }
        $user = User::query()->with('employee')->find($userId);
        if (! $user) {
            return false;
        }
        if ($user->can('UTL_APPR_ADMIN') || $user->can('UTL_APPR_REPORT')) {
            return true;
        }
        if ($this->levelOf($request, $userId) === null) {
            return ApprovalCounter::query()->where('request_id', $request->id)->where('actor_id', $userId)->exists();
        }

        return $this->orgFits($request, $user);
    }

    /**
     * The user's (highest) level on the snapshot, by live designation or STATIC user list.
     *
     * @return array{level_no: int, designation_code: ?string, max: ?string, min: ?string, std: ?string, value_type: string}|null
     */
    public function levelOf(ApprovalRequest $request, int $userId): ?array
    {
        $user = User::query()->with('employee')->find($userId);
        $designation = strtoupper((string) ($user?->employee?->designation_code ?? ''));
        $level = collect($request->snapshot['levels'] ?? [])
            ->filter(fn (array $l) => ($designation !== '' && strtoupper((string) $l['designation_code']) === $designation) || in_array($userId, (array) ($l['user_ids'] ?? []), true))
            ->sortByDesc('level_no')->first();

        return $level ? [
            'level_no' => (int) $level['level_no'], 'designation_code' => $level['designation_code'],
            'max' => $level['max'], 'min' => $level['min'], 'std' => $level['std'], 'value_type' => $level['value_type'],
        ] : null;
    }

    /**
     * Panel data (APR-10): ask, counters by level (active vs stale), effective grant, what the viewer may do.
     *
     * @return array<string, mixed>
     */
    public function panel(ApprovalRequest $request, int $viewerId): array
    {
        $counters = ApprovalCounter::query()->with('actor')->where('request_id', $request->id)->orderBy('created_at')->orderBy('id')->get();
        $effective = $this->effective($request->id);
        $viewerLevel = $this->levelOf($request, $viewerId);
        $min = collect($request->snapshot['levels'] ?? [])->pluck('min')->filter()->max();

        return [
            'request' => $request,
            'levels' => $request->snapshot['levels'] ?? [],
            'counters' => $counters->map(fn (ApprovalCounter $c) => [
                'id' => $c->id, 'level' => $c->level_no, 'actor' => $c->actor?->display_name ?? 'System', 'value' => (float) $c->value,
                'remark' => $c->remark, 'revision' => $c->ask_revision, 'stale' => $c->ask_revision !== $request->ask_revision,
                'winning' => $effective && $effective['counter_id'] === $c->id, 'is_system' => $c->is_system, 'at' => $c->created_at,
            ])->all(),
            'effective' => $effective,
            'below_min' => $effective !== null && $min !== null && $effective['value'] < (float) $min,
            'viewer_level' => $viewerLevel,
            'can_counter' => $request->status === self::OPEN && $viewerLevel !== null && $this->canSee($request, $viewerId)
                && ($request->mode !== 'LINEAR' || (int) $request->current_level === $viewerLevel['level_no']),
            'is_requester' => (int) $request->requester_id === $viewerId,
            'can_close' => $request->status === self::OPEN && ((int) $request->requester_id === $viewerId || (bool) User::query()->find($viewerId)?->can('UTL_APPR_ADMIN')),
            'source_url' => $this->notify->deepLink($request->source_type, $request->source_id ? (int) $request->source_id : null),
        ];
    }

    /** @return list<int> users who may see the request now */
    public function visibleUserIds(ApprovalRequest $request, bool $exceptRequester = false): array
    {
        $ids = collect($request->snapshot['levels'] ?? [])->flatMap(fn (array $l) => $this->usersForLevel($request, $l)->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values();

        return $ids->reject(fn ($id) => $exceptRequester && $id === (int) $request->requester_id)->values()->all();
    }

    /** @return Collection<int, User> */
    private function usersForLevel(ApprovalRequest $request, array $level): Collection
    {
        $query = User::query()->with(['employee', 'person'])->where('is_active', true);
        if (! empty($level['user_ids'])) {
            $query->whereIn('id', (array) $level['user_ids']);
        } elseif ($level['designation_code'] ?? null) {
            $query->whereHas('employee', fn ($q) => $q->where('designation_code', $level['designation_code']));
        } else {
            return collect();
        }

        return $query->get()->filter(fn (User $u) => ! empty($level['user_ids']) || $this->orgFits($request, $u))->values();
    }

    /** Live org fit: a rule scoped to a branch is visible to that branch's staff, users scoped to it, or unscoped users. */
    private function orgFits(ApprovalRequest $request, User $user): bool
    {
        $branch = $request->snapshot['rule']['scope']['branch'] ?? null;
        if ($branch === null || $user->bypass_data_scoping) {
            return true;
        }
        if (strtoupper((string) $user->employee?->primary_branch_code) === strtoupper($branch)) {
            return true;
        }

        return UserScope::query()->where('user_id', $user->id)->where('scope_type', 'BRANCH')->where('scope_code', $branch)->where('is_active', true)->exists();
    }

    /** Recompute the projection's effective grant from counters. */
    private function project(ApprovalRequest $request): void
    {
        $effective = $this->effective($request->id);
        $request->update([
            'effective_level' => $effective['level'] ?? null,
            'effective_value' => $effective['value'] ?? null,
            'effective_actor_id' => $effective['actor'] ?? null,
        ]);
    }

    /** @param array{level_no: int, max: ?string} $level */
    private function autoAccept(ApprovalRequest $request, int $requesterId, array $level, float $asked): void
    {
        DB::transaction(function () use ($request, $requesterId, $level, $asked) {
            ApprovalCounter::create([
                'request_id' => $request->id, 'ask_revision' => $request->ask_revision, 'level_no' => $level['level_no'],
                'actor_id' => $requesterId, 'value' => $asked, 'remark' => 'Within own power', 'is_system' => true,
            ]);
            $this->project($request);
            $request->update(['status' => self::ACCEPTED, 'auto_accepted' => true, 'closed_at' => now(), 'closed_by' => $requesterId]);
            $this->event($request, 'AUTO_ACCEPTED', $requesterId, $level['level_no'], $asked, []);
        });
        $this->chat->event($request, 'AUTO_ACCEPTED', "Auto-accepted within own power (L{$level['level_no']})", [], $requesterId);
    }

    /** @param array<string, mixed> $data */
    private function event(ApprovalRequest $request, string $type, ?int $actorId, ?int $level, ?float $value, array $data): void
    {
        ApprovalEvent::create([
            'request_id' => $request->id, 'type' => $type, 'actor_id' => $actorId, 'ask_revision' => $request->ask_revision,
            'level_no' => $level, 'value' => $value, 'data' => array_filter($data, fn ($v) => $v !== null),
        ]);
    }

    /** @return array{0: ?string, 1: ?int} */
    private function sourceRef(Model|array|null $source): array
    {
        if ($source instanceof Model) {
            return [$this->chat->refType($source) ?? class_basename($source), (int) $source->getKey()];
        }
        if (is_array($source) && isset($source['type'], $source['id'])) {
            return [strtoupper((string) $source['type']), (int) $source['id']];
        }

        return [null, null];
    }

    private function money(ApprovalRequest $request, float $value): string
    {
        return match ($request->value_type) {
            'PERCENTAGE' => rtrim(rtrim(number_format($value, 2), '0'), '.').'%',
            'FLAG' => $value > 0 ? 'Yes' : 'No',
            default => '₹'.number_format($value, 0),
        };
    }

    private function fy(): string
    {
        $start = now()->month >= 4 ? now()->year : now()->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    private function actor(): ?int
    {
        return auth(backpack_guard_name())->id() ?? auth()->id();
    }
}
