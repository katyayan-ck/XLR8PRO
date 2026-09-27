<?php

declare(strict_types=1);

namespace App\Services\Platform\Task;

use App\Events\Platform\TaskChanged;
use App\Models\User;
use App\Models\Utilities\Task\Task;
use App\Models\Utilities\Task\TaskPerson;
use App\Services\KeywordValueService;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Notify\NotifyService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Task utility (FRS §5) — the only writer of task / task_person. Owner, Assignee(s), Follower and
 * Snooper roles; status FRESH → INPROGRESS / HOLD → SUBMITTED → CLOSED ↔ REOPENED; the locked rights
 * matrix (§5.3) lives in `rights()` and `TRANSITIONS`. Every change: persist → Chat → Notify → event.
 */
final class TaskService
{
    public const OWNER = 'OWNER';

    public const ASSIGNEE = 'ASSIGNEE';

    public const FOLLOWER = 'FOLLOWER';

    public const SNOOPER = 'SNOOPER';

    /** Role precedence when one user holds several roles. */
    public const ROLES = [self::OWNER, self::ASSIGNEE, self::FOLLOWER, self::SNOOPER];

    public const STATUSES = ['FRESH', 'INPROGRESS', 'HOLD', 'SUBMITTED', 'CLOSED', 'REOPENED'];

    public const BOXES = ['CREATED' => self::OWNER, 'ASSIGNED' => self::ASSIGNEE, 'FOLLOWED' => self::FOLLOWER, 'SNOOPED' => self::SNOOPER];

    private const OPEN = ['FRESH', 'INPROGRESS', 'HOLD', 'REOPENED'];

    /** Rights matrix (FRS §5.3): target status => [role => allowed from-statuses]. */
    private const TRANSITIONS = [
        'INPROGRESS' => [self::OWNER => self::OPEN, self::ASSIGNEE => self::OPEN],
        'HOLD' => [self::OWNER => self::OPEN, self::ASSIGNEE => self::OPEN],
        'SUBMITTED' => [self::ASSIGNEE => self::OPEN],
        'CLOSED' => [self::OWNER => ['FRESH', 'INPROGRESS', 'HOLD', 'SUBMITTED', 'REOPENED']],
        'REOPENED' => [self::OWNER => ['CLOSED', 'SUBMITTED']],
    ];

    /** Notification copy per role (TSK-01: distinct copy for each role). */
    private const COPY = [
        self::OWNER => 'You created task: {title}',
        self::ASSIGNEE => '{actor} assigned you a task: {title}',
        self::FOLLOWER => '{actor} added you as a follower on: {title}',
        self::SNOOPER => 'You can now view task: {title}',
    ];

    public function __construct(private readonly ChatService $chat, private readonly NotifyService $notify) {}

    /**
     * Create a task (TSK-01, TSK-02).
     *
     * @param  array{title?: string, type?: string, priority?: ?string, owner_id?: int, assignees?: list<int>, followers?: list<int>, snoopers?: list<int>, details?: ?string, deadline?: ?string, ref_type?: ?string, ref_id?: ?int}  $payload
     */
    public function create(array $payload, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $ownerId = (int) ($payload['owner_id'] ?? $actorId);
        $error = $this->validate($payload, $ownerId);
        if ($error !== null) {
            return $error;
        }
        $people = $this->people($payload, $ownerId);

        $task = DB::transaction(function () use ($payload, $ownerId, $people) {
            $task = Task::create([
                'title' => mb_substr(trim(strip_tags((string) $payload['title'])), 0, 250),
                'type' => $this->typeCode((string) $payload['type']),
                'priority' => $payload['priority'] ?? 'NORMAL',
                'status' => 'FRESH',
                'owner_id' => $ownerId,
                'details' => $this->cleanHtml($payload['details'] ?? null),
                'deadline' => ! empty($payload['deadline']) ? Carbon::parse($payload['deadline']) : null,
                'ref_type' => isset($payload['ref_type']) ? strtoupper((string) $payload['ref_type']) : null,
                'ref_id' => $payload['ref_id'] ?? null,
                'is_group' => count($people[self::ASSIGNEE]) > 1,
            ]);
            $this->writePeople($task, $people);

            return $task;
        });

        $this->chat->event($task, 'CREATED', "Task created: {$task->title}", ['status' => 'FRESH'], $actorId);
        if ($task->ref_type && $task->ref_id && ($parent = $this->chat->resolve($task->ref_type, (int) $task->ref_id))) {
            $this->chat->event($parent, 'TASK_CREATED', "Task #{$task->id} created: {$task->title}", ['task_id' => $task->id], $actorId);
        }
        $this->syncSubscriptions($task);
        foreach ($people as $role => $ids) {
            $this->notifyRole($task, $role, $ids, $actorId);
        }
        TaskChanged::dispatch($task->id, 'CREATED', $actorId);

        return Result::ok(['id' => $task->id]);
    }

    /**
     * Owner edits the header and people (TSK-06): associations are rebuilt, added people are told
     * they were added, removed people are told and lose the task from their inboxes.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $taskId, array $payload, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $task = Task::query()->find($taskId);
        if (! $task) {
            return Result::fail('NOT_FOUND', 'Task not found.');
        }
        if (! in_array('edit', $this->rights($task, (int) $actorId), true)) {
            return Result::fail('FORBIDDEN', 'Only the owner may edit this task.');
        }
        $payload += ['title' => $task->title, 'type' => $task->type];
        $error = $this->validate($payload, (int) $task->owner_id);
        if ($error !== null) {
            return $error;
        }

        $before = $this->roster($task);
        $after = $this->people($payload, (int) $task->owner_id);

        DB::transaction(function () use ($task, $payload, $after) {
            $task->update([
                'title' => mb_substr(trim(strip_tags((string) $payload['title'])), 0, 250),
                'type' => $this->typeCode((string) $payload['type']),
                'priority' => $payload['priority'] ?? $task->priority,
                'details' => array_key_exists('details', $payload) ? $this->cleanHtml($payload['details']) : $task->details,
                'deadline' => array_key_exists('deadline', $payload) ? (! empty($payload['deadline']) ? Carbon::parse($payload['deadline']) : null) : $task->deadline,
                'is_group' => count($after[self::ASSIGNEE]) > 1,
            ]);
            TaskPerson::query()->where('task_id', $task->id)->delete();
            $this->writePeople($task, $after);
        });

        $this->chat->event($task, 'UPDATED', 'Task details updated', [], $actorId);
        $this->syncSubscriptions($task, $before);
        foreach (self::ROLES as $role) {
            $added = array_values(array_diff($after[$role], $before[$role]));
            $removed = array_values(array_diff($before[$role], $after[$role]));
            $this->notifyRole($task, $role, $added, $actorId);
            if ($removed !== [] && $role !== self::OWNER) {
                $this->notify->toMany($removed)->kind('N')->about('TASK', $task->id)->actor($actorId)
                    ->title("You were removed from task: {$task->title}")->send();
            }
        }
        TaskChanged::dispatch($task->id, 'UPDATED', $actorId);

        return Result::ok(['id' => $task->id]);
    }

    /**
     * Follow-up = remark and/or status change in one call (TSK-03). `$status` null or NO_CHANGE
     * keeps the status. Illegal transitions return FORBIDDEN_TRANSITION.
     */
    public function followUp(int $taskId, ?int $actorId, ?string $remark, ?string $status = null, ?UploadedFile $file = null): Result
    {
        $actorId ??= $this->actor();
        $task = Task::query()->find($taskId);
        if (! $task) {
            return Result::fail('NOT_FOUND', 'Task not found.');
        }
        $rights = $this->rights($task, (int) $actorId);
        if (! in_array('remark', $rights, true)) {
            return Result::fail('FORBIDDEN', 'You may view this task but not follow it up.');
        }
        $status = $status === null || $status === '' || strtoupper($status) === 'NO_CHANGE' ? null : strtoupper($status);
        if ($status !== null && ! in_array('status:'.$status, $rights, true)) {
            return Result::fail('FORBIDDEN_TRANSITION', "You cannot move this task from {$task->status} to {$status}.");
        }
        if ($status === null && trim((string) $remark) === '' && ! $file) {
            return Result::fail('EMPTY', 'Write a remark, attach a file or change the status.');
        }

        if ($status !== null) {
            $from = $task->status;
            $task->update([
                'status' => $status,
                'submitted_at' => $status === 'SUBMITTED' ? now() : $task->submitted_at,
                'closed_at' => $status === 'CLOSED' ? now() : ($status === 'REOPENED' ? null : $task->closed_at),
            ]);
            $this->chat->event($task, 'STATUS_CHANGED', "Status {$from} → {$status}", ['from' => $from, 'to' => $status], $actorId);
            $this->notifyStatus($task, $from, $status, $actorId);
        }
        if (trim((string) $remark) !== '' || $file) {
            $posted = $this->chat->remark($task, (string) $remark, $file, null, false, $actorId);
            if (! $posted->ok) {
                return $posted;
            }
        }
        TaskChanged::dispatch($task->id, $status ?? 'REMARKED', $actorId);

        return Result::ok(['id' => $task->id, 'status' => $task->status]);
    }

    /** One of the four inboxes (TSK-04): CREATED, ASSIGNED, FOLLOWED or SNOOPED. */
    public function inbox(int $userId, string $box, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $role = self::BOXES[strtoupper($box)] ?? self::ASSIGNEE;

        return Task::query()
            ->whereIn('id', TaskPerson::query()->where('user_id', $userId)->where('role', $role)->select('task_id'))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', strtoupper($filters['status'])))
            ->when(($filters['open'] ?? false), fn ($q) => $q->whereNotIn('status', ['CLOSED']))
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'))
            ->with('owner:id,username,person_code')
            ->orderByRaw("status = 'CLOSED'")->orderByRaw('deadline IS NULL')->orderBy('deadline')->latest('id')
            ->paginate($perPage)->withQueryString();
    }

    /** @return array<string, int> open count per box, for the inbox tabs */
    public function inboxCounts(int $userId): array
    {
        $counts = TaskPerson::query()->join('xlr8_utils_task as t', 't.id', '=', 'xlr8_utils_task_person.task_id')
            ->whereNull('t.deleted_at')->where('t.status', '!=', 'CLOSED')->where('xlr8_utils_task_person.user_id', $userId)
            ->groupBy('xlr8_utils_task_person.role')->selectRaw('xlr8_utils_task_person.role, count(*) as n')->pluck('n', 'role');

        return collect(self::BOXES)->map(fn ($role) => (int) ($counts[$role] ?? 0))->all();
    }

    /**
     * Task with the viewer's role, rights, people and deadline math (TSK-05). Unauthorised viewers
     * get `UNAUTHORISED` and no title or people (UC-TSK-6).
     */
    public function get(int $taskId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $task = Task::query()->with(['people.user', 'owner'])->find($taskId);
        if (! $task || ! $task->chatCanView((int) $actorId)) {
            return Result::fail('UNAUTHORISED', 'You cannot see this task.');
        }

        return Result::ok($this->dto($task, (int) $actorId));
    }

    /** Soft delete by the owner (TSK-08: chat and docs are kept). A closed task needs `$confirm`. */
    public function delete(int $taskId, ?int $actorId = null, bool $confirm = false): Result
    {
        $actorId ??= $this->actor();
        $task = Task::query()->find($taskId);
        if (! $task) {
            return Result::fail('NOT_FOUND', 'Task not found.');
        }
        if (! in_array('delete', $this->rights($task, (int) $actorId), true)) {
            return Result::fail('FORBIDDEN', 'Only the owner may delete this task.');
        }
        if ($task->status === 'CLOSED' && ! $confirm) {
            return Result::fail('CONFIRM_REQUIRED', 'This task is closed; confirm to delete it.');
        }
        $this->chat->event($task, 'DELETED', 'Task deleted', [], $actorId);
        $task->delete();
        TaskChanged::dispatch($task->id, 'DELETED', $actorId);

        return Result::ok();
    }

    /** The viewer's strongest role on the task, or null. */
    public function role(Task $task, int $userId): ?string
    {
        $roles = $this->rolesOf($task, $userId);
        foreach (self::ROLES as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * What the user may do (FRS §5.3): view, edit, delete, remark and `status:<TO>` entries.
     *
     * @return list<string>
     */
    public function rights(Task $task, int $userId): array
    {
        $roles = $this->rolesOf($task, $userId);
        if ($roles === []) {
            return [];
        }
        $rights = ['view'];
        $isOwner = in_array(self::OWNER, $roles, true);
        if ($isOwner) {
            array_push($rights, 'edit', 'delete');
        }
        if (array_intersect($roles, [self::OWNER, self::ASSIGNEE, self::FOLLOWER]) !== []) {
            $rights[] = 'remark';
        }
        foreach (self::TRANSITIONS as $to => $byRole) {
            foreach ($roles as $role) {
                if (in_array($task->status, $byRole[$role] ?? [], true)) {
                    $rights[] = 'status:'.$to;
                    break;
                }
            }
        }

        return array_values(array_unique($rights));
    }

    /** @return array<string, mixed> */
    public function dto(Task $task, int $viewerId): array
    {
        $task->loadMissing(['people.user', 'owner']);
        $deadline = $task->deadline;
        $people = collect(self::ROLES)->mapWithKeys(fn ($role) => [strtolower($role).'s' => $task->people->where('role', $role)
            ->map(fn (TaskPerson $p) => ['id' => $p->user_id, 'name' => $p->user?->display_name ?? '#'.$p->user_id])->values()->all()]);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'type' => $task->type,
            'type_label' => KeywordValueService::getEnum('TASK_TYPE')[$task->type] ?? $task->type,
            'priority' => $task->priority,
            'priority_label' => KeywordValueService::getEnum('TASK_PRIORITY')[$task->priority] ?? $task->priority,
            'status' => $task->status,
            'details' => $task->details,
            'deadline' => $deadline?->toIso8601String(),
            'is_overdue' => $deadline !== null && $task->status !== 'CLOSED' && $deadline->isPast(),
            'days_left' => $deadline ? (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false) : null,
            'age_days' => (int) $task->created_at?->diffInDays(now()),
            'is_group' => (bool) $task->is_group,
            'ref_type' => $task->ref_type,
            'ref_id' => $task->ref_id,
            'ref_url' => $this->notify->deepLink($task->ref_type, $task->ref_id ? (int) $task->ref_id : null),
            'owner' => ['id' => $task->owner_id, 'name' => $task->owner?->display_name],
            'people' => $people->all(),
            'user_role' => $this->role($task, $viewerId),
            'user_can' => $this->rights($task, $viewerId),
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }

    /** @return list<string> */
    private function rolesOf(Task $task, int $userId): array
    {
        $roles = $task->relationLoaded('people')
            ? $task->people->where('user_id', $userId)->pluck('role')->all()
            : TaskPerson::query()->where('task_id', $task->id)->where('user_id', $userId)->pluck('role')->all();

        return array_values(array_unique($roles));
    }

    /** @param array<string, mixed> $payload */
    private function validate(array $payload, int $ownerId): ?Result
    {
        if (trim(strip_tags((string) ($payload['title'] ?? ''))) === '') {
            return Result::fail('INVALID', 'A task needs a title.');
        }
        $types = KeywordValueService::getEnum('TASK_TYPE');
        if (! isset($types[strtoupper((string) ($payload['type'] ?? ''))])) {
            return Result::fail('INVALID_TYPE', 'Unknown task type.');
        }
        if (! empty($payload['priority']) && ! isset(KeywordValueService::getEnum('TASK_PRIORITY')[strtoupper((string) $payload['priority'])])) {
            return Result::fail('INVALID_PRIORITY', 'Unknown task priority.');
        }
        if (! User::query()->whereKey($ownerId)->exists()) {
            return Result::fail('INVALID_OWNER', 'The owner does not exist.');
        }
        if (! $this->isSelf((string) $payload['type']) && array_filter((array) ($payload['assignees'] ?? [])) === []) {
            return Result::fail('ASSIGNEE_REQUIRED', 'An assigned task needs at least one assignee.');
        }
        $ids = array_map('intval', array_merge((array) ($payload['assignees'] ?? []), (array) ($payload['followers'] ?? []), (array) ($payload['snoopers'] ?? [])));
        if ($ids !== [] && User::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
            return Result::fail('INVALID_PEOPLE', 'Some selected people do not exist.');
        }
        if (! empty($payload['deadline']) && rescue(fn () => Carbon::parse($payload['deadline']), null, false) === null) {
            return Result::fail('INVALID_DEADLINE', 'The deadline is not a valid date.');
        }

        return null;
    }

    /**
     * Role → user ids. SELF_TASK ignores the client's assignee list (TSK-02).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<int>>
     */
    private function people(array $payload, int $ownerId): array
    {
        $ids = fn (string $key) => array_values(array_unique(array_filter(array_map('intval', (array) ($payload[$key] ?? [])))));
        $assignees = $this->isSelf((string) $payload['type']) ? [$ownerId] : $ids('assignees');

        return [
            self::OWNER => [$ownerId],
            self::ASSIGNEE => $assignees,
            self::FOLLOWER => array_values(array_diff($ids('followers'), [$ownerId])),
            self::SNOOPER => array_values(array_diff($ids('snoopers'), [$ownerId])),
        ];
    }

    /** @return array<string, list<int>> current people by role */
    private function roster(Task $task): array
    {
        $rows = TaskPerson::query()->where('task_id', $task->id)->get(['user_id', 'role']);

        return collect(self::ROLES)->mapWithKeys(fn ($role) => [$role => $rows->where('role', $role)->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all()])->all();
    }

    /** @param array<string, list<int>> $people */
    private function writePeople(Task $task, array $people): void
    {
        $now = now();
        $rows = [];
        foreach ($people as $role => $ids) {
            foreach ($ids as $userId) {
                $rows[] = ['task_id' => $task->id, 'user_id' => $userId, 'role' => $role, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        TaskPerson::query()->insertOrIgnore($rows);
    }

    /** Owner, assignees and followers follow the conversation; snoopers read silently. */
    private function syncSubscriptions(Task $task, ?array $before = null): void
    {
        $now = $this->roster($task);
        $watchers = array_unique(array_merge($now[self::OWNER], $now[self::ASSIGNEE], $now[self::FOLLOWER]));
        foreach ($watchers as $userId) {
            $this->chat->subscribe($task, $userId);
        }
        if ($before !== null) {
            $was = array_unique(array_merge($before[self::OWNER], $before[self::ASSIGNEE], $before[self::FOLLOWER]));
            foreach (array_diff($was, $watchers) as $userId) {
                $this->chat->unsubscribe($task, (int) $userId);
            }
        }
    }

    /** @param list<int> $userIds */
    private function notifyRole(Task $task, string $role, array $userIds, ?int $actorId): void
    {
        if ($userIds === []) {
            return;
        }
        $pending = $this->notify->toMany($userIds)->kind('N')->about('TASK', $task->id)->actor($actorId)
            ->title(str_replace('{title}', $task->title, self::COPY[$role]))
            ->body($task->deadline ? 'Due '.$task->deadline->format('d-m-Y') : null)
            ->data(['role' => $role, 'priority' => $task->priority]);
        // the owner notification only makes sense when someone else created the task for them
        if ($role === self::OWNER && $actorId !== null && in_array($actorId, $userIds, true)) {
            return;
        }
        $pending->send();
    }

    private function notifyStatus(Task $task, string $from, string $to, ?int $actorId): void
    {
        $roster = $this->roster($task);
        $audience = match ($to) {
            'SUBMITTED' => $roster[self::OWNER],
            'CLOSED', 'REOPENED' => array_merge($roster[self::ASSIGNEE], $roster[self::FOLLOWER]),
            default => array_merge($roster[self::OWNER], $roster[self::FOLLOWER]),
        };
        $this->notify->toMany(array_values(array_unique($audience)))->kind('N')->about('TASK', $task->id)->actor($actorId)
            ->title("{actor} moved task \"{$task->title}\" to {$to}")->data(['from' => $from, 'to' => $to])->send();
    }

    private function isSelf(string $type): bool
    {
        return in_array(strtoupper($type), ['SELF_TASK', 'SELF'], true);
    }

    private function typeCode(string $type): string
    {
        return strtoupper(trim($type));
    }

    private function cleanHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return preg_replace('/<(\/?)(p|br|ul|ol|li|strong|em|b|i)\b[^>]*>/i', '<$1$2>', strip_tags($html, '<p><br><ul><ol><li><strong><em><b><i>'));
    }

    private function actor(): ?int
    {
        return auth(backpack_guard_name())->id() ?? auth()->id();
    }
}
