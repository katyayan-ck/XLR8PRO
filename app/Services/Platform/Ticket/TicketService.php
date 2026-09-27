<?php

declare(strict_types=1);

namespace App\Services\Platform\Ticket;

use App\Events\Platform\TicketChanged;
use App\Models\User;
use App\Models\Utilities\Ticket\Ticket;
use App\Models\Utilities\Ticket\TicketPerson;
use App\Services\KeywordValueService;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ticket system (FRS §6) — the only writer of ticket / ticket_person / ticket_counter.
 * Number TCK/{branch}/{fy}/{seq}; status NEW → ACKNOWLEDGED → INPROGRESS → WAITING_USER → RESOLVED →
 * CLOSED / REOPENED; SLA from `sla.ticket.p{n}_hours`, paused while WAITING_USER. The desk is
 * everyone holding UTL_TCKT_DESK. Every change: persist → Chat → Notify → event.
 */
final class TicketService
{
    public const REQUESTER = 'REQUESTER';

    public const OWNER = 'OWNER';

    public const ASSIGNEE = 'ASSIGNEE';

    public const FOLLOWER = 'FOLLOWER';

    public const SNOOPER = 'SNOOPER';

    public const ROLES = [self::REQUESTER, self::OWNER, self::ASSIGNEE, self::FOLLOWER, self::SNOOPER];

    public const STATUSES = ['NEW', 'ACKNOWLEDGED', 'INPROGRESS', 'WAITING_USER', 'RESOLVED', 'CLOSED', 'REOPENED'];

    public const OPEN = ['NEW', 'ACKNOWLEDGED', 'INPROGRESS', 'WAITING_USER', 'REOPENED'];

    public const BOXES = ['REQUESTED' => self::REQUESTER, 'ASSIGNED' => self::ASSIGNEE, 'FOLLOWED' => self::FOLLOWER, 'SNOOPED' => self::SNOOPER, 'QUEUE' => null];

    /** Legal edges (FRS §6.2, DEC-062). */
    private const EDGES = [
        'NEW' => ['ACKNOWLEDGED', 'INPROGRESS'],
        'ACKNOWLEDGED' => ['INPROGRESS', 'WAITING_USER', 'RESOLVED'],
        'INPROGRESS' => ['WAITING_USER', 'RESOLVED'],
        'WAITING_USER' => ['INPROGRESS', 'RESOLVED'],
        'REOPENED' => ['ACKNOWLEDGED', 'INPROGRESS', 'WAITING_USER', 'RESOLVED'],
        'RESOLVED' => ['CLOSED', 'REOPENED'],
        'CLOSED' => ['REOPENED'],
    ];

    public function __construct(
        private readonly ChatService $chat,
        private readonly NotifyService $notify,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Open a ticket (TCK-01): number, SLA, Chat CREATED, Notify desk + assignees.
     *
     * @param  array{category?: string, priority?: string, title?: string, details?: ?string, requester_id?: int, owner_id?: ?int, assignees?: list<int>, followers?: list<int>, snoopers?: list<int>, ref_type?: ?string, ref_id?: ?int}  $payload
     */
    public function open(array $payload, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $requesterId = (int) ($payload['requester_id'] ?? $actorId);
        $category = strtoupper((string) ($payload['category'] ?? ''));
        $priority = strtoupper((string) ($payload['priority'] ?? 'P3'));
        $title = mb_substr(trim(strip_tags((string) ($payload['title'] ?? ''))), 0, 250);

        if ($title === '') {
            return Result::fail('INVALID', 'A ticket needs a title.');
        }
        if (! isset(KeywordValueService::getEnum('TICKET_CATEGORY')[$category])) {
            return Result::fail('INVALID_CATEGORY', 'Unknown ticket category.');
        }
        if (! isset(KeywordValueService::getEnum('TICKET_PRIORITY')[$priority])) {
            return Result::fail('INVALID_PRIORITY', 'Unknown ticket priority.');
        }
        $requester = User::query()->with('employee')->find($requesterId);
        if (! $requester) {
            return Result::fail('INVALID_REQUESTER', 'The requester does not exist.');
        }
        $people = $this->people($payload);
        if (($error = $this->peopleError($people, $payload['owner_id'] ?? null)) !== null) {
            return $error;
        }

        $ticket = DB::transaction(function () use ($payload, $requester, $category, $priority, $title, $people) {
            $branch = strtoupper((string) ($requester->employee?->primary_branch_code ?: 'HO'));
            $fy = $this->fy(now());
            $seq = $this->nextSeq($branch, $fy);
            $ticket = Ticket::create([
                'number' => sprintf('TCK/%s/%s/%05d', $branch, $fy, $seq),
                'branch_code' => $branch, 'fy' => $fy, 'seq' => $seq,
                'category' => $category, 'priority' => $priority, 'status' => 'NEW', 'title' => $title,
                'details' => $this->cleanHtml($payload['details'] ?? null),
                'requester_id' => $requester->id,
                'owner_id' => $payload['owner_id'] ?? null,
                'ref_type' => isset($payload['ref_type']) ? strtoupper((string) $payload['ref_type']) : null,
                'ref_id' => $payload['ref_id'] ?? null,
                'due_at' => now()->addHours($this->slaHours($priority)),
            ]);
            $this->writePeople($ticket, [self::REQUESTER => [$requester->id], self::OWNER => array_filter([(int) ($payload['owner_id'] ?? 0)])] + $people);

            return $ticket;
        });

        $this->chat->event($ticket, 'CREATED', "Ticket {$ticket->number} opened: {$ticket->title}", ['priority' => $priority, 'category' => $category], $actorId);
        if ($ticket->ref_type && $ticket->ref_id && ($parent = $this->chat->resolve($ticket->ref_type, (int) $ticket->ref_id))) {
            $this->chat->event($parent, 'TICKET_OPENED', "Ticket {$ticket->number}: {$ticket->title}", ['ticket_id' => $ticket->id], $actorId);
        }
        $this->syncSubscriptions($ticket);

        $desk = $this->deskUserIds();
        $this->notify->toMany($desk)->kind($priority === 'P1' ? 'A' : 'N')->about('TICKET', $ticket->id)->actor($actorId)
            ->title("New {$priority} ticket {$ticket->number}: {$ticket->title}")->data(['severity' => $priority === 'P1' ? 'critical' : 'warning'])->send();
        $this->notify->toMany(array_values(array_diff($people[self::ASSIGNEE], $desk)))->kind('N')->about('TICKET', $ticket->id)->actor($actorId)
            ->title("{actor} assigned you ticket {$ticket->number}: {$ticket->title}")->send();
        TicketChanged::dispatch($ticket->id, 'CREATED', $actorId);

        return Result::ok(['id' => $ticket->id, 'number' => $ticket->number]);
    }

    /**
     * Move a ticket (TCK-02) along a legal edge by a permitted role. RESOLVED only by owner /
     * assignee; CLOSED from RESOLVED by the requester (or owner); force-close by the owner with a reason.
     */
    public function transition(int $ticketId, ?int $actorId, string $to, ?string $remark = null): Result
    {
        $actorId ??= $this->actor();
        $to = strtoupper($to);
        $ticket = Ticket::query()->find($ticketId);
        if (! $ticket) {
            return Result::fail('NOT_FOUND', 'Ticket not found.');
        }
        if (! in_array('status:'.$to, $this->rights($ticket, (int) $actorId), true)) {
            return Result::fail('FORBIDDEN_TRANSITION', "You cannot move this ticket from {$ticket->status} to {$to}.");
        }
        $force = $to === 'CLOSED' && $ticket->status !== 'RESOLVED';
        if ($force && trim((string) $remark) === '') {
            return Result::fail('REASON_REQUIRED', 'Give a reason to force-close this ticket.');
        }

        $from = $ticket->status;
        $changes = ['status' => $to];
        if ($from === 'WAITING_USER' && $ticket->sla_paused_at) {
            $paused = (int) $ticket->sla_paused_at->diffInMinutes(now());
            $changes += ['sla_paused_at' => null, 'sla_paused_minutes' => $ticket->sla_paused_minutes + $paused, 'due_at' => $ticket->due_at?->copy()->addMinutes($paused)];
        }
        $changes += match ($to) {
            'ACKNOWLEDGED' => ['acknowledged_at' => $ticket->acknowledged_at ?? now()],
            'WAITING_USER' => ['sla_paused_at' => now()],
            'RESOLVED' => ['resolved_at' => now()],
            'CLOSED' => ['closed_at' => now(), 'close_reason' => $force ? mb_substr((string) $remark, 0, 500) : null],
            'REOPENED' => ['resolved_at' => null, 'closed_at' => null, 'close_reason' => null],
            default => [],
        };
        if ($to === 'ACKNOWLEDGED' && ! $ticket->owner_id) {
            $changes['owner_id'] = $actorId;
            TicketPerson::query()->insertOrIgnore(['ticket_id' => $ticket->id, 'user_id' => $actorId, 'role' => self::OWNER, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ticket->update($changes);

        $this->chat->event($ticket, 'STATUS_CHANGED', "Status {$from} → {$to}".($force ? ' (force-closed)' : ''), ['from' => $from, 'to' => $to], $actorId);
        if (trim((string) $remark) !== '') {
            $this->chat->remark($ticket, (string) $remark, null, null, false, $actorId);
        }
        $this->syncSubscriptions($ticket);
        $this->notifyTransition($ticket, $from, $to, $actorId);
        TicketChanged::dispatch($ticket->id, $to, $actorId);

        return Result::ok(['id' => $ticket->id, 'status' => $to]);
    }

    /**
     * Desk / owner changes priority, category, owner and people. A priority change recomputes the
     * SLA from the open time plus paused time (TCK-03).
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $ticketId, array $payload, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $ticket = Ticket::query()->find($ticketId);
        if (! $ticket) {
            return Result::fail('NOT_FOUND', 'Ticket not found.');
        }
        if (! in_array('manage', $this->rights($ticket, (int) $actorId), true)) {
            return Result::fail('FORBIDDEN', 'Only the owner or the service desk may change this ticket.');
        }
        $priority = strtoupper((string) ($payload['priority'] ?? $ticket->priority));
        $category = strtoupper((string) ($payload['category'] ?? $ticket->category));
        if (! isset(KeywordValueService::getEnum('TICKET_PRIORITY')[$priority]) || ! isset(KeywordValueService::getEnum('TICKET_CATEGORY')[$category])) {
            return Result::fail('INVALID', 'Unknown priority or category.');
        }
        $people = $this->people($payload);
        $ownerId = array_key_exists('owner_id', $payload) ? ((int) $payload['owner_id'] ?: null) : $ticket->owner_id;
        if (($error = $this->peopleError($people, $ownerId)) !== null) {
            return $error;
        }
        $before = $this->roster($ticket);

        DB::transaction(function () use ($ticket, $priority, $category, $ownerId, $people, $payload) {
            $changes = ['priority' => $priority, 'category' => $category, 'owner_id' => $ownerId];
            if (isset($payload['title']) && trim((string) $payload['title']) !== '') {
                $changes['title'] = mb_substr(trim(strip_tags((string) $payload['title'])), 0, 250);
            }
            if ($priority !== $ticket->priority) {
                $changes['due_at'] = $ticket->created_at->copy()->addHours($this->slaHours($priority))->addMinutes($ticket->sla_paused_minutes);
                $changes['breached_at'] = null;
            }
            $ticket->update($changes);
            TicketPerson::query()->where('ticket_id', $ticket->id)->where('role', '!=', self::REQUESTER)->delete();
            $this->writePeople($ticket, [self::OWNER => array_filter([(int) $ownerId])] + $people);
        });

        $this->chat->event($ticket, 'UPDATED', "Ticket updated ({$priority}, {$category})", [], $actorId);
        $this->syncSubscriptions($ticket, $before);
        $after = $this->roster($ticket);
        $added = array_values(array_diff(array_merge($after[self::OWNER], $after[self::ASSIGNEE]), array_merge($before[self::OWNER], $before[self::ASSIGNEE])));
        $this->notify->toMany($added)->kind('N')->about('TICKET', $ticket->id)->actor($actorId)
            ->title("{actor} assigned you ticket {$ticket->number}: {$ticket->title}")->send();
        TicketChanged::dispatch($ticket->id, 'UPDATED', $actorId);

        return Result::ok(['id' => $ticket->id]);
    }

    /** A remark on the ticket's conversation (snoopers cannot post). */
    public function remark(int $ticketId, ?int $actorId, string $body, $file = null): Result
    {
        $actorId ??= $this->actor();
        $ticket = Ticket::query()->find($ticketId);
        if (! $ticket) {
            return Result::fail('NOT_FOUND', 'Ticket not found.');
        }

        return $this->chat->remark($ticket, $body, $file, null, false, $actorId);
    }

    /** Inboxes (TCK-04): REQUESTED, ASSIGNED (assignee or owner), FOLLOWED, SNOOPED, QUEUE (desk). */
    public function inbox(int $userId, string $box, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $box = strtoupper($box);
        $query = Ticket::query()->with('requester:id,username,person_code');
        if ($box === 'QUEUE') {
            $isDesk = (bool) User::query()->find($userId)?->can('UTL_TCKT_DESK');
            $query->where('status', 'NEW')->whereNotIn('id', TicketPerson::query()->where('role', self::ASSIGNEE)->select('ticket_id'));
            if (! $isDesk) {
                $query->whereRaw('1 = 0');
            }
        } else {
            $roles = $box === 'ASSIGNED' ? [self::ASSIGNEE, self::OWNER] : [self::BOXES[$box] ?? self::REQUESTER];
            $query->whereIn('id', TicketPerson::query()->where('user_id', $userId)->whereIn('role', $roles)->select('ticket_id'));
        }

        return $query
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', strtoupper($filters['status'])))
            ->when(($filters['category'] ?? '') !== '', fn ($q) => $q->where('category', strtoupper($filters['category'])))
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$filters['q'].'%')->orWhere('number', 'like', '%'.$filters['q'].'%')))
            ->orderByRaw("status IN ('RESOLVED','CLOSED')")->orderBy('due_at')->latest('id')
            ->paginate($perPage)->withQueryString();
    }

    /** @return array<string, int> open count per box */
    public function inboxCounts(int $userId): array
    {
        $counts = [];
        foreach (array_keys(self::BOXES) as $box) {
            $counts[$box] = $this->inbox($userId, $box, [], 1)->total();
        }

        return $counts;
    }

    /** Ticket with the viewer's roles, rights, people and SLA (unauthorised → UNAUTHORISED, no data). */
    public function get(int $ticketId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $ticket = Ticket::query()->with(['people.user', 'requester', 'owner'])->find($ticketId);
        if (! $ticket || ! $this->canView($ticket, (int) $actorId)) {
            return Result::fail('UNAUTHORISED', 'You cannot see this ticket.');
        }

        return Result::ok($this->dto($ticket, (int) $actorId));
    }

    public function canView(Ticket $ticket, int $userId): bool
    {
        if ($this->rolesOf($ticket, $userId) !== []) {
            return true;
        }
        $user = User::query()->find($userId);

        return (bool) ($user?->can('UTL_TCKT_DESK') || $user?->can('UTL_TCKT_REPORT'));
    }

    /**
     * Rights: view, remark, manage (owner / desk) and `status:<TO>` entries for legal edges.
     *
     * @return list<string>
     */
    public function rights(Ticket $ticket, int $userId): array
    {
        if (! $this->canView($ticket, $userId)) {
            return [];
        }
        $roles = $this->rolesOf($ticket, $userId);
        $isDesk = (bool) User::query()->find($userId)?->can('UTL_TCKT_DESK');
        $isOwner = in_array(self::OWNER, $roles, true);
        $isAssignee = in_array(self::ASSIGNEE, $roles, true);
        $isRequester = in_array(self::REQUESTER, $roles, true);
        $worker = $isOwner || $isAssignee || $isDesk;

        $rights = ['view'];
        if ($roles !== [self::SNOOPER] && ($roles !== [] || $isDesk)) {
            $rights[] = 'remark';
        }
        if ($isOwner || $isDesk) {
            $rights[] = 'manage';
        }
        foreach (self::EDGES[$ticket->status] ?? [] as $to) {
            $allowed = match ($to) {
                'RESOLVED' => $isOwner || $isAssignee,
                'CLOSED' => $isRequester || $isOwner,
                'REOPENED' => $isRequester || $isOwner,
                default => $worker,
            };
            if ($allowed) {
                $rights[] = 'status:'.$to;
            }
        }
        if (in_array($ticket->status, self::OPEN, true) && $isOwner) {
            $rights[] = 'status:CLOSED';
        }

        return array_values(array_unique($rights));
    }

    /**
     * Flag breached tickets once (TCK-03): Alert owner, assignees and desk; Chat event.
     */
    public function flagBreaches(): int
    {
        $count = 0;
        Ticket::query()->whereIn('status', ['NEW', 'ACKNOWLEDGED', 'INPROGRESS', 'REOPENED'])
            ->whereNull('breached_at')->whereNotNull('due_at')->where('due_at', '<', now())
            ->chunkById(100, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $ticket->update(['breached_at' => now()]);
                    $this->chat->event($ticket, 'SLA_BREACHED', "SLA breached (due {$ticket->due_at->format('d-m-Y H:i')})", [], null);
                    $roster = $this->roster($ticket);
                    $audience = array_values(array_unique(array_merge($roster[self::OWNER], $roster[self::ASSIGNEE], $this->deskUserIds())));
                    $this->notify->toMany($audience)->kind('A')->about('TICKET', $ticket->id)->actor(null)->notifySelf()
                        ->title("SLA breached: {$ticket->number} {$ticket->title}")->data(['severity' => 'critical'])
                        ->idempotency("ticket-breach-{$ticket->id}-{$ticket->due_at->timestamp}")->send();
                    TicketChanged::dispatch($ticket->id, 'SLA_BREACHED', null);
                    $count++;
                }
            });

        return $count;
    }

    /** Auto-close RESOLVED tickets after `ticket.autoclose_days` (TCK-06, behind `ticket.autoclose_enabled`). */
    public function autoClose(): int
    {
        if (! $this->settings->flag('ticket.autoclose_enabled')) {
            return 0;
        }
        $days = max(1, (int) $this->settings->get('ticket.autoclose_days', 3));
        $count = 0;
        Ticket::query()->where('status', 'RESOLVED')->where('resolved_at', '<', now()->subDays($days))
            ->chunkById(100, function ($tickets) use (&$count, $days) {
                foreach ($tickets as $ticket) {
                    $ticket->update(['status' => 'CLOSED', 'closed_at' => now()]);
                    $this->chat->event($ticket, 'AUTO_CLOSED', "Closed automatically after {$days} day(s) without a reply", [], null);
                    $this->notify->to((int) $ticket->requester_id)->kind('N')->about('TICKET', $ticket->id)->actor(null)->notifySelf()
                        ->title("Ticket {$ticket->number} was closed automatically")->send();
                    TicketChanged::dispatch($ticket->id, 'CLOSED', null);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Report (TCK-08): open by category, breached, mean time to resolve (hours).
     *
     * @param  array{from?: string, to?: string, branch?: string}  $filters
     * @return array{open_by_category: array<string, int>, open: int, breached_open: int, breached_total: int, resolved: int, mttr_hours: ?float, by_priority: array<string, int>}
     */
    public function report(array $filters = []): array
    {
        $base = fn () => Ticket::query()
            ->when(($filters['from'] ?? '') !== '', fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(($filters['to'] ?? '') !== '', fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->when(($filters['branch'] ?? '') !== '', fn ($q) => $q->where('branch_code', strtoupper($filters['branch'])));

        $resolved = $base()->whereNotNull('resolved_at')->get(['created_at', 'resolved_at', 'sla_paused_minutes']);
        $mttr = $resolved->isEmpty() ? null : round($resolved->avg(fn ($t) => max(0, $t->created_at->diffInMinutes($t->resolved_at) - $t->sla_paused_minutes)) / 60, 1);

        return [
            'open_by_category' => $base()->whereIn('status', self::OPEN)->groupBy('category')->selectRaw('category, count(*) n')->pluck('n', 'category')->map(fn ($n) => (int) $n)->all(),
            'open' => $base()->whereIn('status', self::OPEN)->count(),
            'breached_open' => $base()->whereIn('status', self::OPEN)->whereNotNull('breached_at')->count(),
            'breached_total' => $base()->whereNotNull('breached_at')->count(),
            'resolved' => $resolved->count(),
            'mttr_hours' => $mttr,
            'by_priority' => $base()->whereIn('status', self::OPEN)->groupBy('priority')->selectRaw('priority, count(*) n')->pluck('n', 'priority')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function dto(Ticket $ticket, int $viewerId): array
    {
        $ticket->loadMissing(['people.user', 'requester', 'owner']);
        $people = collect(self::ROLES)->mapWithKeys(fn ($role) => [strtolower($role).'s' => $ticket->people->where('role', $role)
            ->map(fn (TicketPerson $p) => ['id' => $p->user_id, 'name' => $p->user?->display_name ?? '#'.$p->user_id])->values()->all()]);

        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
            'details' => $ticket->details,
            'category' => $ticket->category,
            'category_label' => KeywordValueService::getEnum('TICKET_CATEGORY')[$ticket->category] ?? $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'sla' => $this->sla($ticket),
            'ref_type' => $ticket->ref_type,
            'ref_id' => $ticket->ref_id,
            'ref_url' => $this->notify->deepLink($ticket->ref_type, $ticket->ref_id ? (int) $ticket->ref_id : null),
            'requester' => ['id' => $ticket->requester_id, 'name' => $ticket->requester?->display_name],
            'owner' => $ticket->owner_id ? ['id' => $ticket->owner_id, 'name' => $ticket->owner?->display_name] : null,
            'people' => $people->all(),
            'close_reason' => $ticket->close_reason,
            'user_roles' => $this->rolesOf($ticket, $viewerId),
            'user_can' => $this->rights($ticket, $viewerId),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }

    /**
     * SLA state for the badge: ok / due_soon / breached / paused / done, minutes left.
     *
     * @return array{state: string, due_at: ?string, minutes_left: ?int, paused: bool}
     */
    public function sla(Ticket $ticket): array
    {
        $paused = $ticket->status === 'WAITING_USER';
        if (in_array($ticket->status, ['RESOLVED', 'CLOSED'], true) || ! $ticket->due_at) {
            return ['state' => 'done', 'due_at' => $ticket->due_at?->toIso8601String(), 'minutes_left' => null, 'paused' => false];
        }
        $reference = $paused && $ticket->sla_paused_at ? $ticket->sla_paused_at : now();
        $left = (int) $reference->diffInMinutes($ticket->due_at, false);
        $total = max(1, $this->slaHours($ticket->priority) * 60);
        $state = $paused ? 'paused' : ($left < 0 ? 'breached' : ($left <= $total * 0.25 ? 'due_soon' : 'ok'));

        return ['state' => $state, 'due_at' => $ticket->due_at->toIso8601String(), 'minutes_left' => $left, 'paused' => $paused];
    }

    public function slaHours(string $priority): int
    {
        $n = (int) filter_var($priority, FILTER_SANITIZE_NUMBER_INT) ?: 3;

        return max(1, (int) $this->settings->get("sla.ticket.p{$n}_hours", [1 => 4, 2 => 8, 3 => 24, 4 => 72][$n] ?? 24));
    }

    /** @return list<int> users holding the service desk permission */
    public function deskUserIds(): array
    {
        return User::permission('UTL_TCKT_DESK')->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Indian financial year label, e.g. 26-27 for 1 Apr 2026 – 31 Mar 2027. */
    public function fy(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    private function nextSeq(string $branch, string $fy): int
    {
        DB::table('xlr8_utils_ticket_counter')->insertOrIgnore(['branch_code' => $branch, 'fy' => $fy, 'last_seq' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('xlr8_utils_ticket_counter')->where('branch_code', $branch)->where('fy', $fy)->lockForUpdate()->first();
        $next = (int) $row->last_seq + 1;
        DB::table('xlr8_utils_ticket_counter')->where('id', $row->id)->update(['last_seq' => $next, 'updated_at' => now()]);

        return $next;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<int>>
     */
    private function people(array $payload): array
    {
        $ids = fn (string $key) => array_values(array_unique(array_filter(array_map('intval', (array) ($payload[$key] ?? [])))));

        return [self::ASSIGNEE => $ids('assignees'), self::FOLLOWER => $ids('followers'), self::SNOOPER => $ids('snoopers')];
    }

    /** @param array<string, list<int>> $people */
    private function peopleError(array $people, mixed $ownerId): ?Result
    {
        $ids = array_merge(...array_values($people));
        if ($ownerId) {
            $ids[] = (int) $ownerId;
        }
        $ids = array_unique($ids);
        if ($ids !== [] && User::query()->whereIn('id', $ids)->count() !== count($ids)) {
            return Result::fail('INVALID_PEOPLE', 'Some selected people do not exist.');
        }

        return null;
    }

    /** @return list<string> */
    private function rolesOf(Ticket $ticket, int $userId): array
    {
        $roles = $ticket->relationLoaded('people')
            ? $ticket->people->where('user_id', $userId)->pluck('role')->all()
            : TicketPerson::query()->where('ticket_id', $ticket->id)->where('user_id', $userId)->pluck('role')->all();

        return array_values(array_unique($roles));
    }

    /** @return array<string, list<int>> */
    private function roster(Ticket $ticket): array
    {
        $rows = TicketPerson::query()->where('ticket_id', $ticket->id)->get(['user_id', 'role']);

        return collect(self::ROLES)->mapWithKeys(fn ($role) => [$role => $rows->where('role', $role)->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all()])->all();
    }

    /** @param array<string, list<int>> $people */
    private function writePeople(Ticket $ticket, array $people): void
    {
        $now = now();
        $rows = [];
        foreach ($people as $role => $ids) {
            foreach ($ids as $userId) {
                $rows[] = ['ticket_id' => $ticket->id, 'user_id' => (int) $userId, 'role' => $role, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        TicketPerson::query()->insertOrIgnore($rows);
        $ticket->unsetRelation('people');
    }

    /** Requester, owner, assignees and followers follow the conversation; snoopers read silently. */
    private function syncSubscriptions(Ticket $ticket, ?array $before = null): void
    {
        $roster = $this->roster($ticket);
        $watchers = array_unique(array_merge($roster[self::REQUESTER], $roster[self::OWNER], $roster[self::ASSIGNEE], $roster[self::FOLLOWER]));
        foreach ($watchers as $userId) {
            $this->chat->subscribe($ticket, $userId);
        }
        if ($before !== null) {
            $was = array_unique(array_merge($before[self::REQUESTER], $before[self::OWNER], $before[self::ASSIGNEE], $before[self::FOLLOWER]));
            foreach (array_diff($was, $watchers) as $userId) {
                $this->chat->unsubscribe($ticket, (int) $userId);
            }
        }
    }

    private function notifyTransition(Ticket $ticket, string $from, string $to, ?int $actorId): void
    {
        $roster = $this->roster($ticket);
        $requester = $roster[self::REQUESTER];
        $workers = array_values(array_unique(array_merge($roster[self::OWNER], $roster[self::ASSIGNEE])));

        match ($to) {
            // TCK-05: waiting on the requester → Alert, SLA paused
            'WAITING_USER' => $this->notify->toMany($requester)->kind('A')->about('TICKET', $ticket->id)->actor($actorId)
                ->title("Ticket {$ticket->number} is waiting for your reply")->data(['severity' => 'warning'])->send(),
            // TCK-06: resolved → please confirm
            'RESOLVED' => $this->notify->toMany($requester)->kind('N')->about('TICKET', $ticket->id)->actor($actorId)
                ->title("Ticket {$ticket->number} is resolved — please confirm or reopen")->send(),
            'REOPENED' => $this->notify->toMany($workers)->kind('N')->about('TICKET', $ticket->id)->actor($actorId)
                ->title("{actor} reopened ticket {$ticket->number}")->send(),
            default => $this->notify->toMany(array_values(array_unique(array_merge($requester, $workers))))->kind('N')->about('TICKET', $ticket->id)->actor($actorId)
                ->title("Ticket {$ticket->number}: {$from} → {$to}")->send(),
        };
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
