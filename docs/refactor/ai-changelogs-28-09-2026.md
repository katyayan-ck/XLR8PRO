
# AI changelog: 28-09-2026 (Track A: helper purge + platform utilities)

## Legacy helpers removed (DEC-060)
- **Deleted:**
  - `app/Helpers/{CommonHelper,XCommonHelper,XpricingHelper,date-format}.php` (`app/Helpers/` is gone);
  - `app/Models/Admin/EmpPostAssignment.php`.
- **New:** `app/Support/helpers.php` (`site_date()`), loaded through composer `autoload.files`; the `require_once` in `AppServiceProvider` is removed.
- **Service reads added:**
  - `OrgService::branchRows/locationRows/locationsByState/serviceBranches`.
  - `VehicleService::segmentOptions/modelOptions/modelOptionsFor/variantOptions/colorOptions`.
- **Callers rewired:**
  - `BookingCrudController` (about 22 calls and the dropdown AJAX endpoints);
  - `Booking{Insurance,Rto,Finance,Exchange,Kyc}Service`;
  - `SpareRequestCrudController` (its create screen no longer crashes, BUG-030 part).
- **Behaviour:** booking colour dropdowns now list the variant's colour rows instead of the retired colour table. Everything else keeps the same shapes.
- **Models:** see DEC-060 (Post relations, Booking::segment, Variant options, unused missing imports). Also `RBACService` / `OrgService` retired-Posts paths.
- **Bugs:** BUG-180 logged.
- **Smoke:**
  - As user 1: the booking models/variants/colors/branch-locations/locations endpoints, booking create/edit, the insurance/rto/finance/exchange edits and spares create all return 200.
  - As user 40: 403.
## Platform core: Settings, Notify, Chat, Docs (DEC-061)
- **Migrations (local, then `xlrm_testing`):**
  - `2026_09_28_100000_platform_permissions`: UTL processes plus `UTL_*` codes; everyday codes go to all 76 designations in one bulk insert.
  - `2026_09_28_100100_platform_core_tables`: `setting_scope`, `noty_dispatch`, `comm_subscription`; inbox `kind` / `dispatch_id` / `archived_at`; thread `kind` / `is_internal` / `edited_at`; the docs library columns (documentable nullable).
- **New services** (`App\Services\Platform\*`): `Settings\SettingsService`, `Notify\{NotifyService, PendingNotification, Audience}`, `Chat\ChatService`, `Docs\DocsService`.
- **Other new code:**
  - `App\Support\{Result, Facades\*}`, `App\Events\Platform\*`, `PlatformServiceProvider`.
  - Jobs `SendPushNotification` and `PurgeDeletedDocuments` (daily 02:30).
  - Commands `settings:cache` / `settings:clear`.
- **Adapters** (before → after):
  - `EntityHistoryService`, `NotificationService` and `DocService` were standalone implementations, broken in places (BUG-139, BUG-181). They are now thin adapters over the platform services; v1 routes and envelope are unchanged.
  - `HasCommunications` delegates to Chat. `HasDocuments` is new.
- **Models:**
  - `Document` / `DocAccess` / `DocGroup` → the real `xlr8_utils_docs_*` tables and pivot.
  - New `NotificationsMaster`, `NotificationDispatch`; `User::getOrCreateNotificationsMaster()`.
  - `Notification` / `Alert` gain the new fillable columns.
  - `CommThread` relations are typed; `is_internal` / `edited_at` casts.
  - `SystemSetting::flushCache` also forgets the row cache.
- **Admin** (`routes/backpack/utils.php`; views under `admin/utils/platform`):
  - My inbox (`utils/inbox`: tabs N/A/M × Inbox/Unread/Read/Archive, mark, open → deep link).
  - Documents (`utils/docs`: library with path facets, upload / info card, my uploads, cart → pack, pack zip, download through `canView`).
  - Settings (`utils/settings`: grouped, search, typed edit, secrets masked and kept when left blank, scoped overrides, reset).
  - Chat endpoints for `<x-chat.composer>`.
- **Components:** `x-notify.bell`, `x-chat.thread`, `x-chat.composer`, `x-docs.uploader`, `x-docs.preview`, `x-docs.library-path`.
- **Topbar:** the three dropdowns showed hard-coded sample data; they now render `x-notify.bell` for A/N/M. The dummy "Read" script in `theme-tabler/inc/menu.blade.php` is removed.
- **Menu:** the Utilities dropdown gains My Inbox (all users), Documents (`UTL_DOCS_VIEW`) and Settings (`UTL_SETTINGS_VIEW`). Keyword Master / Key Values stay under `UTL_SETTINGS_VIEW`.
- **Fixes found while verifying:**
  - Settings seeds were read with `config("platform.settings.{$key}")`, which fails for dotted keys. They are now read by exact key; a declared seed type wins over a bare `string` row.
  - `fileError()` passed `''` as the default, so the MIME allow-list was skipped.
  - Info-card HTML attributes (e.g. `onclick`) are now stripped.
  - A record-attached document without entitlements now follows its record, not `UTL_DOCS_VIEW`.
  - `NotifyService` READ/UNREAD restore archived rows.
  - v1 `DocController`: upload no longer 500s when there is no entity; the add-to-group rule uses the real table; the zip return type is fixed.
  - v1 `EntityHistoryController::addThread`: `parent_id` is optional.
  - v1 `NotificationController`: `sender:id,name` → `id,username,person_code` (`users` has no `name`; the lists 500'd when a sender existed).
  - `DocService::getAnalytics` reads OwenIt audits.
- **Bugs:** BUG-139 and BUG-181 fixed; BUG-182 logged (v1 history/docs record access, needs owner approval).
- **Verification:**
  - `php -l` and Pint on all touched files; scoped PHPStan shows no new actionable errors (the remaining ones are Sprint 3–5 classes and model-property noise).
  - 49/49 functional checks on `xlrm_testing` (settings scope/type/reset, notify idempotency / self / audience / mark, chat event / remark / edit / delete / access, docs attach / entitle / library / card / cart / pack / zip, legacy adapters).
  - v1 API, 15 endpoints as users 1 and 40: all 200/201 with the unchanged envelope.
  - Admin GET smoke: inbox / docs / settings return 200 for user 1; user 40 gets 200 on inbox / docs and 403 on settings.

## Task and Ticket utilities (DEC-062)
- **Migrations (local, then `xlrm_testing`):**
  - `2026_09_28_110000_platform_task_ticket_tables`: `xlr8_utils_task`, `_task_person`, `xlr8_utils_ticket`, `_ticket_person`, `_ticket_counter`.
  - `2026_09_28_110100_platform_task_ticket_keywords` (through the keyword services, idempotent):
    - `TASK_TYPE` gains `ASSIGNED_TASK` / `SELF_TASK` (GENERAL / SELF are kept and treated as ASSIGNED / SELF).
    - New `TICKET_CATEGORY` (6 values) and `TICKET_PRIORITY` (P1–P4).
- **Services:**
  - `App\Services\Platform\Task\TaskService`: `create`, `update` (people rebuild plus added / removed notices), `followUp` (rights matrix, FORBIDDEN_TRANSITION), `inbox` / `inboxCounts`, `get` (role, rights, deadline math; UNAUTHORISED strips data), `delete`, `rights`, `role`.
  - `App\Services\Platform\Ticket\TicketService`: `open` (numbering with `lockForUpdate`), `transition` (legal edges plus roles, force-close reason, SLA pause / resume), `update` (priority change recomputes SLA), `remark`, `inbox` (4 plus QUEUE), `get`, `rights`, `flagBreaches`, `autoClose`, `report`, `sla`.
- **Models:** `Utilities\Task\{Task,TaskPerson}` and `Utilities\Ticket\{Ticket,TicketPerson}`, with Chat and Docs traits and `chatCanView` / `chatCanRemark` (snoopers read only).
- **Other code:**
  - Events `TaskChanged` and `TicketChanged`.
  - Jobs `FlagTicketSlaBreaches` (hourly) and `AutoCloseResolvedTickets` (03:00).
  - `OrgService::teamOptions()` (people picker).
- **Screens** (`routes/backpack/utils.php`, `admin/utils/platform/{tasks,tickets}`):
  - Task inboxes, create / edit, and a view with follow-up, timeline and attachments.
  - Ticket inboxes plus desk queue, open, view (transition, reply, desk management, SLA badge) and report.
  - Components `x-task.inbox`, `x-task.composer`, `x-ticket.inbox`, `x-ticket.sla-badge`.
  - Menu: Tasks (`UTL_TASK_VIEW`) and Tickets (`UTL_TCKT_VIEW`).
- **Fix to DEC-061 Settings:** a declared config seed now wins over the caller's fallback in `get()` / `flag()`. `flag('ticket.autoclose_enabled')` returned the `false` fallback even though the seed is `true`.
- **Verification:**
  - 66/66 Task / Ticket functional checks on `xlrm_testing` covering:
    - SELF / ASSIGNED rules, group flag, distinct per-role copy, the four inboxes;
    - every rights-matrix cell exercised; UNAUTHORISED; people rebuild with no orphans; soft delete keeps the timeline;
    - ticket number / sequence, SLA 8h / 4h, pause extends due time, breach flagged once, resolve / reopen / close, force-close needs a reason, auto-close, report.
  - Sprint 2 checks still 49/49.
  - GET smoke:
    - Live, all screens: 200 for user 1. User 40 gets 403 on the desk queue and the report.
    - `xlrm_testing` show pages: owner 200; outsider and snooper-less 403; edit owner-only.
  - Pint clean; PHPStan clean apart from the pre-existing untyped `User::employee()`.

## Approval engine, topics / rules / power sheet / reports (DEC-063)
- **Migrations (local, then `xlrm_testing`):**
  - `2026_09_28_120000_approval_engine_tables`: `xlr8_approval_{topic, rule, rule_level, request, counter, event}`.
  - `2026_09_28_120100_approval_topic_seed`: a starter tree from the FRS examples, with no rules: DISCOUNT.EXTRA `extra_disc`, ACCESSORIES.PACK `apack_disc`, INSURANCE.WAIVER `ins_waiver`, RTO.EXEMPT `rto_exempt`, DOCS.APPROVAL, COMMS.TEMPLATE.
- **Models:** `App\Models\Approval\{ApprovalTopic, ApprovalRule, ApprovalRuleLevel, ApprovalRequest, ApprovalCounter, ApprovalEvent}`. The request has Chat and Docs and access through `canSee()`.
- **Services (`App\Services\Platform\Approval`):**
  - `Entities\ApprovalTopicService` and `Entities\ApprovalRuleService` (the DEC-050 write path; levels are validated against the designation master and written with their rule).
  - `TopicService` (`Topics::resolve` / tree) and `RuleService` (`Rules::match`: deepest node, then specificity weights, then latest id; effective-dated).
  - `ApprovalService`: open (snapshot), counter (visibility, own level, max, LINEAR turn), effective (highest level, then latest), reviseAsk (stale counters), close, visibleTo, inbox (TO_ACT / RAISED / TEAM / CLOSED), authorize, preview, panel, auto-accept (setting).
  - `PowerSheetImportService` (header synonyms, dry-run, purge-replace per topic, error rows).
  - `ApprovalReportService` (projection plus counters only).
- **Other code:** facade `Rules`; event `ApprovalChanged`; `App\Exports\Platform\RowsExport`.
- **Screens:**
  - `utils/approvals` inboxes, raise form, request view (`<x-approval.panel>` plus timeline).
  - Admin topics (`<x-approval.topic-tree>`), rules and rule form with levels, power-sheet import (template / dry-run / apply / error sheet), simulation.
  - Report with xlsx export. Menu: Approvals (`UTL_APPR_VIEW`).
- **Removed:** `App\Services\ApprovalService` (legacy graph approve / reject: no callers, no routes) and its `AppServiceProvider` binding. `App\Models\Core\{ApprovalHierarchy, GraphNode, GraphEdge}` are also dead (their tables do not exist) and are left for owner sign-off.
- **Models:** `User::employee()` / `person()` now declare their `BelongsTo` return types.
- **Bugs:** BUG-183 logged (36 employees on designation codes missing from the master).
- **Verification:**
  - 53/53 functional checks on `xlrm_testing`:
    - precedence (item > main, model > segment + branch, non-matching dimension excluded, tie → latest, expired skipped);
    - every FRS §7.5 example, UC-APR-1 / 3 / 5, two topics independent, LINEAR turn, auto-accept flag, snapshot freeze;
    - import dry-run / apply / purge-replace / skip-bad-topic; report totals and levels.
  - GET smoke: every approval screen returns 200 for user 1. User 40 gets 403 on admin / report and on requests they cannot see; requester and level holder get 200.
  - Pint clean. PHPStan: nothing new beyond Larastan not resolving `User` relations (pre-existing).

## Regression fixed: `User::employee()` / `person()` (commit 078ef47)
- **Before:** `ce3704b` added `BelongsTo` return types without importing the class, because the `sed` did not match a CRLF line. The hint resolved to `App\Models\BelongsTo`, so every `$user->employee` / `->person` call threw a TypeError.
- **After:** `Illuminate\Database\Eloquent\Relations\BelongsTo` is imported. Found by the Sprint 5 functional run and fixed as its own commit.
- **Lesson:** shell `sed` on CRLF files can silently not match; edits are now verified by grep or made with the editor. Every earlier `sed` edit this session was re-checked.

## Comms plane: Templates, outbox, Email / SMS / WhatsApp / Telephony (DEC-064)
- **Migrations (local, then `xlrm_testing`):**
  - `2026_09_28_130000_comms_plane_tables`: `xlr8_comm_{template, template_version, outbox, sandbox, consent, suppression, otp, wa_thread, wa_message, call, webhook_event}`.
  - `2026_09_28_130100_comms_seed`:
    - System templates `notify.generic` for EMAIL / SMS / WHATSAPP, `otp.sms` and `sms.stop.ack`, seeded ACTIVE and marked `is_system`.
    - KeyValue `CALL_DISPOSITION`.
- **Models:** `App\Models\Comms\{CommTemplate, CommTemplateVersion, CommOutbox (payload encrypted), WaThread, WaMessage, CommCall}`.
- **Services:**
  - `Platform\Templates\TemplateService`: get, render, renderVersion / preview, saveDraft, submit (approval `COMMS.TEMPLATE`), approveDirect, activate, seedSystem, export, import, diff, usage ledger.
  - `Platform\Comms\{ContactService, OutboxService, EmailService, SmsService, WhatsAppService, TelephonyService, CommsRouter}`.
  - Drivers: `ChannelDriver` / `TelephonyDriver` interfaces, `DriverRegistry`, `SandboxDriver` (OTP masked), `LaravelMailDriver` (the only `Mail::` use), `SandboxTelephonyDriver`.
- **Jobs and events:**
  - Jobs `SendOutboxMessage` (queued, backoff) and `FlagMissingCallRecordings` (every 15 minutes).
  - Events `OutboxAccepted`, `CallRecorded`, `ChannelLinked`. An `ApprovalChanged` listener approves template versions.
- **API:** `POST /api/webhooks/comms/{email|sms|whatsapp|telephony}`, HMAC signed with `comms.webhook_secret` and idempotent on `event_id`. `CommsWebhookController` redacts media from its log.
- **Screens and components:**
  - Screens: templates (list, editor, versions, diff, preview, submit / approve / activate, JSON import / export), outbox + sandbox viewer + resend, WhatsApp inbox, call log with dispositions and recording play / download (download gated).
  - Menu entries gated by `UTL_TPL_VIEW`, `UTL_COMM_VIEW`, `UTL_COMM_WA_INBOX`, `UTL_COMM_CALL`.
  - Components `x-template.preview`, `x-telephony.click-to-call`, `x-whatsapp.{inbox, thread, composer}`, `x-email.send-panel`.
- **Config:** entity types TEMPLATE / WA_THREAD / CALL. New settings `mail.redirect_to`, `mail.allowed_from`, `sms.dlt_required`, `sms.default_header`, `whatsapp.session_hours`, `comms.max_attempts`.
- **Note:** the local `.env` mailer is a real SMTP host (`mail.xceler8.in`), not Mailpit. Every verification used the `log` driver or `Mail::fake`; `mail.redirect_to` exists as a dev safety valve.
- **Verification:**
  - 54/54 functional checks on `xlrm_testing` (queue sync; mail log or fake). FRS acceptance items covered:
    - #9: Notify EMAIL options → one outbox row → resend after a driver swap with the same snapshot.
    - #11: template outside the window works; free-form returns SESSION_CLOSED.
    - #12: inbound WA image → Docs row in the thread.
    - #13: dial → call row → recording Doc → `CALL_RECORDED` on the Enquiry.
    - #14: a draft cannot be sent; activating v2 leaves v1 snapshots untouched.
    - #15: SMS and WA STOP flip consent and are honoured on the next send.
  - Also checked:
    - templates: approval-driven APPROVED, HTML escaping, render errors;
    - email: FROM allow-list, suppression → sent 0, missing attachment, idempotency, BCC kept out of the timeline, PII masked;
    - SMS: DLT and consent rules; OTP hashed, never in the outbox or sandbox, rate-limited, not resendable;
    - WhatsApp: poll degrade and parsed answer; CHANNEL_LINKED and inbound events on the record;
    - webhooks: bad signature 401, duplicate event.
  - GET smoke: every new screen returns 200 for user 1 and 403 for user 40.
  - Pint clean. PHPStan: two cosmetic notes only.


## Platform integration: acceptance pack, seeds, rules (DEC-065)
- **Tests** (`tests/Feature/Platform/`):
  - `PlatformAcceptanceTest`: FRS §11 items 1–15; item 10 skipped with its reason (no real SMS vendor driver yet).
  - `ApprovalServiceTest` (§7.5 decisions, precedence, snapshot, auto-accept), `TaskServiceTest` (rights matrix, inboxes, people rebuild), `TicketServiceTest` (numbering, SLA and pause, edges, breach once), `TemplateServiceTest` (escaping, render errors, fork), `CommsWebhookControllerTest` (401 / duplicate / 404).
  - Fixtures: `Concerns\PlatformFixtures`.
- **Migration:** `2026_09_28_140000_platform_entity_actions_keyword` (the Chat action vocabulary), run on `xlrm` and on `xlrm_testing`.
- **Settings:** an undeclared key takes its type from its first value. Before this, a new flag was typed `string`, so a boolean write failed validation and the flag never changed.
- **Rules:** new `.ai/rules/modules/platform.md`.
- **Test-DB incident:**
  - This session ran `testing:refresh-db` several times, against DEC-051's instruction. That copied the purged (empty) local vehicle masters over `xlrm_testing`, and 8 vehicle tests failed.
  - Fixed by reloading the five vehicle tables into `xlrm_testing` only from `storage/app/backups/xlrm-vehicle-masters-pre-purge-27-09-2026.sql`. Live `xlrm` is unchanged and still waits for the fresh import.
  - The platform rules now say to migrate the test copy in place.
- **Verification:**
  - Platform tests 44 passed / 1 skipped.
  - Full suite 333 passed / 2 skipped / 0 failed.

## UI standards: shared layer, platform screens, developer guides (DEC-066)
- **Rules:** `.ai/rules/ui.md` gains four recorded standards and a shared-layer section (synced to `.claude/rules`).
- **New files:** `public/js/xl-ui.js`, `public/css/xl-ui.css`; components `x-ui.date`, `x-ui.select`, `x-ui.upload`.
- **Config and wiring:**
  - pinned flatpickr / Select2 in `config/backpack/{ui,theme-tabler}.php`;
  - meta tags (site formats, upload limits) and the AG-Grid date hook in `vendor/backpack/ui/inc/header_metas.blade.php`.
- **Dates:**
  - `DateFormatService` gains datetime and ISO formats; helper `site_datetime()`; directive `@sitedatetime`.
  - `AppServiceProvider` points Backpack's date formats at the setting.
  - 28 non-Sales views now display dates through `site_date` / `site_datetime`, including the receipt show fields.
- **Notification centre:** `x-notify.bell` rewritten as a single bell with tabs, mark-read and an empty state; `topbar_right_content` renders it.
- **Platform screens:**
  - 26 files use the components (no list boxes, native pickers, bare file inputs, fixed widths or `bg-white`).
  - The chat timeline and WhatsApp bubbles are redesigned.
  - The docs uploader uses an AJAX drop-zone with progress.
- **Guides:** `docs/utilities/` — README, one per utility (01–13) and `ui-kit.md`.
- **Verification:**
  - Every Blade template compiles (`view:cache`).
  - The 22 platform screens return 200 or 403 as expected for users 1 and 40.
  - Headless Chrome at 390, 768 and 1366 px:
    - Select2 and site-format flatpickr render;
    - the drop-zone previews a file and removes it before upload, and refuses a second file on a single-file input;
    - the bell panel renders;
    - a legacy screen (user create) is enhanced with no JS errors.

## Tabler-parity shell, theme settings, AG-Grid theming, dev UI kit (DEC-067)
- **Root causes of "mode / colour switch doesn't work":**
  - `layouts/horizontal` hard-coded `#FFFFFF !important` / `#F4F2EE`.
  - The user block used inline hex colours.
  - AG-Grid v36 (unversioned CDN) themes itself in JS, so the CSS-variable mapping never reached it.
  - Its UMD exports are getter-only, so the DEC-066 `createGrid` hook never actually ran: it assigned into a read-only export. As a result, grid date columns were **not** site-formatted either.
  - Legacy `ag-theme-quartz.css` overrode the JS theme.
  - Backpack's dark adjustments used a fixed blue for inputs and checkboxes.
- **Theme:**
  - New `inc/theme_styles` override: a render-blocking mode and theme bootstrap (resolves "system", no white flash), plus Tabler 1.4 `tabler-themes.min.css` (pinned, SRI, Basset) and `public/css/xl-theme.css`.
  - New `public/js/xl-theme.js` (`XL.theme` API) and the `inc/theme_settings` Appearance off-canvas: mode, 12 primary colours, base, font, radius, layout, reset.
- **Layout:**
  - `xl_layout` cookie (unencrypted, whitelisted in `bootstrap/app.php`) read by the new `App\Http\Middleware\ApplyUiPreferences` (added to `backpack.base.middleware_class`). Allowed: `horizontal` (default), `vertical`, `vertical_dark`.
  - New overrides `layouts/vertical`, `layouts/vertical_dark`, `layouts/_vertical/menu_container`: sidebar, top header, dark sidebar via `data-bs-theme="dark"`.
- **Shell:**
  - `layouts/horizontal` has no hard-coded colours.
  - `inc/menu` gains an Appearance button.
  - `inc/menu_user_dropdown` is a Tabler user block: photo over initials, online dot, name / designation, and a menu with header, account, inbox, tasks, appearance, UI kit (dev) and log-out.
  - New partial `inc/appearance_button`.
- **AG-Grid (`header_metas`):**
  - The hook now wraps a copy of the module, and applies a Quartz Theming-API theme whose parameters are Tabler variables to every grid without its own theme.
  - It disables the legacy `styles/ag-theme-*` sheets.
  - `xl-ui.js` re-assigns the wrapped module when late.
  - Verified on the legacy branch list: dark mode, orange primary, centred headers kept.
- **Dark-mode safety net** in `xl-theme.css`: `bg-white`, `bg-light`, `text-black`, `text-dark`, `table-light`, common inline light backgrounds and dark text, and Backpack input / checkbox colours.
- **Dev UI kit:**
  - `routes/backpack/dev.php` and `App\Http\Controllers\Admin\Dev\UiKitController`, gated by `config('platform.dev_ui_kit')` (env `XL_DEV_UI_KIT`, default local only).
  - Views `resources/views/admin/dev/ui/{_layout,index,forms,lists,elements,dashboard,chat,pages}.blade.php`.
  - ApexCharts 3.54.1 (pinned, SRI) on the dashboard only.
- **Rules / docs:** `.ai/rules/ui.md` (AG-Grid and theme bullets, synced), `docs/utilities/ui-kit.md` (theme section).
- **Tests:** `tests/Feature/Admin/UiKitAndLayoutTest` (5): every page renders when enabled, 404 when disabled, login required, layout cookie honoured, unknown layout ignored.
- **Verification:** headless Chrome at 1366 px and 390 px:
  - light and dark;
  - purple / teal / red / green / orange primaries;
  - serif font and radius 1.5;
  - top-menu, sidebar and dark-sidebar layouts;
  - the Appearance panel;
  - the legacy branch grid.

## Design work parked; utility developer guides completed (docs only)
- **Design progress record:** new `docs/refactor/ui-design-progress.md`.
  - What DEC-066 / DEC-067 delivered, with file lists and how it was verified.
  - The resume list for after the Sales merge: pin AG-Grid in about 86 views, convert the Sales views, remove hex and inline styles, a real dashboard, and the logo / avatar checks.
  - How to verify.
  - `.ai/state/current.md` points to it and was trimmed to stay within 50 lines.
- **Guides (`docs/utilities/`):**
  - New `14-cookbook.md`: wiring a module to every utility end to end (hypothetical JobCard) plus a checklist.
  - New `15-testing.md`: testing code that uses the utilities (sync queue, sandbox rows, fixtures, idempotency, time travel, event / push faking, webhooks, templates, approvals).
  - New `16-reference.md`: every Result code with its meaning, events and payloads, Chat action codes, jobs and schedule, the settings seed pack, `UTL_*` permissions, webhook rules.
  - Guides 01–13 each gain an "Events & testing" section, and guides 02–06 gain extra use cases.
  - The README index lists 14–16.
- **Corrections found while checking the guides against the code:**
  - Notify quiet hours **skip** push (the guide said "held back").
  - `docs.allowed_mimes` is a list of file **extensions**.
  - Tasks' `create` can also return `INVALID_OWNER`; the `open` inbox filter was undocumented.
  - A ticket's default priority is P3, and a P1 alerts the desk.
  - The chat component's `filter` / `allowInternal` options were undocumented.
  - The outbox derives an idempotency key when none is given, so identical re-sends are duplicates.
- **Rules:** `.ai/rules/modules/platform.md` points to the guides (synced to `.claude/rules`).
- **Verification:** no code changed. Every API, code, event, setting and permission in the new pages was checked against `app/Services/Platform/*`, the events, jobs, `config/platform.php` and the permissions migration.

## Developer guides for all models and services; doc-sync rules (DEC-068, step 1)
- **New `docs/domains/`:**
  - `README`, `core`, `org`, `person`, `iam-auth`, `hr`, `vehicle`, `pricing`, `crm-enquiry-quotation`, `sales-booking`, `accounts`, `spares`, `utils-legacy`, `api-v1-adapters`.
  - `reference.md`: 127 models → table → writer → guide, generated from the code.
  - Written from a reflection inventory of every project-defined public member. A coverage script checked 916 members of services, models and traits: **0 missing**.
- **Links:** `docs/index.md`, `docs/utilities/README.md`, `.ai/rules/services.md`.
- **Rule corrections:** the rules and `services.md` named a non-existent `PricingEngineService::getPricing()`. The real call is `getPricingPayload($oemCode, $options)`. Fixed in `services.md`, `modules/vehicle-pricing.md` and `modules/sales.md`.
- **Rules recorded on request:** `.ai/rules/app.md` (`app/**`) and `.ai/rules/components.md` (components, `xl-*` assets, `config/platform.php`). Guides must change with the code; synced to `.claude/rules`.
- **Bugs found while documenting** (logged, not fixed; the booking and auth ones need their owners):
  - BUG-184: audit-detail helpers always report "System".
  - BUG-185: `onlyRestored()` never matches.
  - BUG-186: `OrgService::variantName()` throws a TypeError.
  - BUG-187: v1 auth returns null name / email / mobile.
  - BUG-188: login OTP generated with `rand()`.
  - BUG-189: AuthService logs full mobile numbers.
  - BUG-190: legacy `RBACService` is unused and broken.
  - BUG-191: Booking scopes and count helpers query `xcelr8_*` tables.
  - BUG-192: `Enquiry::quotations()` uses the wrong key.
  - BUG-193: booking ↔ exchange / finance relations use `booking_id` instead of `bid`.

## Merge dev/admin → stage (DEC-068, step 2)
- **Base:** `stage` was fast-forwarded to `26ab25b` (Sales team, 27-09: body_type column, OTF branch picker, import / list / quotation view changes), then `dev/admin` (`9910199`) was merged with `--no-ff`.
- **Conflicts:** no textual conflicts; `BookingCrudController.php` auto-merged.
- **Fixes made inside the merge** (so `stage` never breaks):
  - `BookingOtfService::resolveOtfFormData()` called the deleted `CommonHelper::getBranches()`. It now calls `OrgService::branchRows()`, which has the same shape.
  - `generateVotfNumber(Booking, string $branchCode)`:
    - removed the stale comment and the duplicated empty check;
    - the Sales tests still asserted the old enquiry / FSC fallback, so they were rewritten for the new contract (selected branch; blank → `InvalidArgumentException`).
  - The Sales migration `add_body_type_to_xlr8_booking_master` is now guarded with `Schema::hasColumn` (up and down), as the database rules require.
- **Migrations:** run on `xlrm` and `xlrm_testing`; `body_type` is present on both.
- **Pint:** reformatted `BookingOtfService` (style only).
- **Guides:** `docs/domains/sales-booking.md` updated for the new VOTF contract and the booking branch columns: bookings have no `branch_code` / `location_code`, so `Booking::branch()` / `location()` are always null.
- **New bug:** BUG-194. `BookingCrudController::fetchPendBkData()` / `fetchCbrData()` use `Cache` without importing it. This was pre-existing on every branch; the fix is scheduled in step 4.
- **Verification:**
  - Before the test fix, the full suite had 2 failures, both the outdated VOTF tests; OTF tests now 5 / 5.
  - Smoke group: the known "1 risky".
  - HTTP smoke as users 1 and 40:
    - the booking list, create, OTF list, pending KYC / DMS / insurance / deliveries, quotation create, sales import, receipts and dashboard return 200;
    - they return 403 for user 40, who has no booking permissions;
    - OTF for a booking without a quotation returns its `quotation_missing` gate.

## Sales / booking parity — backend (DEC-068, step 4a)
- **History:** 32 `addHistory('commented', …)` calls (10 booking services and the booking controller) became `$booking->recordEvent(ACTION, $title, $meta, $body)`.
  - Actions are registered codes instead of the unregistered `COMMENTED`: `CREATED`, `STATUS_CHANGED` (hold / resume / restore / dummy→active / refund moves) and `UPDATED`.
  - The timeline content is the same; the label now names the kind of change.
  - `HasCommunications::recordEvent()` gains an optional `$body` (backward compatible).
- **Privacy:** the KYC history meta now masks the Aadhaar (`XXXXXXXX1234`). BUG-195 is fixed for new entries; masking existing rows needs approval.
- **BUG-194 fixed:** `Cache` facade imported in `BookingCrudController`.
- **Dates:** 13 display dates in Sales PHP now use `site_date()` / `site_datetime()`: campaign list, enquiry list (IST kept), booking change logs, refund details.
- **Chat:** `Enquiry`, `Lead`, `Quotation` use `HasCommunications`. The entity registry already had `QUOTE`, `ENQUIRY` and `BOOKING`, and their deep links resolve.
- **Not changed, on purpose:**
  - Proof uploads stay on the satellite models' own media collections. Moving them to Docs needs a data migration and reader changes, so it is a follow-up.
  - The two importer `Keyvalue::` reads stay: an uncached lookup before a write avoids duplicates.
  - No `NotificationService` use existed in Sales.
- **Guides and rules:** `docs/domains/{core,sales-booking,crm-enquiry-quotation}.md`, `docs/utilities/03-chat.md` and `.ai/rules/modules/sales.md` (history API, VOTF branch).
- **Verification:**
  - lint, Pint;
  - PHPStan: no runtime-risk findings left in the touched files;
  - Sales service and platform tests: 99 passed, 1 skipped.

## Sales / booking parity — visual (DEC-068, step 4b)
- **Scope:** Sales, import and accounts views (`admin/pdf/*` exempt; paper output).
- **Libraries:**
  - AG-Grid pinned to `ag-grid-community@36.2.0` in 49 views.
  - Removed: 51 legacy `ag-theme-quartz.css` links, 17 `ag-grid-tabler-theme.css` links, and per-view flatpickr (35) and Select2 (20, including the 4.0.13 and bootstrap-5 theme) tags. The global pinned copies load first.
  - The `bootstrap-5` Select2 theme option was dropped: `xl-ui.css` styles the default theme with tokens.
- **Dates:**
  - `header_metas` defines `XL.dateFormat`, `XL.dateTimeFormat` and `XL.flatpickrFormat(withTime)` synchronously, so view scripts can use them before `xl-ui.js` loads.
  - 13 view pickers gained `altInput` in the site format; their submitted `dateFormat` is unchanged (controllers parse it). Three hard-coded `altFormat: "d-M-Y"` became the site format.
  - 23 display dates now use `site_date()` / `site_datetime()`. Picker `value=` attributes keep their picker's format.
  - Two native date inputs: OTF voucher date (the view's own picker) and lead expected delivery (`<x-ui.date>`).
- **Colours:**
  - Classes: `bg-white` → `bg-surface` (102), `bg-light` → `bg-surface-secondary` (31), `text-dark` / `text-black` → `text-body` (94).
  - 284 hex colours (CSS declarations, inline styles, jQuery `.css()` and `.style` writes) → Tabler variables, mapped by property:
    - backgrounds → surface tokens;
    - text → body / secondary / status tokens;
    - borders → the border token (black document lines → `--tblr-body-color`);
    - status tints → `rgba(var(--tblr-*-rgb), a)`.
  - `@media print` blocks untouched. The inline SVG file icon is kept.
  - SweetAlert button colours → `XL.theme.token(...)`.
- **Bug fix in `xl-ui.js`:** selects hidden by the page (`display:none` / `hidden`) are no longer wrapped in Select2. Before, OTF's custom accessories picker got a visible duplicate.
- **Left, on purpose:**
  - File inputs and multi-selects are enhanced at runtime; converting them renders the same markup.
  - Small per-view `<style>` duplicates were not merged, to avoid selector collisions.
  - Fixed widths are left alone; the booking list toolbar clips at 390 px (noted in `ui-design-progress.md`).
- **Verification:**
  - `view:cache` OK.
  - Headless Chrome, JS-error capture: 0 errors on the booking list, booking add, OTF list, pending KYC, quotation create, enquiry create, receipt create, campaign create and lead create.
  - Screenshots: booking list and quotation (dark, teal), enquiry (dark), booking add (light, purple), booking list and add at 390 px.

## "Later" list cleared: booking proofs in Docs, AG-Grid pinned everywhere, phone toolbars (DEC-069)
- **Booking proofs → Docs.** Receipt `amount-proof`, finance `instrument_proof`, insurance `policy_copy`, RTO `trc_copy` /
  `tax_receipt_copy`, refund `acc-proof` / `aadhar` / `pan` / `pay-proof`, delivery photos and booking `chassis_image` are now
  Docs one-file slots on the record.
  - `DocsService`: `latestFor()`, `supersede()`. `HasDocuments`: `replaceDocument()`, `documentFor()`, `documentUrl()`,
    `hasDocumentIn()`, `removeDocuments()`. `DocsLibraryController::download` serves `?inline=1` previews.
  - `HasDocuments` + `chatCanView()` (`SLS_BKNG_VIEW`; receipts also `ACC_RCPT_VIEW`) on `Bookingamount`, `XlDelivery`, `XlRto`,
    `Xl_Refunds`, `XFinance`, `XlInsurance`; `Booking` uses the BOOKING entity.
  - Writers: `BookingCrudController`, `EnquiryCrudController`, `Booking{Delivery,Finance,Insurance,Otf,Refund,Rto}Service`
    (before: `addMedia()` / temp `public/Uploads` moves; after: `replaceDocument()` with the form field as error key).
  - 13 views: `getFirstMediaUrl` / `getFirstMedia` / `hasMedia` → `documentUrl` / `documentFor` / `hasDocumentIn` (42 places).
  - Migration `2026_09_28_150000_move_booking_proofs_to_docs` re-points the existing media rows to new documents (no file
    moves; reversible, original owner kept in `tags.migrated_from`). Run on `xlrm` and `xlrm_testing`: 91 documents each;
    rollback and re-apply verified. Backup: `storage/app/backups/*-media-docs-pre-DEC069-28-09-2026.sql`.
  - BUG-196 (policy copies under the misspelled `…\Insurance\Xlinsurer`) fixed by the migration.
  - Security gain: proofs are no longer public `/storage` URLs; every link checks access.
- **AG-Grid:** the remaining 37 views pinned to `ag-grid-community@36.2.0` (all 87); legacy grid CSS links dropped.
  `public/css/ag-grid-tabler-theme.css` was unused and is deleted (approved 28-09).
- **Phone toolbars:** `xl-ui.css` lets the `#quickFilter` group take the row and the box shrink below 768px (~85 list views had
  fixed 220–360px widths); booking list header wraps and its status select lost its fixed width. Verified at 390px.
- **Other:** `Document` model `@property` docs; stale `BookingCoreService::store()` docblock corrected.
- **Tests:** 4 unit tests updated to the Docs API; new `tests/Feature/Platform/BookingProofDocsTest.php` (supersede, invalid file →
  field error, inline preview, 403 without booking access).
- **Guides:** `docs/utilities/04-docs.md`, `ui-kit.md`, `docs/domains/{sales-booking,accounts,core}.md`, `ui-design-progress.md`.

## Sprint summary 27–28-09-2026 (DEC-059 … DEC-069) — what `stage` gets in this merge
Range `6ccaf2a..dev/admin` (last stage baseline before the sprint): 491 files, +26k / −8k lines.

| DEC | Area | Delivered |
|---|---|---|
| 059 | Entity services | Seeders write through the entity services and are idempotent (last DEC-050 roll-out group). |
| 060 | Cleanup | `app/Helpers` removed; every caller goes through `OrgService` / `VehicleService` / other services. |
| 061 | Platform | Settings, Notify, Chat and Docs core (FRS §1–4): services, facades, `HasCommunications` / `HasDocuments`, screens. |
| 062 | Platform | Task and Ticket utilities with SLA (FRS §5–6). |
| 063 | Platform | Approval engine: topic tree, rules, power sheet, reports (FRS §7–8). |
| 064 | Platform | Comms plane: templates, outbox, Email / SMS / WhatsApp / Telephony (sandbox drivers) (FRS §12–17). |
| 065 | Platform | FRS acceptance pack, `ENTITY_ACTIONS` keyword seeds, platform rules. |
| 066 | UI | Project-wide UI standards; shared UI layer (`xl-ui.js` / `xl-ui.css`), `x-ui.date` / `select` / `upload`, site dates. |
| 067 | UI | Tabler-parity shell, Appearance panel (mode / colour / font / radius / layout), AG-Grid theming hook, dev UI kit. |
| 068 | Process | Developer guides for all models and services (`docs/domains/`), guide-sync rules; Sales team merge; Sales/booking parity (Chat history events, site dates, tokens, pinned libraries). |
| 069 | Sales / UI | Booking proofs → Docs (migration, access-checked links, BUG-196); AG-Grid pinned in all 87 views; phone list toolbars; unused `ag-grid-tabler-theme.css` deleted; fresh-clone boot verified. |

**Bugs closed this sprint:** BUG-139 (Docs model tables), BUG-194 (`Cache` import), BUG-195 (Aadhaar in KYC history, new entries),
BUG-196 (misspelled insurer media type). Open items are in `.ai/state/bugs-index.md`.

**Verification at merge:** full suite 342 passed / 1 skipped; `--group=smoke` admin sweep; docs coverage script 0 missing;
headless screenshots at 1366 / 390 px; fresh clone + `composer install` boots (615 routes, views compile, login 200).

**After pulling `stage` (dev team):**
1. `composer install`
2. `php artisan migrate` (adds the platform tables and moves booking proofs into Docs — reversible), then
   `DB_DATABASE=xlrm_testing php artisan migrate` for the test copy.
3. `php artisan optimize:clear`. No `npm` step.
4. New code: history via `$model->recordEvent()`, files via `Docs` / `HasDocuments` (`replaceDocument()` for one-file proofs),
   dates via `site_date()` / `@sitedate`, UI via `x-ui.*` components and Tabler tokens. Guides: `docs/utilities/`, `docs/domains/`.
