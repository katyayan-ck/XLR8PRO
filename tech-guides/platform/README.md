# Platform utilities — developer guides

Shared services every module uses instead of building its own notifications, comments, files, tasks,
tickets, approvals, settings or messaging. Spec: `tech-guides/frs-and-workflows/frs/platform-utilities-frs.md` (FRS v1.1).
Models and services of the business domains (org, person, vehicle, pricing, sales …): [`tech-guides/modules/`](../modules/README.md).
Decisions: DEC-060 … DEC-067 in `docs/decisions/decision-log.md`. Rules for agents: `.ai/rules/modules/platform.md`.

| # | Utility | Use it when you need to… | Facade | Guide |
|---|---|---|---|---|
| 1 | Settings | read / change a runtime setting or feature flag, per branch or desk | `Settings` | [01-settings.md](01-settings.md) |
| 2 | Notify | tell users something (bell, push, and optionally email / SMS / WhatsApp) | `Notify` | [02-notify.md](02-notify.md) |
| 3 | Chat | keep a record's history timeline and remarks (with @mentions, files) | `Chat` | [03-chat.md](03-chat.md) |
| 4 | Docs | store files / information cards, control who sees them, library, packs | `Docs` | [04-docs.md](04-docs.md) |
| 5 | Tasks | ask someone to do something by a date, with follow-ups | `Task` | [05-tasks.md](05-tasks.md) |
| 6 | Tickets | log an incident / support request with SLA | `Ticket` | [06-tickets.md](06-tickets.md) |
| 7 | Approvals | get an amount / % / yes-no granted by the right authority | `Approval` | [07-approvals.md](07-approvals.md) |
| 8 | Approval topics, rules & reports | define who may grant what; import the power sheet; report grants | `Topics`, `Rules` | [08-approval-topics-rules-reports.md](08-approval-topics-rules-reports.md) |
| 9 | Templates | define customer-facing message copy (email / SMS / WhatsApp) | `Templates` | [09-templates.md](09-templates.md) |
| 10 | Email | send email with from / cc / bcc / attachments | `Email` | [10-email.md](10-email.md) |
| 11 | SMS | send SMS; one-time passwords | `Sms` | [11-sms.md](11-sms.md) |
| 12 | WhatsApp | send / receive WhatsApp, agent inbox | `WhatsApp` | [12-whatsapp.md](12-whatsapp.md) |
| 13 | Telephony | click-to-call, call log, recordings | `Telephony` | [13-telephony.md](13-telephony.md) |
| — | UI kit | dates, multi-selects, uploads, theme / Appearance, AG-Grid, charts, dev UI kit `/admin/dev/ui` | Blade `x-ui.*`, `XL.*` | [ui-kit.md](ui-kit.md) |
| 14 | Cookbook | wire a new module to all utilities, end to end (worked example + checklist) | — | [14-cookbook.md](14-cookbook.md) |
| 15 | Testing | write feature tests for code that uses the utilities | — | [15-testing.md](15-testing.md) |
| 16 | Reference | every result code, event, job, setting, permission, webhook rule | — | [16-reference.md](16-reference.md) |
| 17 | Help & support | F1 help pane, help articles, Help centre (tours / support requests to come) | `HelpService`, `XL.help` | [17-help-support.md](17-help-support.md) |

**New here?** Read the rules below, then [14-cookbook.md](14-cookbook.md), then the guide for each utility you touch.

## Rules that apply to all of them

1. **Call the facade (or inject the service); never write their tables.** Each service is the only writer
   of its tables. Facades live in `App\Support\Facades\*`:
   ```php
   use App\Support\Facades\Notify;
   use App\Support\Facades\Chat;
   ```
2. **Every write returns `App\Support\Result`** — check it, don't expect exceptions for business failures:
   ```php
   $result = Task::create([...]);
   if (! $result->ok) {
       return back()->withErrors(['task' => $result->message]);   // $result->code = machine code, e.g. ASSIGNEE_REQUIRED
   }
   $taskId = $result->get('id');
   ```
   `$result->ok`, `->code`, `->message`, `->data`, `->get('key', $default)`, `->toArray()` (JSON-ready).
3. **Register your record type once** so chat, docs, notifications and deep links work for it —
   one row in `config/platform.php` → `entities`:
   ```php
   'QUOTE' => ['model' => App\Models\CRM\Quotation::class, 'url' => 'sales/quotation/{id}/edit',
               'label' => 'Quotation', 'permission' => 'SLS_QUOT_VIEW'],
   ```
   `ref_type` everywhere is this code (`QUOTE`, `BOOKING`, `TASK`, …). Access to a record's chat / files = the
   model's `chatCanView(int $userId)` if it has one, else the `permission` above.
4. **Opt your model in** with the traits: `use HasCommunications, HasDocuments;` (see guides 3 and 4).
5. **Order of a state change:** persist → `Chat::event()` → `Notify` → domain event. Services already do
   this for their own changes; follow the same order in module code.
6. **Enums come from KeyValue or Settings**, never hard-coded lists (`TASK_TYPE`, `TICKET_CATEGORY`,
   `CALL_DISPOSITION`, …).
7. **Screens follow the UI kit** (`ui-kit.md`): site date format, Select2 multi-selects, drop-zone uploads,
   responsive at 360 / 768 / desktop.

## Where things are

| What | Where |
|---|---|
| Services | `app/Services/Platform/{Settings,Notify,Chat,Docs,Task,Ticket,Approval,Templates,Comms}` |
| Models | `app/Models/Utilities/*`, `app/Models/Approval/*`, `app/Models/Comms/*` |
| Admin screens | `routes/backpack/utils.php` → `/admin/utils/...`, controllers in `app/Http/Controllers/Admin/Utils/Platform` |
| Blade components | `resources/views/components/{notify,chat,docs,task,ticket,approval,template,whatsapp,telephony,email,ui}` |
| Config | `config/platform.php` (entity types, notify kinds / channels, docs collections, settings seed pack) |
| Jobs / schedule | `app/Jobs/Platform/*`, `routes/console.php` |
| Webhooks | `POST /api/webhooks/comms/{email|sms|whatsapp|telephony}` |
| Tests | `tests/Feature/Platform/*` (FRS acceptance pack + per service) |
