# 6. Tickets

Operational / IT / process incidents with a number, category, priority-driven SLA, a service desk and the same
people roles as tasks. Service `App\Services\Platform\Ticket\TicketService` · facade `Ticket`.

## Concepts
- **Number:** `TCK/{branch}/{fy}/{seq}` e.g. `TCK/JPR/26-27/00042` (branch = requester's primary branch, else `HO`).
- **Category** (KeyValue `TICKET_CATEGORY`): HARDWARE, ACCESS, DATA, BUG, ENHANCEMENT, PROCESS.
- **Priority** (KeyValue `TICKET_PRIORITY`): P1–P4 → SLA hours from Settings `sla.ticket.p1_hours` … `p4_hours`.
- **Roles:** Requester, Owner (desk lead), Assignee(s), Follower, Snooper. The **desk** = users with `UTL_TCKT_DESK`.
- **Status & legal moves:**
  - NEW → ACKNOWLEDGED / INPROGRESS
  - ACKNOWLEDGED / INPROGRESS / REOPENED → INPROGRESS / WAITING_USER / RESOLVED
  - WAITING_USER → INPROGRESS / RESOLVED
  - RESOLVED → CLOSED / REOPENED
  - CLOSED → REOPENED
  - The owner may force-close any open ticket, with a reason.
- **Who:** work states — owner, assignee or desk; RESOLVED — owner or assignee; CLOSED after RESOLVED — requester
  (or owner); REOPENED — requester or owner. The first to ACKNOWLEDGE becomes owner.
- **SLA:** due time set at open and on priority change; **WAITING_USER pauses the clock** (the paused time is added
  back); the hourly job flags breaches once (Alert to owner, assignees, desk); resolved tickets auto-close after
  `ticket.autoclose_days` when `ticket.autoclose_enabled`.

## API
| Call | Returns |
|---|---|
| `Ticket::open($payload, $actorId = null)` | Result `{id, number}` — `INVALID`, `INVALID_CATEGORY`, `INVALID_PRIORITY`, `INVALID_REQUESTER`, `INVALID_PEOPLE` |
| `Ticket::transition($id, $actorId, $to, $remark = null)` | Result — `FORBIDDEN_TRANSITION`, `REASON_REQUIRED` |
| `Ticket::update($id, ['priority','category','owner_id','assignees','followers','snoopers','title'], $actorId)` | Result — owner / desk |
| `Ticket::remark($id, $actorId, $body, $file = null)` | Result (snoopers cannot) |
| `Ticket::inbox($userId, 'REQUESTED'|'ASSIGNED'|'FOLLOWED'|'SNOOPED'|'QUEUE', $filters)` | paginator (QUEUE = NEW, unassigned, desk only) |
| `Ticket::get($id, $viewerId)` | Result DTO incl. `sla`, `user_roles`, `user_can[]` — `UNAUTHORISED` |
| `Ticket::sla($ticket)` | `{state: ok|due_soon|breached|paused|done, due_at, minutes_left, paused}` |
| `Ticket::report(['from','to','branch'])` | open by category / priority, breached, resolved, mean time to resolve |

Payload for `open`: `title`, `category`, `priority`, `details`, `requester_id` (default actor), `owner_id`,
`assignees[]`, `followers[]`, `snoopers[]`, `ref_type`, `ref_id`.

## Use cases

**1. A screen offers "Report a problem" for the current record**
```blade
<a href="{{ route('utils.tickets.create', ['ref_type' => 'QUOTE', 'ref_id' => $quote->id]) }}" class="btn btn-outline-secondary">Report a problem</a>
```

**2. Code opens a ticket** (e.g. a failing integration)
```php
Ticket::open(['title' => 'DMS sync failed for 12 bookings', 'category' => 'DATA', 'priority' => 'P2',
              'details' => $summary, 'requester_id' => $systemUserId, 'ref_type' => 'BOOKING', 'ref_id' => $firstId]);
```

**3. Desk takes and routes it**
```php
Ticket::transition($id, $deskUserId, 'ACKNOWLEDGED', 'Taken');
Ticket::update($id, ['priority' => 'P1', 'category' => 'ACCESS', 'owner_id' => $deskUserId, 'assignees' => [$itUserId]], $deskUserId);
```

**4. Wait for the requester** (clock pauses; requester gets an Alert)
```php
Ticket::transition($id, $itUserId, 'WAITING_USER', 'Please send a screenshot');
```

**5. SLA badge anywhere** `<x-ticket.sla-badge :ticket="$ticket" />` · inbox `<x-ticket.inbox box="QUEUE" />`

## Screens & permissions
**Utilities → Tickets** `/admin/utils/tickets` (+ desk queue), `/tickets/create`, `/tickets/{id}`, `/tickets/report`.
Everyone `UTL_TCKT_VIEW` / `UTL_TCKT_CREATE`; desk `UTL_TCKT_DESK`; report `UTL_TCKT_REPORT`.
Jobs: `FlagTicketSlaBreaches` (hourly), `AutoCloseResolvedTickets` (03:00).

## Gotchas
- Tickets are not tasks: don't use Task types or the TASK entity for tickets.
- Changing priority recomputes the due time and clears the breach flag.
