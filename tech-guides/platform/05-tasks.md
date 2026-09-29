# 5. Tasks

"Someone must do X by a date": owner, assignees, followers and snoopers, a status flow, follow-ups with
remarks and files, four inboxes. Service `App\Services\Platform\Task\TaskService` · facade `Task`.

## Concepts
- **Types** (KeyValue `TASK_TYPE`): `ASSIGNED_TASK` (needs ≥1 assignee), `SELF_TASK` (assignee = owner, whatever is sent).
- **Priority** (KeyValue `TASK_PRIORITY`): `LOW`, `NORMAL`, `HIGH`.
- **Roles:** Owner · Assignee(s) · Follower (may remark) · Snooper (reads silently). More than one assignee = group task.
- **Status:** `FRESH → INPROGRESS / HOLD → SUBMITTED → CLOSED ↔ REOPENED`.
- **Rights matrix (FRS §5.3):**

| Action | Owner | Assignee | Follower | Snooper |
|---|---|---|---|---|
| view | ✓ | ✓ | ✓ | ✓ |
| edit header / people, delete | ✓ | | | |
| remark | ✓ | ✓ | ✓ | |
| INPROGRESS / HOLD | ✓ | ✓ | | |
| SUBMITTED | | ✓ | | |
| CLOSED | ✓ | | | |
| REOPENED (from CLOSED or SUBMITTED) | ✓ | | | |

- Every create / change writes a Chat event on the task (and `TASK_CREATED` on the linked record) and notifies the
  right people; owner, assignees and followers follow the task's conversation.

## API
| Call | Returns |
|---|---|
| `Task::create($payload, $actorId = null)` | Result `{id}` — `INVALID`, `INVALID_TYPE`, `INVALID_PRIORITY`, `INVALID_OWNER`, `ASSIGNEE_REQUIRED`, `INVALID_PEOPLE`, `INVALID_DEADLINE` |
| `Task::update($taskId, $payload)` | Result — owner only (`FORBIDDEN`); people rebuilt, added/removed notified |
| `Task::followUp($taskId, $actorId, $remark, $status = null, $file = null)` | Result — `FORBIDDEN`, `FORBIDDEN_TRANSITION`, `EMPTY` |
| `Task::inbox($userId, 'CREATED'|'ASSIGNED'|'FOLLOWED'|'SNOOPED', ['status' =>, 'q' =>, 'open' => true])` | paginator of `Task` models (closed last, then by deadline) |
| `Task::inboxCounts($userId)` | open count per box |
| `Task::get($taskId, $viewerId)` | Result DTO incl. `user_role`, `user_can[]`, deadline math — `UNAUTHORISED` (no data) |
| `Task::delete($taskId, $actorId, $confirm = false)` | Result — owner; a CLOSED task needs `$confirm` |
| `Task::rights($task, $userId)` | e.g. `['view','remark','status:SUBMITTED']` |

Payload: `title`, `type`, `priority`, `owner_id` (default actor), `assignees[]`, `followers[]`, `snoopers[]`,
`details` (simple HTML), `deadline` (date or date-time), `ref_type`, `ref_id`.

## Use cases

**1. A module asks someone to follow up** (task hangs off the record)
```php
Task::create([
    'title' => 'Collect PAN copy', 'type' => 'ASSIGNED_TASK', 'priority' => 'HIGH',
    'assignees' => [$fscUserId], 'followers' => [$smUserId],
    'deadline' => now()->addDays(2)->toDateString(),
    'ref_type' => 'BOOKING', 'ref_id' => $booking->id,
]);
```

**2. Personal reminder**
```php
Task::create(['title' => 'Call financier', 'type' => 'SELF_TASK', 'deadline' => '2026-10-01 11:00']);
```

**3. Assignee updates status with a note and a photo**
```php
$r = Task::followUp($taskId, $userId, 'Visited customer', 'INPROGRESS', $request->file('photo'));
if ($r->code === 'FORBIDDEN_TRANSITION') { /* show $r->message */ }
```

**4. Offer only the allowed buttons**
```php
$task = Task::get($id, backpack_user()->id)->data;       // $task['user_can']
@if (in_array('status:CLOSED', $task['user_can'], true)) … @endif
```
or just `<x-task.composer :task="$task" />` (remark + allowed statuses + upload).

**5. Show someone's work on a dashboard**
```blade
<x-task.inbox box="ASSIGNED" :limit="5" />
```

**6. Hand a task to someone else / add a follower** (owner). People are rebuilt from the payload, so send the full lists:
```php
Task::update($taskId, ['assignees' => [$newUserId], 'followers' => [$smUserId]], $ownerId);   // removed people are told and lose it
```

**7. Tasks for one record** (e.g. a booking side panel)
```php
$open = Task::inbox($userId, 'ASSIGNED', ['open' => true]);     // the user's view
// all tasks of a record, read-only: App\Models\Utilities\Task\Task::where('ref_type', 'BOOKING')->where('ref_id', $id)->get()
```

**8. React when work is submitted**
```php
Event::listen(TaskChanged::class, function ($e) { if ($e->change === 'SUBMITTED') { /* notify QA */ } });
```

## Events & testing
`TaskChanged` (`CREATED`, `UPDATED`, `DELETED`, `REMARKED`, or the new status). Test the rights with
`Task::get($id, $userId)->get('user_can')`: see [15-testing.md](15-testing.md). Codes: [16-reference.md](16-reference.md).

## Screens & permissions
**Utilities → Tasks** `/admin/utils/tasks` (inboxes), `/tasks/create`, `/tasks/{id}` (view + follow-up + timeline +
attachments), `/tasks/{id}/edit` (owner). `UTL_TASK_VIEW` / `UTL_TASK_CREATE` for everyone; `UTL_TASK_ADMIN` sees any task.
People pickers use `OrgService::teamOptions()` (active staff only) with Select2.

## Gotchas
- Never write `Task` / `TaskPerson` directly. Don't create "assigned_to" columns in your module — link a task.
- Deadlines are shown in the site date format; store / pass ISO (`Y-m-d` or `Y-m-d H:i`).
