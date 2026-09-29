# BMPL / Xceler8 — Platform Utilities FRS
## How each shared service MUST behave (product contract)

| Field | Value |
|---|---|
| Product | BMPL / Xceler8 DMS |
| Document | Platform Utilities Functional Requirements |
| Version | 1.1 — capability spec + Integration/Support Services |
| Date | 26 September 2026 |
| Audience | Architecture, backend, Blade/UI, QA |
| Scope | **What each service must do**, callable from any module, job, API, or Blade view. Not a dump of current helpers. Includes Email, SMS, WhatsApp, Telephony wrappers and the Template Engine. |

### How to read this document

Each block is a **standalone product**. Other modules (Enquiry, Quotation, Booking, HR, Accounts) **consume** these services. They do not re-implement notify, chat, docs, tasks, tickets, settings, or approval.

**Availability law (applies to every service below)**

1. One service class is the only write path.
2. A Facade (or thin helper alias) exists so any PHP file can call it without constructor noise.
3. Blade directives / components exist for the read/display side.
4. Eloquent traits exist so a domain model can opt in (`HasCommunications`, `HasDocuments`, `HasAssignments`, `Approvable`).
5. Events are fired after every successful write so listeners (notify, history, search index) stay decoupled.
6. Every call is tenant/org-aware and actor-aware (`Auth::user()` or explicit `$actorId`).
7. Failures return a structured result (`ok`, `code`, `message`, `data`) — never a blank `false`.
8. Keywords/enums come from Settings / KeyValue — no magic integers in callers.

**Shared actors**

| Actor | Meaning |
|---|---|
| Actor | Logged-in user performing the call |
| Subject | Person / employee the record is about |
| Owner | Person who created or owns the record |
| Assignee | Person responsible for doing work |
| Audience | People who may see or act (visibility ≠ assignment) |
| System | Jobs, console, inbound webhooks |

---

# 1. Site Settings

## 1.1 Purpose

The single runtime configuration plane for the dealership platform. Any feature that today would hard-code a tax rate, logo URL, feature flag, SLA hour, WhatsApp footer, FY calendar, or FCM sender name **must** read it from Settings.

Settings are **not** the same as KeyValue/enums. Settings = “what is this installation’s value”. KeyValue = “what are the allowed labels/codes”.

## 1.2 Features

- Hierarchical keys: `group.subgroup.key` (example `tax.tcs.threshold`, `brand.whatsapp.footer`).
- Typed values: string, int, decimal, bool, json, file (logo), encrypted secret.
- Scopes: Global → Company → Branch → Desk. More specific wins.
- Cache with tag `settings` and automatic bust on write.
- Feature flags (`quotes.csd.enabled`, `approval.engine.v2`).
- Read-only system keys vs admin-editable keys.
- Audit: who changed what, old → new, when.
- Blade-safe: missing key never throws in views; returns default.
- Seed pack per environment (dev/stage/prod) with the same keys.

## 1.3 Functional requirements

| ID | Requirement |
|---|---|
| SET-01 | `Settings::get('tax.tcs.threshold', 1000000)` returns the effective value for the current org context. |
| SET-02 | `Settings::getFor($branchCode, 'sla.ticket.p1_hours')` resolves Branch → Company → Global. |
| SET-03 | `Settings::set($key, $value, $scope)` validates type and allowed range before persist. |
| SET-04 | Encrypted keys (`sms.api_key`) never appear in logs, API list, or Blade dumps. |
| SET-05 | File keys store via Docs service and expose a public/signed URL. |
| SET-06 | `Settings::flag('approval.engine.v2')` is a bool used by modules to switch behaviour. |
| SET-07 | Admin UI lists groups, search, last-changed, and “reset to default”. |
| SET-08 | A change emits `SettingsChanged($key, $old, $new, $scope)` and flushes cache. |
| SET-09 | Console `settings:cache` / `settings:clear` for deploy. |
| SET-10 | Blade `@setting('brand.name', 'BMPL')` and `{{ setting('brand.logo_url') }}`. |

## 1.4 Use cases

- UC-SET-1 Quotation reads TCS threshold and FAME flag at calculate time, not from `.env`.
- UC-SET-2 Branch Jaipur has a different ticket P1 SLA than company default.
- UC-SET-3 Marketing updates WhatsApp footer copy without a release.
- UC-SET-4 Ops turns off CSD quotes for one weekend via flag.
- UC-SET-5 Auditor asks “who changed insurance default provider on 12 Aug” — Settings audit answers.

## 1.5 Code & Blade contract

```php
Settings::get('brand.name');
Settings::flag('quotes.csd.enabled');
Settings::getFor($orgCode, 'tax.tcs.threshold');
Settings::set('brand.whatsapp.footer', $text, scope: 'company');
```

```blade
<title>@setting('brand.name')</title>
<img src="{{ setting('brand.logo_url') }}">
@feature('quotes.csd.enabled')
    {{-- CSD form --}}
@endfeature
```

## 1.6 Out of scope

Application secrets that belong in `.env` (DB, mail host). Settings may *point* at a secret name; they do not replace the vault.

---

# 2. Notification Service

## 2.1 Purpose

Deliver a message to one or many people, persist it for the in-app inbox, optionally push (FCM), mail, or SMS, and keep a per-user read state. Every other service (Task, Ticket, Chat, Approval, Quote, Docs) **must** notify through this service. No module writes to a notifications table itself.

## 2.2 Features

- Channels: In-app (mandatory), FCM, Mail, SMS, Topic broadcast. Each user has channel preferences.
- Kinds: Notification (N), Alert (A), Message (M).
- Deep link: `ref_type` + `ref_id` so the app opens Quote / Task / Ticket / Approval / Doc / Vehicle.
- Audience builders: one user, many users, role, designation, org scope, topic subscribers, “everyone on this record”.
- Templates: title + body with placeholders (`{quote_id}`, `{actor}`, `{status}`).
- Idempotency key so retries do not double-notify.
- Badge counts by kind and unread.
- Bulk mark read / unread / archive by kind or by master (`purgeFor('QUOTE', $id)`).
- Quiet hours and digest (optional, flag-driven).
- Never notify the actor about their own write unless `notify_self` is set.
- Snooper / silent audience: persist in-app, skip push (or push without sound) per preference.

## 2.3 Functional requirements

| ID | Requirement |
|---|---|
| NOT-01 | `Notify::send($audience, $payload)` creates one master message and one inbox row per recipient. |
| NOT-02 | `$payload` includes `kind`, `title`, `body`, `ref_type`, `ref_id`, `channels[]`, `data{}`, `template`, `idempotency_key`. |
| NOT-03 | In-app row is written in the same DB transaction as the caller’s domain write when the caller asks (`syncInbox: true`). Push/mail always queued. |
| NOT-04 | FCM payload always carries `type` + `id` matching `ref_type` / `ref_id`. |
| NOT-05 | Missing or stale device token is recorded, not retried forever. |
| NOT-06 | `Notify::counts($userId)` returns `{ notifications, alerts, messages } × { total, unread, read }`. |
| NOT-07 | `Notify::list($userId, kind, state)` pages inbox newest first. |
| NOT-08 | `Notify::mark($userId, $inboxId, READ\|UNREAD\|ARCHIVE)`. |
| NOT-09 | `Notify::purgeFor($userId, $refType, $refId, READ\|ARCHIVE)` used when the user opens the quote/task. |
| NOT-10 | Blade bell component reads counts; polling / Echo optional later. |
| NOT-11 | If audience resolution yields zero people, log and return `ok` with `sent: 0` — do not throw. |
| NOT-12 | Template rendering uses Settings brand name and never embeds raw HTML from user remarks. |

## 2.4 Standard `ref_type` catalogue (locked)

| Code | Opens |
|---|---|
| QUOTE | Quote thread |
| TASK | Task view |
| TICKET | Ticket view |
| APPROVAL | Approval request |
| DOC | Document |
| VEHICLE | Vehicle master |
| PRICING | Price list session |
| CHAT | Deep link to a comm thread |
| SYSTEM | No entity; settings/announcement |

## 2.5 Use cases

- UC-NOT-1 Task assigned → assignee gets N + FCM “New task assigned #{id}”.
- UC-NOT-2 Quote escalated → next (or all visible) approvers get A.
- UC-NOT-3 Approval counter posted → requester gets N; other counters do not get a push unless they follow the request.
- UC-NOT-4 User opens Quote #8821 → `purgeFor(QUOTE, 8821)` marks those rows read.
- UC-NOT-5 Night job sends digest of unread alerts if flag on.
- UC-NOT-6 Snooper on a task receives in-app only.
- UC-NOT-7 Broadcast “price list published” to topic `pricing.{branch}`.

## 2.6 Code & Blade contract

```php
Notify::to($userId)->kind('N')->about('QUOTE', $qid)
    ->title('Quote #{id} assigned to you')
    ->body($remark)
    ->send();

Notify::toMany($userIds)->kind('A')->about('TASK', $tid)
    ->template('task.status_changed', compact('from', 'to', 'actor'))
    ->send();

Notify::audience(Audience::org('JAIPUR.SALES')->designation('SM'))
    ->kind('M')->title('Price list live')->send();
```

```blade
<x-notify.bell />
<x-notify.list kind="N" state="UNREAD" />
```

## 2.7 What callers must NOT do

- Insert into notification tables.
- Call Firebase from a controller.
- Hard-code title strings when a template exists.

---

# 3. Chat / Communication History Utility

## 3.1 Purpose

The journal of an entity. Every business object that people talk about (Quote, Task, Ticket, Approval request, Booking, Enquiry) has **one conversation** plus an ordered log of status events. Chat is how humans leave remarks and files; History is how the system records “status changed / assigned / approved”. Both live in this utility so the UI can show a single timeline or two tabs.

## 3.2 Features

- One master thread per `(entity_type, entity_id)`.
- Two entry kinds: **EVENT** (system) and **REMARK** (human).
- Nested replies on remarks (thread-under-a-comment).
- Attachments via Docs utility (never a private upload path).
- Visibility: people who can see the parent entity can see the journal, unless a remark is marked internal.
- Mentions `@user` resolve to Notify.
- Edit/delete own remark within a Settings window; events are immutable.
- Pin, react (optional later), search within a thread.
- Trait `HasCommunications` so ` $quote->addRemark()`, `$quote->timeline()`.
- Blade `<x-chat.thread :model="$quote" />` renders both tabs.

## 3.3 Functional requirements

| ID | Requirement |
|---|---|
| CHAT-01 | First write on an entity creates the master. Subsequent writes append. |
| CHAT-02 | `Chat::event($model, $action, $summary, $meta)` writes an immutable EVENT. `$action` is a KeyValue `ENTITY_ACTIONS` code (`CREATED`, `STATUS_CHANGED`, `ASSIGNED`, `APPROVED`, `COUNTERED`, `ATTACHED`…). |
| CHAT-03 | `Chat::remark($model, $body, $file = null, $parentId = null)` writes a REMARK; optional reply. |
| CHAT-04 | Timeline API returns `{ events: [], remarks: [], combined: [] }` sorted by time. Combined is default for mobile. |
| CHAT-05 | Each row exposes actor display name, actor id, human time, iso time, action label, body, files[], parent_id. |
| CHAT-06 | Gate: caller must be allowed to view the parent. Snoopers may read; they may not remark (unless a module overrides). |
| CHAT-07 | File on a remark goes through Docs (`collection: thread-docs`) and a DOC event is also recorded. |
| CHAT-08 | `@mention` in body → Notify kind M, ref CHAT + parent entity. |
| CHAT-09 | Soft-delete remark sets `deleted_at`; timeline shows “remark removed” not the text. |
| CHAT-10 | `Chat::subscribe($model, $userId)` / unsubscribe for “notify me of new remarks”. |
| CHAT-11 | Blade component paginates; posting uses the same service (AJAX or livewire/live form). |

## 3.4 Entity types the utility must support on day one

QUOTE, TASK, TICKET, APPROVAL, ENQUIRY, BOOKING, DOC, VEHICLE, PERSON, SYSTEM.

Adding a type is a KeyValue row + morph map. No code change in Chat itself.

## 3.5 Use cases

- UC-CHAT-1 Quote created → EVENT “Quote created”. FSC adds “Customer wants CN 8k” → REMARK + optional photo.
- UC-CHAT-2 Approver counters → Approval service writes EVENT “Countered at L4 ₹X”; Chat shows it on the quote timeline via the quote’s thread *or* the approval request thread (both allowed; quote thread is the customer-facing one).
- UC-CHAT-3 Task status FRESH → INPROGRESS → EVENT with from/to. Assignee adds progress remark.
- UC-CHAT-4 Ticket requester replies under an agent remark (nested).
- UC-CHAT-5 Auditor opens Booking #2345 and exports the combined timeline (the 11 history cases live here).
- UC-CHAT-6 User edits a remark after 2 minutes; after Settings `chat.edit_window_minutes` the edit button disappears.

## 3.6 Code & Blade contract

```php
Chat::event($quote, 'CREATED', 'Quote created');
Chat::remark($quote, $request->rem, $request->file('fdoc'));
$timeline = Chat::timeline($quote);          // events + remarks
$events   = Chat::events($quote);            // status tab
$remarks  = Chat::remarks($quote);           // communication tab
```

On a model:

```php
class Quotation extends Model {
    use HasCommunications;
}
$quote->addRemark('Need extra rust warranty', $file);
$quote->history(); // alias of Chat::timeline
```

```blade
<x-chat.thread :model="$quote" tabs="status,remarks,combined" />
<x-chat.composer :model="$quote" />
```

## 3.7 Rules

- Domain services call Chat. Controllers do not assemble Communication rows.
- Events never use user-typed text as the only record of a state change; state lives on the domain row, Chat is the narrative.
- One write path — if Task changes status it calls Task service, which calls Chat::event. Task does not call ChatHelper-style inserts.

---

# 4. Document Utility

## 4.1 Purpose

Store, classify, entitle, group, and attach files (and file-less information cards) so that Quote, Task, Ticket, Person KYC, Vehicle, and the standalone library all share one engine.

## 4.2 Features

- Upload to named collections (`docs`, `kyc`, `quote-pdf`, `thread-docs`, `Image`, `Doc`).
- Kinds: Image, Document, Information (no binary).
- Classification path for the library: **Entity → Location → Category → Sub-category → Item → FY**.
- Entitlement: explicit users, designations, departments, org scopes, “anyone who can see parent entity”.
- Personal briefcase: named groups + a cart (`_temp`) the user can save as a pack.
- Attach-to-entity: morph on any model via `HasDocuments`.
- Signed / public URL policy per collection.
- Preview: image thumb, pdf icon, info card.
- Virus/size/mime validation from Settings.
- Optional AI tags (flag) written as KeyValue labels, never as the only classifier.
- Replace / version a file without breaking old links (new media id; old kept until purge job).
- Download zip of a group or of an entity collection.

## 4.3 Functional requirements

| ID | Requirement |
|---|---|
| DOC-01 | `Docs::attach($model, $file, $collection, $meta)` stores media and returns a DTO (id, url, name, mime, size). |
| DOC-02 | `Docs::card($meta)` creates an Information row with no file. |
| DOC-03 | `Docs::entitle($docId, $spec)` where `$spec` is users / designations / departments / scopes. |
| DOC-04 | `Docs::canView($docId, $userId)` is the only check Blade and controllers use. |
| DOC-05 | `Docs::listFor($model, $collection)` and `Docs::library($filters)` (path + FY + kind). |
| DOC-06 | Cart: `Docs::cartAdd($userId, $docId)`, `cartRemove`, `cartSaveAs($name, $purpose)`. |
| DOC-07 | Groups: list, rename, delete, zip download. |
| DOC-08 | Library path is searchable and shown as breadcrumb. |
| DOC-09 | Deleting a doc is soft; entitlements remain for audit. Physical purge is a job. |
| DOC-10 | Attaching a file to a chat remark or task also writes Chat EVENT `ATTACHED`. |
| DOC-11 | Blade `<x-docs.picker>`, `<x-docs.preview>`, `<x-docs.library-path>`. |

## 4.4 Use cases

- UC-DOC-1 FSC drops a customer PAN on the quote composer → attached to quote + appears in timeline.
- UC-DOC-2 Accounts browses Entity=BMPL > Location=Jaipur > Category=Invoices > FY=2025-26.
- UC-DOC-3 Auditor builds a cart of 12 files, saves group “GST pack Aug”, downloads zip.
- UC-DOC-4 KYC category entitled to FI designation + Accounts department only.
- UC-DOC-5 Policy “Cash handling” is an Information card with no file.
- UC-DOC-6 Task follow-up includes a photo; Task, Chat, and Docs all show the same media id.

## 4.5 Code & Blade contract

```php
Docs::attach($task, $request->file('fdoc'), 'docs', ['caption' => $remark]);
Docs::entitle($docId, ['designations' => ['FI'], 'departments' => ['ACCOUNTS']]);
if (! Docs::canView($docId, $userId)) abort(403);
$pack = Docs::cartSaveAs($userId, 'Auditor pack', 'FY25 samples');
```

```blade
<x-docs.preview :doc="$doc" />
<x-docs.uploader :model="$task" collection="docs" />
@can('view', $doc) ... @endcan
```

---

# 5. Task Utility

## 5.1 Purpose

A general work item with four association roles, a status machine, a deadline, and automatic Chat + Notify. Used by humans for follow-ups and by other modules when they need “someone must do X by date”.

## 5.2 Features

- Types from KeyValue `TASK_TYPE` (at least `ASSIGNED_TASK`, `SELF_TASK`). SELF_TASK forces assignee = owner.
- Priority from `TASK_PRIORITY`.
- Roles: **Owner**, **Assignee(s)**, **Follower** (listener, may remark), **Snooper** (silent read).
- Group task when assignee count > 1.
- Status: FRESH → INPROGRESS / HOLD → SUBMITTED → CLOSED ↔ REOPENED.
- Deadline, overdue flag, age in days.
- Four inboxes for every user: Created, Assigned, Followed, Snooped.
- Optional link to a parent entity (`ref_type`, `ref_id`) so a task can hang off a Quote or Ticket.
- Follow-up = remark and/or status change, one form.
- Attachments via Docs.
- Team picker sourced from Org (not “all users”).

## 5.3 Rights matrix (locked)

| Action | Owner | Assignee | Follower | Snooper |
|---|---|---|---|---|
| View | Yes | Yes | Yes | Yes |
| Edit header (title, people, deadline) | Yes | No | No | No |
| Delete (soft) | Yes, if not CLOSED or with confirm | No | No | No |
| Remark | Yes | Yes | Yes | **No** |
| INPROGRESS / HOLD | Yes | Yes (from FRESH/INPROGRESS/HOLD) | No | No |
| SUBMIT FOR REVIEW | No | Yes | No | No |
| CLOSE | Yes | **No** | No | No |
| REOPEN | Yes, from CLOSED or SUBMITTED | No | No | No |

Status `NO-CHANGE` always allowed for people who may remark.

## 5.4 Functional requirements

| ID | Requirement |
|---|---|
| TSK-01 | `Task::create($payload)` validates type, people, writes associations, Chat CREATED, Notify all four roles with distinct copy. |
| TSK-02 | SELF_TASK ignores client assignee list and uses owner. ASSIGNED_TASK requires ≥1 assignee. |
| TSK-03 | `Task::followUp($id, $actor, $remark, $status, $file)` enforces the matrix; illegal transition returns `code: FORBIDDEN_TRANSITION`. |
| TSK-04 | `Task::inbox($userId, CREATED\|ASSIGNED\|FOLLOWED\|SNOOPED)` returns rows for that role only. |
| TSK-05 | `Task::get($id, $actor)` includes `user_role`, `user_can[]`, people names, deadline math, timeline. Unauthorised → `code: UNAUTHORISED` and a stripped payload. |
| TSK-06 | Replacing people on edit rebuilds associations without orphan rows. New people get Notify “added to task”. Removed people get Notify “removed” and lose inbox. |
| TSK-07 | Group flag is derived (assignee count > 1), not a user checkbox. |
| TSK-08 | Soft delete keeps Chat and Docs. |
| TSK-09 | Blade: four inbox pages, create/edit form, view + composer bound to Chat. |

## 5.5 Use cases

- UC-TSK-1 SM creates “Collect PAN” ASSIGNED_TASK, assignees FI + clerk (group), follower SM’s TL, snooper Audit. Four inboxes fill. Four notification texts differ.
- UC-TSK-2 Clerk moves INPROGRESS, attaches photo, remarks. Snooper sees it in Snooped box, cannot reply.
- UC-TSK-3 Clerk submits. Owner closes. Later owner reopens with a remark.
- UC-TSK-4 Owner creates SELF_TASK “Call financier tomorrow”.
- UC-TSK-5 Quote module calls `Task::create` with `ref QUOTE #8821` so the FSC’s pending CN follow-up sits in Assigned and on the quote.
- UC-TSK-6 Unauthorised URL `/tasks/view/99` does not leak title or people.

## 5.6 Code & Blade contract

```php
Task::create([
    'title' => 'Collect PAN',
    'type' => 'ASSIGNED_TASK',
    'priority' => 'HIGH',
    'owner_id' => $uid,
    'assignees' => [$fi, $clerk],
    'followers' => [$tl],
    'snoopers' => [$audit],
    'details' => $html,
    'deadline' => '2026-10-01',
    'ref_type' => 'QUOTE',
    'ref_id' => $qid,
]);

Task::followUp($tid, $actor, 'Visited customer', 'INPROGRESS', $file);
Task::inbox($uid, 'ASSIGNED');
```

```blade
<x-task.inbox box="ASSIGNED" />
<x-task.composer :task="$task" />
```

---

# 6. Ticket System

## 6.1 Purpose

Operational / IT / process incidents. Same association grammar as Task so people already understand Owner / Assignee / Follower / Snooper, but with **categories, SLA, priorities tied to clocks, and a requester who is not always the owner**.

## 6.2 Features

- Number `TCK/{branch}/{fy}/{seq}`.
- Categories (KeyValue): Hardware, Access, Data, Bug, Enhancement, Process.
- Priority drives SLA hours from Settings (`sla.ticket.p1_hours` …).
- Status: NEW → ACKNOWLEDGED → INPROGRESS → WAITING_USER → RESOLVED → CLOSED / REOPENED.
- Roles: Requester, Owner (service desk lead), Assignee(s), Follower, Snooper.
- SLA clock pauses on WAITING_USER; breaches raise Alert via Notify.
- Link to any entity (login issue on a User, bug on Quote).
- Canned replies optional later; Chat is the conversation.
- Only Assignee/Owner may set RESOLVED; Requester confirms CLOSE; Owner may force-close with reason.

## 6.3 Functional requirements

| ID | Requirement |
|---|---|
| TCK-01 | `Ticket::open($payload)` creates number, Chat CREATED, Notify service-desk queue + assignees. |
| TCK-02 | `Ticket::transition($id, $actor, $to, $remark)` enforces role + legal edges. |
| TCK-03 | SLA due_at computed at open and on priority change; job flags breached tickets hourly. |
| TCK-04 | Four inboxes + a fifth **Queue** for unassigned NEW tickets visible to the desk role. |
| TCK-05 | Waiting on user sends the requester an Alert and pauses SLA. |
| TCK-06 | Resolved sends requester “please confirm”. Auto-close after Settings `ticket.autoclose_days` if no reply (flag). |
| TCK-07 | Same Docs + Chat contracts as Task. |
| TCK-08 | Report: open by category, breached, mean time to resolve — topic of the ticket, not approval topics. |

## 6.4 Use cases

- UC-TCK-1 FSC cannot print PDF → category Bug, P2, ref QUOTE. Desk acknowledges, assignee IT, SLA 8h.
- UC-TCK-2 Assignee waits on user for a screenshot; clock pauses; FSC gets Alert.
- UC-TCK-3 Requester confirms close. Audit snooper watched the whole thread.
- UC-TCK-4 P1 access-down for a branch notifies designation IT-ONCALL as Alert.

## 6.5 Code & Blade contract

```php
Ticket::open([
    'category' => 'BUG',
    'priority' => 'P2',
    'title' => 'Quote PDF 500',
    'requester_id' => $uid,
    'ref_type' => 'QUOTE',
    'ref_id' => $qid,
    'details' => $body,
]);
Ticket::transition($id, $actor, 'ACKNOWLEDGED', 'Taken');
```

```blade
<x-ticket.inbox box="QUEUE" />
<x-ticket.sla-badge :ticket="$ticket" />
```

Do **not** reuse Task type ids or Chat entity type TASK. Ticket is a first-class entity_type.

---

# 7. Approval Service (Approver)

## 7.1 Purpose

Decide extra-commercial asks (discount, waiver, flag, limit) using **frozen authority**, **live visibility**, and **Highest-Level-Wins** on the current ask revision. This replaces “walk L1 then L2 then L3 with a single assigned_to” as the decision engine. Quote / Booking / Credit still own their own documents; they **raise requests** into this service.

## 7.2 Three concerns that must never be mixed

| Concern | Frozen? | Meaning |
|---|---|---|
| Authority | Yes, on the request snapshot | What each level *may* grant (amount / % / flag, min / std / max) |
| Visibility | No, live Org + designation | Who *sees* the open request right now |
| Decision | Computed | Highest level that posted a counter on **this ask revision** wins |

## 7.3 Features

- Requester opens a request on a topic + item against a source document (usually a Quote revision).
- Every entitled visible approver may **counter** (including counter = 0 or counter = asked). There is no “Reject” by an approver; they underwrite a number.
- Same level: latest counter replaces the previous one at that level.
- Highest level among active counters is the effective grant.
- Requester may **revise the ask** (new revision); old counters detach from the new ask.
- Requester **Accepts** (closes accepted) or **Withdraws**. System never auto-closes on a counter.
- Soft minimum: if grant < configured minimum, UI asks the requester to confirm.
- Snapshot of matched rule + levels at open time. Later rule edits do not change this request.
- Events append-only. Request row is a projection.
- Quote inboxes (Raised / Assigned / Team / …) remain; they become views over **open requests the user can see or owns**, not a single assigned_to pointer. A compatibility `assigned_to` may still be projected as “highest counter actor or requester”.

## 7.4 Functional requirements

| ID | Requirement |
|---|---|
| APR-01 | `Approval::open($source, $topicCode, $itemKey, $ask)` resolves topic mode, matches rule, snapshots levels, writes OPEN, Chat EVENT, Notify visible audience. |
| APR-02 | `Approval::counter($requestId, $actor, $value, $remark)` checks visibility + “actor has a level on the snapshot”. Stores counter bound to current ask revision. |
| APR-03 | `Approval::effective($requestId)` returns `{ level, actor, value, basis }` using highest-level-wins; ties at same level → latest counter. |
| APR-04 | `Approval::reviseAsk($requestId, $actor, $newAsk, $remark)` only requester; increments ask revision. |
| APR-05 | `Approval::close($requestId, $actor, ACCEPTED\|WITHDRAWN)` only requester (or a Settings-listed superuser). |
| APR-06 | `Approval::visibleTo($userId)` lists OPEN requests the live org+designation may see. |
| APR-07 | `Approval::authorize($actor, $topic, $scope, $value)` answers “could this person grant this without a request?” for auto-approve below own max. |
| APR-08 | Zero-ask or ask within requester’s own power may auto-close accepted with a system counter at the requester’s level (flag). |
| APR-09 | All writes emit events consumed by Chat (on the request and optionally the source quote) and Notify. |
| APR-10 | Blade `<x-approval.panel :request="$req" />` shows ask, counters by level, effective grant, composer for counter / revise / close. |

## 7.5 Decision examples the service must pass

- L2 counters 5,000; L4 counters 3,000 → effective is L4 / 3,000.
- L4 counters 3,000 then L4 counters 4,000 → effective 4,000 (same level, latest).
- L5 counters 0 → effective 0. Requester may revise ask or withdraw.
- Requester revises ask after L4 counter → L4 counter is not active on the new revision; panel shows “stale, re-counter”.
- Two topics on one quote (Insurance discount + Accessory discount) are **two requests**. Closing one does not close the other. Quote “Approved” aggregate = all mandatory topics accepted.

## 7.6 Use cases

- UC-APR-1 FSC asks ₹8,000 extra disc on Nexon. Rule snapshot L1 3k / L2 5k / L3 8k / L4 12k. SM (L2) counters 5k, GM (L4) counters 8k. Effective 8k. FSC accepts. Quote line stores granted 8k.
- UC-APR-2 Same quote, insurance loading waiver is a second request on topic INSURANCE.WAIVER.
- UC-APR-3 FSC’s own power already covers ₹2,000 — `authorize` true → auto-accepted, still snapshotted.
- UC-APR-4 Approver opens Team inbox, sees requests currently granted by someone below them (monitoring).
- UC-APR-5 Rule sheet changes tomorrow; yesterday’s open request still uses yesterday’s snapshot.

## 7.6 Code contract

```php
$req = Approval::open($quote, 'DISCOUNT.EXTRA', 'extra_disc', [
    'value_type' => 'AMOUNT',
    'asked' => 8000,
    'scope' => ['model' => 'NEXON', 'branch' => 'JAIPUR'],
]);

Approval::counter($req->id, $gm, 8000, 'Ok as per scheme');
$grant = Approval::effective($req->id);
Approval::close($req->id, $fsc, 'ACCEPTED');
```

Callers never compute “who is next L”. Visibility is a query, not a baton pass.

---

# 8. Multilevel / Topic-wise Approval Reporting

## 8.1 Purpose

The **topic tree and power sheet** that the Approval service matches against, plus the **reports** leadership needs: who granted what, on which topic, at which level, in which scope, this FY.

This is not a second engine. It is the master data + analytics face of §7.

## 8.2 Topic tree

```
Main topic
  └── Sub topic
        └── Item   ← requests attach here (approval_item_key)
```

Each node has a **mode**, inherited downward unless overridden:

| Mode | Meaning |
|---|---|
| STATIC | Fixed people list on the rule (rare; named committee) |
| LINEAR | Classic baton: only current level acts (legacy compatibility) |
| OPEN_TO_ALL | Default. Every snapshot level may counter; highest wins |
| VARIABLE | Resolve at runtime; if unresolved, treat as OPEN_TO_ALL |

## 8.3 Rule sheet (authority)

A rule is: topic node + scope tuple + ordered levels.

Scope dimensions (same ANY=NULL idea as Accessory catalog):

- Company / Zone / State / Branch / Desk
- Segment / Model / Variant / Permit
- Channel (retail / CSD / fleet) when present

Match order (locked):

1. Deepest topic node that has a rule (Item beats Sub beats Main).
2. Then scope specificity: variant > model > segment > permit > desk > branch > zone > company.
3. Then latest rule id.

Each level row: level no, designation (or post), value type AMOUNT | PERCENTAGE | FLAG, standard, min, max.

## 8.4 Features

- Admin tree editor for topics + mode + item keys (`extra_disc`, `ins_waiver`, `apack_disc`, `rto_exempt`…).
- Import power sheet (Excel) with Synonym + dry-run + purge-replace per topic (Accessory-style).
- Effective-dated rules (`valid_from` / `valid_to`).
- Simulation: “user U on vehicle V asking ₹X on item K — who is visible, what is each max, would this auto-pass?”
- Reports (filters: FY, branch, topic, item, level, actor, source type):
  - Requests opened / accepted / withdrawn / still open.
  - Granted vs asked (leakage).
  - Counters per level (who actually used power).
  - Auto-approved share.
  - Time-to-close.
  - Topic heatmap (which item burns power).
- Export CSV / xlsx.
- Drill from a report cell to the request + quote.

## 8.5 Functional requirements

| ID | Requirement |
|---|---|
| TOP-01 | Topic codes are stable strings. Renames do not break old snapshots (snapshot stores code + title). |
| TOP-02 | `Topics::resolve($itemKey)` returns the chain Main/Sub/Item and the effective mode. |
| TOP-03 | `Rules::match($topic, $scope)` implements the two-stage precedence and returns one rule. |
| TOP-04 | Import validates designations against Org/KeyValue; unknown rows go to an error sheet. |
| TOP-05 | Simulation is available in admin and as `Approval::preview($actor, $itemKey, $scope, $ask)`. |
| TOP-06 | Report queries read events + request projection; they never re-run matching. |
| TOP-07 | Blade `<x-approval.topic-tree />`, `<x-approval.report filters="..." />`. |
| TOP-08 | LINEAR mode, if a topic is set to it, exposes a single `current_level` pointer for that topic only — default platform mode remains OPEN_TO_ALL. |

## 8.6 Use cases

- UC-TOP-1 Product adds item `kazam_waiver` under ACCESSORIES.POWER. Import two rows (retail Jaipur max 2k, CSD max 0). Next quote can open that request.
- UC-TOP-2 CFO report: extra-disc leakage by branch for Q2, stacked by winning level.
- UC-TOP-3 GM simulates “if I counter 6k on this Nexon, do I win over SM’s 5k?” — yes, higher level.
- UC-TOP-4 Compliance lists all FLAG grants (`rto_exempt`) this month.
- UC-TOP-5 VARIABLE topic with no resolver configured falls back to OPEN_TO_ALL and logs a warning.

## 8.7 Code contract

```php
$node = Topics::resolve('extra_disc');
$rule = Rules::match($node, ['model' => 'NEXON', 'branch' => 'JAIPUR']);
$preview = Approval::preview($sm, 'extra_disc', $scope, 5000);

Report::approval()
    ->fy('2025-26')
    ->topic('DISCOUNT')
    ->groupBy('branch', 'winning_level')
    ->get();
```

---

# 9. How the eight services collaborate

```
Settings ──────── flags, SLA hours, brand, thresholds
KeyValue / Org ─── types, designations, scopes          (supporting, not this FRS)
        │
        ▼
Task / Ticket ──► Chat (timeline) ──► Docs (files)
        │                │
        └──► Notify ◄────┘
        
Quote / Booking ──► Approval::open(item_key, ask)
                      │
                      ├─ Topics + Rules (this FRS §8)
                      ├─ Chat event on request + source
                      ├─ Notify visible audience
                      └─ Docs if a justification file is attached
```

**One narrative rule:** a user-visible state change always does, in this order, inside one application service method:

1. Persist domain row  
2. Chat::event  
3. Notify::send (Notify may fan-out to Email / SMS / WhatsApp / FCM)  
4. Emit domain event  

Controllers only validate HTTP and call the application service.

---

# PART B — Integration / Support Services

These five utilities are the **outbound and inbound communication plane**. Domain modules never talk to SMTP, MSG91, Gupshup, Exotel, or Meta Cloud API directly. They talk to facades. Providers sit behind a driver interface so a vendor change is a Settings value + one driver class.

**Shared laws for all wrappers**

1. **Driver interface + registry.** `MailDriver`, `SmsDriver`, `WhatsAppDriver`, `TelephonyDriver`. Active driver from Settings (`sms.driver=msg91`).
2. **Outbox first.** Every send writes `comm_outbox` (channel, payload snapshot, template id+version, status QUEUED) then a job talks to the vendor. The domain transaction does not wait on the vendor.
3. **Idempotency key** required on every send. Retries reuse the key; vendors that support it get it; we still de-dupe locally.
4. **Identity resolution.** Recipients are `person_code` / user id / raw address. The service resolves email / mobile / wa_id from Person contacts. Callers may override with an explicit address.
5. **Template Engine is the only source of body copy** for customer-facing messages. Ad-hoc raw body is allowed only for internal/ops and must set `raw: true`.
6. **Docs for binaries.** Attachments and inbound media are stored through the Document utility. Wrappers never keep vendor CDN URLs as the system of record.
7. **DND / consent / quiet hours.** Person contact has `consent.email`, `consent.sms`, `consent.whatsapp`, `consent.call`. Quiet hours from Settings. Transactional templates may bypass marketing quiet hours; promotional may not.
8. **Audit.** Every send/receive is queryable by person, entity (`ref_type`+`ref_id`), template, channel, status.
9. **Sandbox driver** for local/dev that writes to logs + a `comm_sandbox` table instead of the vendor.
10. Structured result: `{ ok, outbox_id, provider_message_id, code, message }`.

Notify is the **orchestrator**, not a second mailer:

```
Notify::send(...)
  channels: ['INAPP','FCM','EMAIL','SMS','WHATSAPP']
        │
        ├─ INAPP / FCM     → Notification service
        ├─ EMAIL           → Email::send(...)
        ├─ SMS             → Sms::send(...)
        └─ WHATSAPP        → WhatsApp::send(...)
```

Callers that need a channel with extra options (cc, bcc, specific template, extra attachment) pass a `channels` map. Notify forwards those options unchanged.

```php
Notify::to($personCode)
    ->kind('N')
    ->about('QUOTE', $qid)
    ->channels([
        'INAPP' => true,
        'FCM'   => true,
        'EMAIL' => [
            'template' => 'quote.customer.send',
            'from'     => 'quotes@bmpl.in',
            'cc'       => ['sm@bmpl.in'],
            'bcc'      => ['audit@bmpl.in'],
            'attach'   => [$quotePdfDocId],
            'vars'     => ['cust_name' => $name, 'link' => $url],
        ],
        'WHATSAPP' => [
            'template' => 'quote.customer.send.wa',
            'vars'     => ['cust_name' => $name, 'link' => $url],
        ],
    ])
    ->send();
```

---

# 12. Template Engine

## 12.1 Purpose

Single catalogue of message copy for Email, SMS, WhatsApp, and (later) print/push. A template is a **versioned, approvable artefact**. Runtime services only send an **Approved + Active** version. Editors never hot-edit production copy.

This is not Blade views in `resources/views/mail`. Those may be the *renderer*, but the record of “what is live, who signed it, which variables it needs” lives here.

## 12.2 Features

- Channels per template: EMAIL, SMS, WHATSAPP, PUSH, PRINT. One logical template family can have one row per channel (`quote.customer.send` email + `quote.customer.send.wa`).
- Versioning: DRAFT → IN_REVIEW → APPROVED → ACTIVE. New edit always forks a draft. Activating a version retires the previous ACTIVE (kept for replay).
- Approval of templates uses the **Approval service** on topic `COMMS.TEMPLATE` (OPEN_TO_ALL or LINEAR — configurable). Marketing copy should not go live on one intern’s save.
- Variable schema: declared list `{ name, type, required, sample, pii }`. Render fails if a required var is missing. PII vars are masked in outbox logs.
- Layouts: EMAIL has header/footer/brand layout from Settings/Docs (logo). SMS/WA have no layout, only body + buttons.
- Locale + brand variants (`en-IN`, `hi-IN`) without forking the code key.
- Usage ledger: last sent at, send count, bounce/fail rate, linked outbox ids.
- Preview with a sample variable bag. WhatsApp preview must show the exact Meta-approved body (no extra words).
- WhatsApp / SMS vendor template id mapping (`provider_template_id`) so the wrapper submits the registered HSM, not free text, when the vendor requires it.
- Category: TRANSACTIONAL / OTP / OPERATIONAL / PROMOTIONAL. Promotional requires consent + time-window.
- Deprecation date and “replaced by” key.
- Import/export JSON for promotion across environments (draft only; activation is per env).

## 12.3 Template record (logical)

| Field | Notes |
|---|---|
| `code` | Stable key `quote.customer.send` |
| `channel` | EMAIL / SMS / WHATSAPP / PUSH / PRINT |
| `category` | TRANSACTIONAL / OTP / OPERATIONAL / PROMOTIONAL |
| `name`, `description` | Admin |
| `locale`, `brand` | Default `en-IN`, `BMPL` |
| `subject` | Email only; may contain `{{var}}` |
| `body_html`, `body_text` | Email |
| `body_text` | SMS / WA |
| `wa_components` | header / body / buttons / footer JSON as vendor expects |
| `variables[]` | schema |
| `provider_template_id` | HSM / DLT template id |
| `dlt_entity_id`, `dlt_header` | India SMS |
| `version`, `status` | draft/review/approved/active/retired |
| `approved_by`, `approved_at` | |
| `usage_count`, `last_used_at` | |
| `sample_vars` | JSON for preview |

## 12.4 Functional requirements

| ID | Requirement |
|---|---|
| TPL-01 | `Templates::get($code, $channel, $locale)` returns the ACTIVE version or a typed `TEMPLATE_NOT_ACTIVE`. |
| TPL-02 | `Templates::render($code, $channel, $vars)` returns `{ subject, html, text, wa_payload, missing[], warnings[] }`. |
| TPL-03 | Unknown `{{placeholder}}` in body is a render error in production; in preview it is highlighted. |
| TPL-04 | `Templates::saveDraft($code, $payload, $actor)` never mutates ACTIVE. |
| TPL-05 | `Templates::submit($code, $version)` opens Approval request; on ACCEPTED the version becomes APPROVED. |
| TPL-06 | `Templates::activate($code, $version)` requires APPROVED; previous ACTIVE → RETIRED. |
| TPL-07 | WhatsApp/SMS templates that need vendor registration expose `Templates::registerWithProvider($code)` which the wrapper implements; status `PENDING_PROVIDER` until webhook confirms. |
| TPL-08 | Outbox stores `template_code` + `template_version` so a send can be replayed exactly. |
| TPL-09 | Admin UI: list, filter by channel/status/usage, diff versions, preview, usage drill-down. |
| TPL-10 | Blade `<x-template.preview :code="" :channel="" :vars="" />` for admin only. |

## 12.5 Use cases

- UC-TPL-1 Marketing drafts new Diwali WA template, submits, Brand + Compliance counter-approve, activate. Next `WhatsApp::send` uses v3. v2 remains for audit of old sends.
- UC-TPL-2 Quote PDF mail uses `quote.customer.send` v7. FSC cannot type the body. They only pass vars + attachment doc id.
- UC-TPL-3 OTP SMS uses category OTP, DLT template id, 2-minute expiry copy. Promotional engine refuses to send OTP templates to a marketing segment.
- UC-TPL-4 Locale `hi-IN` missing → fallback `en-IN` + warning in result, not a silent English surprise if Settings `templates.strict_locale=true`.
- UC-TPL-5 Compliance asks “show every promotional SMS live right now” — list ACTIVE + PROMOTIONAL.

## 12.6 Code contract

```php
$rendered = Templates::render('quote.customer.send', 'EMAIL', [
    'cust_name' => 'Ravi',
    'vehicle'   => 'Nexon XZ+',
    'link'      => $url,
]);

Templates::saveDraft('quote.customer.send', ['body_html' => $html], $actor);
Templates::submit('quote.customer.send', $versionId);
Templates::activate('quote.customer.send', $versionId);
```

---

# 13. Email Service

## 13.1 Purpose

Send (and later receive) email with full envelope control: from / reply-to / to / cc / bcc, template or raw, attachments from Docs, per-message tags, and delivery tracking. Notification calls this service when a send includes the EMAIL channel. Quote, HR, Accounts, and Ticket may also call Email directly when they need cc/bcc/attachments that Notify’s default template does not know about.

## 13.2 Features

- Envelope: `from` (must be a verified identity in Settings allow-list), `reply_to`, `to[]`, `cc[]`, `bcc[]`.
- Identity aliases: `from: 'quotes'` resolves `mail.identities.quotes` = `Bikaner Motors Quotes <quotes@bmpl.in>`.
- Template mode (default): `template` + `vars`. Raw mode: `subject` + `html` + `text` + `raw: true` (internal only).
- Attachments: Doc ids, or a model+collection (“attach latest quote PDF”). Inline images via Docs (logo).
- Calendar invites (`ics`) as a first-class attach type for test-drive / interview.
- Headers: `X-Entity-Ref-Type`, `X-Entity-Ref-Id`, List-Unsubscribe for promotional.
- Tracking: accepted / bounced / complained / delivered / opened / clicked (as far as the driver supports). Stored on outbox, not guessed.
- Open/click tracking can be disabled per template category (OTP never tracked).
- Batch send with personalization (one template, N recipients, N var bags) still writes N outbox rows.
- Inbound (phase 2): webhook → parse → attach to Chat of the referenced entity if `In-Reply-To` / plus-address matches (`quote-8821@inbound.bmpl.in`).
- Suppression list: hard bounce and complaint addresses are skipped and reported.
- Rate limit per identity from Settings.

## 13.3 Invoke options (locked shape)

```php
Email::send([
    'to'        => [$personCodeOrEmail, ...],
    'cc'        => [...],                 // optional
    'bcc'       => [...],                 // optional
    'from'      => 'quotes',              // alias or full address
    'reply_to'  => 'fsc.ravi@bmpl.in',    // optional
    'template'  => 'quote.customer.send',
    'vars'      => ['cust_name' => 'Ravi', 'link' => $url],
    'attach'    => [                      // Doc ids and/or descriptors
        $pdfDocId,
        ['doc_id' => $id, 'as' => 'Quote-8821.pdf'],
        ['ics' => $calendarPayload],
    ],
    'ref_type'  => 'QUOTE',
    'ref_id'    => $qid,
    'idempotency_key' => 'quote.8821.send.v3',
    'locale'    => 'en-IN',
]);
```

Notify forwards the same keys under `channels.EMAIL`.

## 13.4 Functional requirements

| ID | Requirement |
|---|---|
| EML-01 | Allow-list check on `from`. Unknown identity → `FROM_NOT_ALLOWED`. |
| EML-02 | At least one `to`. Empty after suppression → `ok` with `sent: 0`, not an exception. |
| EML-03 | Template path must use Template Engine ACTIVE version. |
| EML-04 | Each attachment is pulled from Docs; missing doc → fail the send before vendor call. |
| EML-05 | BCC never appears in Chat excerpts or customer-visible logs. |
| EML-06 | Driver swap (`mail.driver=smtp\|ses\|postmark\|mailgun\|log`) does not change this API. |
| EML-07 | Bounce webhook marks Person contact `email_status=BOUNCED` and suppression. |
| EML-08 | `Email::status($outboxId)` returns vendor-normalized state. |
| EML-09 | `Email::resend($outboxId, $actor)` clones with a new idempotency suffix, same snapshot. |
| EML-10 | Blade `<x-email.send-panel>` for ops “send this quote again” using the same API. |

## 13.5 Use cases

- UC-EML-1 FSC clicks “Email quote”: from=quotes, to=customer, cc=SM, bcc=branch audit, attach Quote PDF + Insurance brochure, template `quote.customer.send`, vars from quote DTO.
- UC-EML-2 Notify on Task SUBMITTED: default no cc; Task service can add cc = followers by passing EMAIL options.
- UC-EML-3 OTP mail: template category OTP, no open-tracking, no marketing footer.
- UC-EML-4 Salary slip: from=hr, to=employee, attach payslip Doc, bcc=none, encryption flag if driver supports it.
- UC-EML-5 Customer replies to quote mail → inbound plus-address routes a Chat REMARK on QUOTE 8821 with the MIME attachment stored in Docs.
- UC-EML-6 SES outage: outbox stays QUEUED, retry with backoff, dead-letter after N; Ticket auto-opened for comms-ops when fail-rate exceeds Settings threshold.

## 13.6 What callers must not do

- `Mail::raw` / `Mail::send` from a module.
- Embed SMTP credentials in feature code.
- Pass unsanitized HTML as `vars` that can break out of the template.

---

# 14. SMS Service

## 14.1 Purpose

Send transactional, OTP, and (opt-in) promotional SMS through any Indian or global aggregator without the rest of Xceler8 knowing which one. DLT headers, template ids, sender ids, and vendor quirks live in the driver + Template Engine mapping.

## 14.2 Features

- Driver interface: `send`, `balance`, `deliveryReport`, `health`. Stock drivers: MSG91, Kaleyra, Twilio, Gupshup, Log/Sandbox. One active + one failover.
- Envelope: `to` (E.164 or 10-digit IN; service normalizes), `sender_id` / header, `template`, `vars`, `dlt_entity_id` fallback from template.
- OTP helper: `Sms::otp($person, $purpose, $ttl)` generates, stores hashed OTP, sends via OTP template, `Sms::verify($person, $purpose, $code)`.
- Unicode / GSM detection; billing units recorded on outbox.
- Promotional vs transactional routes (different sender ids).
- Time window for promotional (TRAIs / Settings).
- Delivery receipts via webhook → outbox DELIVERED / FAILED / EXPIRED.
- Inbound SMS (phase 2): keyword STOP → revoke `consent.sms`; HELP → auto-reply template; other keywords can open a Ticket or Chat on a campaign code.
- Failover: if primary returns 5xx / timeout, retry once on secondary driver with same idempotency key.
- Credit balance alert via Notify kind A to comms-ops when below Settings threshold.

## 14.3 Invoke options

```php
Sms::send([
    'to'        => $personCodeOrMobile,
    'template'  => 'booking.delivery.sms',
    'vars'      => ['cust_name' => 'Ravi', 'when' => 'Tue 11am'],
    'from'      => 'BMPLRT',              // optional override of template header
    'ref_type'  => 'BOOKING',
    'ref_id'    => $bid,
    'idempotency_key' => 'booking.55.delivery.sms',
]);

Sms::otp($personCode, purpose: 'LOGIN', ttl: 300);
Sms::verify($personCode, 'LOGIN', $code);
```

## 14.4 Functional requirements

| ID | Requirement |
|---|---|
| SMS-01 | Numbers normalized to E.164. Invalid → `INVALID_MSISDN` without vendor call. |
| SMS-02 | No consent + category PROMOTIONAL → `CONSENT_DENIED`. TRANSACTIONAL / OTP proceed unless hard-suppressed. |
| SMS-03 | Body always from Template Engine. Raw body only if `raw: true` and actor has `comms.sms.raw` permission. |
| SMS-04 | DLT template id + entity id + header must be present for IN transactional; missing → `DLT_MAP_MISSING`. |
| SMS-05 | OTP codes never stored in outbox body; outbox stores `purpose` + masked destination only. |
| SMS-06 | Driver change is Settings `sms.driver` + optional `sms.failover_driver`. |
| SMS-07 | `Sms::status($outboxId)` normalized. |
| SMS-08 | STOP inbound sets consent false and replies with `sms.stop.ack` template. |
| SMS-09 | Quiet hours apply to PROMOTIONAL only. |

## 14.5 Use cases

- UC-SMS-1 Login OTP. 5-minute TTL, max 3 sends / 15 min / person (Settings).
- UC-SMS-2 Booking “vehicle arrived at workshop” transactional SMS, template vars, DLT mapped.
- UC-SMS-3 Festival promo to consented customers only, 10:00–18:00 IST, promotional header.
- UC-SMS-4 Primary MSG91 503 → failover Kaleyra, same idempotency, one customer SMS.
- UC-SMS-5 Notify channel SMS on Ticket P1 to on-call mobile.

## 14.6 Isolation rule

Feature code contains zero vendor SDK imports. Only `App\Comms\Sms\Drivers\*` may reference a vendor.

---

# 15. WhatsApp Service

## 15.1 Purpose

Send and **read** WhatsApp Business conversations through a third-party Cloud API reseller (Gupshup, 360dialog, ValueFirst, Wati, Meta Cloud directly). Xceler8 sees conversations, not vendor payloads. Media, templates, free-form session messages, polls/surveys, groups (where the vendor allows), and history retrieval all go through this wrapper.

## 15.2 Capabilities the wrapper must expose

| Capability | Notes |
|---|---|
| Session send | Free-form text / media / location / contacts inside 24h customer-care window |
| Template send | HSM outside the window; Template Engine + `provider_template_id` |
| Interactive | Buttons, lists, **polls / surveys** if driver supports; otherwise degrade to numbered-list text and record the degrade |
| Media send | Image, video, audio, document, sticker — source is always a Doc id |
| Media receive | Download vendor media → Docs collection `wa-inbound` → never keep vendor URL as SoR |
| Read conversation | `WhatsApp::thread($waId)` messages oldest/newest, with media |
| History by counterpart | All messages to/from a person or group id, filter by date, type, direction |
| Groups | Where vendor supports: send to group, list members, retrieve group history + media |
| Status | sent / delivered / read / failed |
| Window clock | Per contact `session_expires_at`; UI warns FSC before sending free-form |
| Assignment | A WA thread can be linked to Person + optional entity (QUOTE/TICKET) and assigned to an FSC (Task-like, but light) |
| Labels | Open / Pending / Done for the inbox |

## 15.3 Inbox model

WhatsApp is both a pipe and a workplace:

- **Customer inbox** for FSCs: open sessions, unread, assigned to me / unassigned queue.
- Every inbound message: outbox/inbox row + optional Chat REMARK if the thread is linked to a Quote/Ticket/Booking.
- Agent outbound from the inbox uses the same `WhatsApp::send` API as campaigns.

## 15.4 Invoke options

```php
// Template (outside session window)
WhatsApp::send([
    'to'        => $personCodeOrWaId,
    'template'  => 'quote.customer.send.wa',
    'vars'      => ['cust_name' => 'Ravi', 'vehicle' => 'Nexon', 'link' => $url],
    'attach'    => [$pdfDocId],           // header document if template allows
    'ref_type'  => 'QUOTE',
    'ref_id'    => $qid,
    'idempotency_key' => 'quote.8821.wa',
]);

// Session message
WhatsApp::send([
    'to'      => $waId,
    'type'    => 'TEXT',                  // TEXT|IMAGE|VIDEO|AUDIO|DOCUMENT|LOCATION|POLL|INTERACTIVE
    'text'    => $body,
    'raw'     => true,                    // agent typed it
    'ref_type'=> 'QUOTE',
    'ref_id'  => $qid,
]);

WhatsApp::send([
    'to'   => $waId,
    'type' => 'POLL',
    'poll' => [
        'question' => 'Preferred visit slot?',
        'options'  => ['Sat 11am', 'Sat 4pm', 'Sun 11am'],
        'multi'    => false,
    ],
]);

$thread   = WhatsApp::thread($waId, since: '2026-09-01');
$history  = WhatsApp::history(['person' => $personCode, 'direction' => 'ANY']);
$group    = WhatsApp::history(['group_id' => $gid, 'with_media' => true]);
WhatsApp::markRead($messageId);
```

## 15.5 Functional requirements

| ID | Requirement |
|---|---|
| WA-01 | If session window closed and send type is not a registered template → `SESSION_CLOSED`. UI must offer template send. |
| WA-02 | Template send refuses extra body text (Meta rule). Vars only. |
| WA-03 | Inbound webhook verifies signature, is idempotent on vendor message id. |
| WA-04 | Inbound media downloaded within Settings minutes, stored in Docs, outbox/inbox points at doc id. |
| WA-05 | Poll votes stored as structured answers; if driver has no poll, wrapper sends numbered list and parses reply “1/2/3”. |
| WA-06 | `WhatsApp::thread` paginates; includes text, media docs, poll summaries, delivery state. |
| WA-07 | Group retrieve returns messages + media doc ids + sender wa_id mapped to Person when known. |
| WA-08 | Opt-out phrases (STOP) flip `consent.whatsapp`. |
| WA-09 | Quality rating / template rejection webhooks update Template Engine `PENDING_PROVIDER` / `REJECTED`. |
| WA-10 | Driver swap (`whatsapp.driver=gupshup\|360dialog\|meta`) does not change this API. |
| WA-11 | Agent inbox actions (assign, label, reply) are authorized by RBAC `wa.inbox`. |
| WA-12 | Linking a WA thread to a Quote writes Chat EVENT `CHANNEL_LINKED` and future inbound becomes remarks. |

## 15.6 Use cases

- UC-WA-1 Share quote: template with document header (PDF from Docs), body vars, button URL. Same moment Email may also fire via Notify.
- UC-WA-2 Customer replies “price high” → inbound lands in FSC inbox, linked quote shows a Chat REMARK, FSC replies free-form inside 24h.
- UC-WA-3 Service advisor sends a poll “pickup or drop?”. Answer stored on Booking.
- UC-WA-4 Workshop group “Jaipur Bodyshop” history pulled for an insurance claim file; media zipped via Docs group.
- UC-WA-5 Campaign 2,000 consented customers, template only, throttled to vendor TPS from Settings.
- UC-WA-6 Session expired, FSC tries free-form → API error + Blade prompt “send template X instead”.
- UC-WA-7 Customer sends a video of a dent → Docs `wa-inbound` + Ticket or Job-card attachment.

## 15.7 Blade

```blade
<x-whatsapp.inbox />
<x-whatsapp.thread :waId="$waId" />
<x-whatsapp.composer :waId="$waId" :entity="$quote" />
```

---

# 16. Telephony Wrapper

## 16.1 Purpose

Click-to-call, call logging, recording retrieval, and (where the PBX/CPaaS allows) inbound DID correlation — all behind one API. Exotel, Knowlarity, MyOperator, Tata EasyDial, or an on-prem IP-PBX should be replaceable without touching Enquiry / CRM screens.

## 16.2 Features

- **Click-to-call:** `Telephony::dial($actorUserId, $destinationPersonOrMsisdn, $ref)` first rings the agent’s registered extension / mobile, then the customer. Returns `call_id`.
- **Call object:** direction IN/OUT, from, to, agent user id, person_code, started_at, answered_at, ended_at, duration, disposition, recording_doc_id, vendor_call_id, ref_type/ref_id.
- **Recordings:** webhook or pull job fetches audio, stores via Docs collection `call-recordings`, entitlement = agent + manager + audit snooper.
- **Click-to-call from anywhere:** Blade component + JS on any phone-looking field (Person, Enquiry, Quote, Ticket).
- **Missed-call / incoming:** DID map in Settings (`telephony.did.jaipur_sales`) → open or bump Enquiry, Chat EVENT `CALL_INBOUND`, optional Task “call back”.
- **Disposition codes** KeyValue: CONNECTED, NO_ANSWER, BUSY, WRONG_NUMBER, VOICEMAIL, CALLBACK_REQUESTED.
- **Live listen / whisper / barge** only if driver supports; API exists, driver may return `NOT_SUPPORTED`.
- **Masking:** customer sees a mask number when Settings `telephony.mask=true`; real MSISDN never sent to the browser.
- **Pop-up:** on inbound, desktop notification + deep link to Person 360 if CLI known.
- **Reports:** answered rate, AHT, recordings missing, calls per agent / campaign.

## 16.3 Invoke options

```php
$call = Telephony::dial(
    actor: $userId,
    to: $personCode,                 // or raw msisdn if permitted
    ref: ['type' => 'ENQUIRY', 'id' => $eid],
    options: ['caller_id' => 'JAIPUR_SALES', 'record' => true],
);

$rec  = Telephony::recording($callId);       // Doc DTO
$list = Telephony::calls(['person' => $personCode, 'from' => $date]);
Telephony::dispose($callId, 'CALLBACK_REQUESTED', $remark);
```

## 16.4 Functional requirements

| ID | Requirement |
|---|---|
| TEL-01 | `dial` checks `consent.call` and quiet hours for PROMOTIONAL campaigns; operational follow-up is allowed. |
| TEL-02 | Browser never receives the raw customer number when masking is on; only a handle. |
| TEL-03 | Every call row exists before the vendor returns (status DIALING), then updates via webhook. |
| TEL-04 | Recording pull is idempotent; stored as Docs; Chat EVENT `CALL_RECORDED` on the ref entity when linked. |
| TEL-05 | Missing recording after Settings grace period → Alert to telephony-ops + flag on the call. |
| TEL-06 | Driver swap `telephony.driver=exotel\|knowlarity\|myoperator\|asterisk`. |
| TEL-07 | Click-to-call component works on Person, Enquiry, Quote, Ticket, Task, WA thread pages. |
| TEL-08 | Inbound unmatched CLI creates / matches Person by mobile via Person service; never a silent drop. |
| TEL-09 | Agents cannot download recordings unless policy `telephony.recording.download`. Play-in-browser still allowed if `view`. |
| TEL-10 | `NOT_SUPPORTED` for whisper/barge must not break dial. |

## 16.5 Use cases

- UC-TEL-1 On Enquiry 360, FSC hits Call. Agent phone rings, then customer. Call linked to Enquiry. Recording appears in 3 minutes under Docs + timeline.
- UC-TEL-2 Missed call on Jaipur sales DID at 21:10 → Enquiry bumped, Task “callback after 09:30”, SMS template `missed.call.ack`.
- UC-TEL-3 Audit snooper on a complaint Ticket plays the related recording, cannot download.
- UC-TEL-4 Provider migrated from Exotel to Knowlarity over a weekend: Settings + new driver; Enquiry screen unchanged.
- UC-TEL-5 Campaign “hot prospects” click-to-call list with mandatory disposition before next dial.

## 16.6 Blade

```blade
<x-telephony.click-to-call :person="$person" :ref="$enquiry" />
<x-telephony.recordings :ref="$enquiry" />
<x-telephony.call-log :person="$person" />
```

---

# 17. How Notify, Templates, and wrappers fit together

```
Caller (Quote / Task / Ticket / Approval / Job)
        │
        ▼
Notify::send(audience, kind, ref, channels{})
        │
        ├─ INAPP + FCM          Notification tables + Firebase
        ├─ EMAIL                Email::send(from, cc, bcc, template, vars, attach)
        ├─ SMS                  Sms::send(template, vars)
        └─ WHATSAPP             WhatsApp::send(template|session, vars, media)
                │
                ▼
        Templates::render(code, channel, vars)
                │
                ▼
        Outbox row (template version snapshotted)
                │
                ▼
        Driver (SES / MSG91 / Gupshup / Exotel / sandbox)
                │
                ▼
        Webhook → outbox status → optional Chat EVENT
```

Direct calls (`Email::send`, `WhatsApp::send`, `Telephony::dial`) are allowed when the feature needs envelope control or is not a “notification” (campaigns, click-to-call, OTP). They still **must** go through Templates when customer-facing copy is involved.

---

# 10. Blade & code availability checklist (all services)

Every service above must ship:

| Surface | Example |
|---|---|
| Facade | `Notify::`, `Chat::`, `Docs::`, `Task::`, `Ticket::`, `Approval::`, `Settings::`, `Topics::`, `Templates::`, `Email::`, `Sms::`, `WhatsApp::`, `Telephony::` |
| Trait on models | `HasCommunications`, `HasDocuments`, `HasTasks`, `Approvable` |
| Blade component | `<x-chat.thread>`, `<x-notify.bell>`, `<x-docs.uploader>`, `<x-task.inbox>`, `<x-ticket.sla-badge>`, `<x-approval.panel>`, `<x-whatsapp.inbox>`, `<x-telephony.click-to-call>`, `@setting`, `@feature` |
| Policy | `view`, `remark`, `counter`, `close`, `entitle`, `wa.inbox`, `telephony.recording.download` |
| Event | `*Created`, `*Changed`, `*Closed`, `OutboxAccepted`, `CallRecorded`, `ChannelLinked` |
| Idempotent jobs | FCM, mail, SMS, WA send, recording pull, SLA sweep, settings cache |

If a new module needs “comment + file + notify”, it uses Chat + Docs + Notify. It does not invent a fourth table. If it needs “email this PDF to customer and SM”, it uses Email + Templates + Docs, or Notify with an EMAIL options block.

---

# 11. Acceptance pack (minimum)

1. From a Quote controller, open an approval, attach a file, add a remark, assign a follow-up task — four facades, zero new tables in the quote module.
2. Bell badge matches unread inbox after each of those writes.
3. Snooper on the task sees the task and chat, cannot remark.
4. Highest-level counter changes the effective grant without moving an `assigned_to` baton.
5. Settings flag turns CSD form off in Blade without a deploy of quote code.
6. Library cart zip downloads only files `canView` allows.
7. Ticket P1 breach sends Alert using Settings hours.
8. Topic report matches the events of accepted requests for a FY.
9. `Notify` with EMAIL options (from, cc, bcc, template, vars, Doc attach) produces one outbox row whose snapshot can be resent after a driver swap.
10. SMS driver flipped from sandbox to MSG91 in Settings; OTP still uses `Sms::otp` unchanged.
11. WhatsApp template send outside 24h window succeeds; free-form in that state returns `SESSION_CLOSED`.
12. Inbound WA image becomes a Docs row and appears in `WhatsApp::thread`.
13. Click-to-call from Enquiry creates a call row, then a recording Doc, then a Chat EVENT on that Enquiry.
14. Draft template cannot be sent; only ACTIVE. Activating v2 does not rewrite v1 outbox snapshots.
15. STOP on SMS and WA flips the matching consent flag and is honored on the next send.

---

*End of Platform Utilities FRS v1.1. Implementation may use Laravel 12 services, Spatie media, queues, and existing table names, as long as callers only see the contracts in this file.*

