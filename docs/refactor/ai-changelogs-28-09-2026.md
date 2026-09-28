
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

## Bug-fix sprint, wave 1 — fixes without an owner decision (DEC-070)
Triage of every open bug against HEAD `0386230` (plan approved 28-09); decisions D1–D29 wait for the owner.

**W1–W2 — mobile login logging (BUG-189):**
- `OtpNotificationService`: the SMS placeholder logged the OTP itself (and again to `stack` in debug) — removed; emails and
  numbers in every log line go through `ContactService::mask()`.
- `AuthService`: log contexts mask the number (8 places); the "not registered" error no longer echoes it; missing
  `use Throwable;` added (its catch blocks never matched); the device-limit check throws `AuthenticationException`
  (the abstract `ApplicationException` could not be created). `Api/V1/AuthController` masks the number in exception context.
- Test: `tests/Feature/Api/OtpLoggingTest.php`.
- Still broken until D1: the user lookup (`users.mobile` doesn't exist, BUG-187).

**W3–W5 — core:**
- BUG-184: `BaseModel` audit helpers read `display_name` (test `tests/Unit/Models/BaseModelAuditDetailsTest.php`).
- BUG-185: unused, never-matching `scopeOnlyRestored()` removed.
- BUG-186: `OrgService::variantName()` returns the name (test `tests/Unit/Services/OrgServiceNameLookupTest.php`).
- Guides: `docs/domains/{core,org,vehicle}.md`.

**W6–W10 — booking:**
- BUG-192: `Enquiry::quotations()` joins on the enquiry `id`. BUG-193: `Booking::finances()` / `exchanges()`,
  `XExchange::booking()`, `XFinance::booking()` join on `bid`; missing imports added. Test `tests/Unit/Models/BookingRelationsTest.php`.
- BUG-102: `BookingExchangeService::apply()` stops sending nine vehicle fields the exchange table doesn't have
  (Eloquent dropped them); nothing stored changes.
- BUG-097: `BookingOtfService::apply()` saves under `Cache::lock('sales:booking:votf')` and rejects a VOTF number another
  booking holds (`votf_no` error, shown under the field in `otf-form.blade.php`); new `bookingHoldingVotf()`.
- BUG-195: the KYC history meta masks the PAN too (`XXXXXX234F`).
- Tests: `BookingOtfServiceTest` (+2), `BookingKycServiceTest` (+1); Sales + model unit tests 71 passed.
- Guides: `docs/domains/{sales-booking,crm-enquiry-quotation}.md`.

**W11–W14 — routes, imports, controllers:**
- BUG-168: Lead / Lead Source `search` / `details` routes carry `'operation' => 'list'` (`routes/backpack/core.php`).
- BUG-029 (part): `SalesImportController` stamps imports with the Backpack user instead of falling back to user 1 (4 places).
- BUG-179 (part): `AccessoryImportService::processRow()` no longer echoes / `print_r`s every row.
- BUG-008 / 020 / 021: Employee, Person Address and Person Banking controllers drop the unused Create / Update operations
  and `Person` import; the banking docblock describes the list-only screen. No files deleted.
- HTTP smoke (one request per process, `xlrm_testing`): user 1 → 200 on the three Org lists, lead, lead source, Imports → Sales,
  OTF list and OTF form; user 40 → 403 on each except Imports → Sales (open by BUG-177, decision D14).

**W15 — tracker accuracy:**
- Closed with evidence: BUG-019, 028, 030, 033, 045, 061, 106, 107, 109, 111, 119; BUG-090 marked a duplicate of 183.
- Triage notes and decision numbers on the open ones (009, 056, 062, 069, 083, 092, 095, 101, 122, 153, 161, 173, 177,
  178, 180, 182, 183, 188, 190, 191); BUG-106 / 109 got entries (they only had index rows).
- `AdminScreenSmokeTest::KNOWN_BROKEN`: `spares/spare-request/create` removed (it returns 200 now); the list cites BUG-031.
- `.ai/state/current.md` and the generated bugs index (`ai:refresh-context`): 35 open.

**W16 — UI debt:**
- Removed the Bootstrap-4 `.form-control:focus { border-color: #80bdff; box-shadow: … }` override from 69 non-Sales views
  (268 lines); inputs use Tabler's themed focus ring (follows the primary colour and dark mode).
- `menu_items.blade.php` inline `<style>` → `public/css/xl-theme.css` (shell section).
- Verified: `view:cache`; segment create in dark mode at 1366 px. Remaining hex in ~107 legacy views stays a follow-up.

**Index clean-up:** `RefreshAiContext` now treats `CLOSED…` and `DUPLICATE…` statuses as closed (before, only `FIXED` /
`WON'T FIX`), so the generated `.ai/state/bugs-index.md` lists only open work: **31 open** (was 55). BUG-031 / 032 / 116 /
154 / 085 got decision references; the state file's waiting list is the D1–D29 list.

## Sprint summary — bug-fix sprint wave 1 (DEC-070), 28-09-2026
Range `0386230..dev/admin` (on top of stage): 8 commits, 110 files, +725 / −670 lines. Not pushed.

| Result | Bugs |
|---|---|
| Fixed in code | 097 (VOTF duplicates), 102, 184, 185, 186, 189 (OTP in logs — security), 192, 193, 195 (PAN in new entries) |
| Fixed in part | 008 / 020 / 021 (controller leftovers), 029 (import actor), 168 (route keys), 179 (debug output) |
| Closed after triage (already fixed or superseded) | 019, 028, 030, 033, 045, 061, 106, 107, 109, 111, 119; 090 duplicate of 183 |
| Open, each with a decision id | 31 (see `.ai/state/bugs-index.md`) |

**New findings during triage:** mobile OTP login is broken (`users.mobile` doesn't exist — BUG-187, D1); the login OTP was
written to the log (fixed); BUG-178 also mis-scopes add-ons and discounts; BUG-153's endpoint now errors; 52 dead menu links.

**Tests:** +5 test files / cases (`OtpLoggingTest`, `BaseModelAuditDetailsTest`, `OrgServiceNameLookupTest`,
`BookingRelationsTest`, OTF duplicate + KYC masking cases). Full suite **353 passed, 1 skipped** (was 342 / 1).
HTTP smoke as users 1 and 40 on the touched screens. `--group=smoke` not rerun (no merge in this sprint).

**Owner decisions pending (D1–D29):** security / API (D1–D4), deletions (D5–D12), UAT-visible (D13–D17), business / data
(D18–D29) — full list with recommendations in `.ai/state/current.md` and the approved plan.

## Automatic user data scoping (DEC-071)
Plan: `docs/plans/2026-09-28-data-scoping-DEC-071.md`. User decisions 28-09: empty codes visible until backfilled, pickers
unscoped, department / division / vertical only where a column exists, bookings get their own codes.

- **Engine** (`app/Services/IAM/DataScope/`): `ScopeResolver` → `ScopeSet` (codes per level, `null` = unrestricted) from
  the user's active, in-date scope rows and the master trees in `config/data_scope.php` (Branch → Location,
  Department → Division, Segment → Sub-segment → Model → Variant, Vertical). A parent covers all children unless a child
  is assigned within the nearest assigned ancestor; `ALL` rows = no restriction; superadmin / bypass = everything.
  Masters cached 10 min, scopes memoised per request.
- **Filter:** `HasDataScope` trait + `DataScopeFilter` global scope → `DataScopeManager` (most specific column decides,
  empty value falls back upward, unassigned rows per `scope.unassigned_rows`; satellites `via` their parent; alias-safe;
  admin + API user; never in jobs / console). Opt-out: `withoutDataScope()`, `DataScope::off(fn, reason)`, route
  middleware `data-scope:off,<reason>`; raw queries `DataScope::apply()`. Settings `scope.enabled`, `scope.unassigned_rows`.
- **Scoped models:** Enquiry, Lead, Campaign, Quotation (via enquiry), Booking, Bookingamount, XFinance, XExchange,
  XlDelivery, XlInsurance, XlRto, Xl_Refunds (via booking).
- **Opt-outs added:** booking create duplicate checks, duplicate-enquiry check, receipt-number check, VOTF numbering /
  holder lookup. `withoutGlobalScopes()` in three booking lists (it also dropped the data scope) → only soft deletes lifted.
  Menu and highlight enquiry counts are cached per scope hash.
- **Booking codes** (D22 / BUG-161 / BUG-092): migration `2026_09_28_160000_add_scope_codes_to_xlr8_booking_master_table`
  (5 nullable code columns + 3 indexes; run on `xlrm` and `xlrm_testing`, rollback verified). `ScopeCodeFiller` fills empty
  codes on every Booking / Enquiry save (`saving` hooks) from enquiry, quotation snapshot, acting employee, masters.
- **Backfill:** `php artisan data-scope:backfill [--entity=] [--apply]` — report-only by default. Local report: bookings
  and enquiries have no source codes yet; 17,821 enquiries would get `BKN` from follow-up names; 6 follow-up location
  names need "Location" synonyms (RATANGARH RD, CHURU · NOKHA_SZZ · RAJGARH_SZZ · RATANGARH_SZZ · SHRIDUNGARGARH_SZZ ·
  SUJANGARH_SZ). Not applied.
- **Removed (replaced):** `DataScopeService`, `ScopedQuery`, `ScopedCrud` and their test; the dead id-based scope
  properties on Booking, Stock, XlSpareRequest (Stock / Spares not scoped until they store codes).
- **User screen:** read-only "Effective data access" panel (resolved codes per level).
- **Tests:** `ScopeResolverTest` (7), `DataScopeFilterTest` (9), `ScopeCodeFillerTest` (2); full suite 364 passed, 1 skipped.
  HTTP smoke (xlrm_testing) as superadmin and scoped user 4 (BKN / PV / NON-XUV): 13 Sales / Accounts screens and data
  endpoints 200; the generated SQL keeps BKN's 10 locations and PV NON-XUV variants.
- **Tracker:** BUG-136 FIXED, BUG-083 CLOSED (masters unscoped by decision), BUG-161 / BUG-092 FIXED; new BUG-197
  (division PRSNL under ADM while 42 users hold it with SLS).
- **Guides / rules:** `docs/domains/{iam-auth,core,sales-booking,crm-enquiry-quotation,spares,README,reference}.md`,
  `docs/utilities/{01-settings,16-reference}.md`, `.ai/rules/{services,modules/iam-rbac,modules/sales}.md`.
- **Data follow-up (user answers 28-09, local `xlrm` only; backups in `storage/app/backups/*DEC071*`):**
  - "Location" synonyms: NOKHA_SZZ → NOK, RAJGARH_SZZ → RJG, RATANGARH_SZZ → RTN, SHRIDUNGARGARH_SZZ → DNG,
    SUJANGARH_SZ → SUJ, "RATANGARH RD, CHURU" → RTN (written as a row: the service's CSV parser would split the comma).
  - `php artisan data-scope:backfill --apply`: 24,070 enquiries got `dealer_location` + `dealer_branch` (39.5%); bookings
    unchanged (no source codes). The rest stay unassigned (visible) until consultants' `mile_id` / vehicle masters exist.
  - BUG-197: division PRSNL moved to department SLS (`DivisionService`). Other environments need the same master edit
    and synonyms, then the backfill command.

## My Account rebuild (DEC-072, part 1)
- **Before:** stock Backpack page reading / writing `users.name` (no such column; the name was silently dropped), no
  photo, Gravatar avatar that never resolved (users have no email).
- **After:** `App\Http\Controllers\Admin\Account\MyAccountController` + `App\Services\IAM\MyAccountService` on the same
  route names (`routes/backpack/account.php`; `setup_my_account_routes = false`); view `admin/account/show.blade.php`:
  header (photo or initials, display name, designation under it, user type, employee code, primary branch, reporting
  manager), tabs Profile (personal info; edit display name; upload / remove photo with the shared drop-zone),
  Organisation & access (primary assignment, add-on scopes, effective data access), Employment history (timeline from
  `EmployeeJourneyService`), Contact, Security (current password checked; min 8 with letters + numbers; other sessions
  signed out). Username read-only. Display name / photo written through `PersonRecordService`.
- Top-bar avatar: `avatar_type = profilePhotoUrl` → `User::profilePhotoUrl()` (person photo, else initials) — D17 done.
- Shared partial `admin/org/user/_effective_access.blade.php` (User edit screen now uses it too).
- Removed: `resources/views/vendor/backpack/theme-tabler/my_account.blade.php` (replaced).
- Tests: `tests/Feature/IAM/MyAccountTest.php` (5). Verified: 200 for users 1, 4, 40; screenshots 1366 light and 390 px.

## Dynamic dashboard (DEC-072, part 2)
- **Before:** a static "My profile & access" card and a banner (`vendor/backpack/ui/dashboard.blade.php`); an orphan KPI
  view with every value hard-coded to 0 (`admin/dashboard.blade.php`, `admin/widgets/*`).
- **After:**
  - `config/dashboard.php` — 23 widgets in 5 groups (My work, Sales, Bookings & deliveries, Accounts, Vehicles & stock),
    each gated by a permission (no designation names).
  - `DashboardController::index()` renders only permitted cards; `widget($key)` (route `dashboard.widget`) re-checks the
    permission and returns JSON, cached 5 min per user + scope hash + period.
  - `App\Services\Dashboard\DashboardService` (one method per widget, all through data-scoped models or
    `DataScope::apply()`), `DashboardPeriod` (today / week / month / quarter / FY Apr–Mar).
  - Definitions agreed 28-09: open enquiries = stages Enquiry / Test Drive / Quotation / Booking / Postponed; aligned
    deliveries = invoiced bookings with `del_date` in the period (delivered vs pending).
  - View `admin/dashboard/index.blade.php` (greeting, period switcher, groups of KPI / chart / list cards, empty and
    loading states) + `public/js/xl-dashboard.js` (fetch per card, ApexCharts bound to Tabler tokens, rebuilt on theme change).
  - Migration `2026_09_28_200421_add_dashboard_indexes` (17 indexes on enquiries, follow-ups, test drives, booking
    satellites `bid`, receipts, booking status/date; reversible; run on both DBs). Follow-up widgets 2.1 s → 0.23 s.
- Removed (replaced): `vendor/backpack/ui/dashboard.blade.php`, `admin/dashboard.blade.php`, `admin/widgets/{stats-card,activity-feed}.blade.php`.
- Tests: `tests/Feature/Dashboard/DashboardTest.php` (4). Verified: page 200 for users 1 / 4 / 40; all 23 endpoints
  200 (catalogue 403 for user 4 without `VEH_VAR_VIEW`), 0.15–0.8 s each; screenshots.
- Guide: new `docs/domains/dashboard.md`. New BUG-198 (permission cache rebuild ~10 s).

## Pricing redesign — Phase 1 foundations (DEC-073)
Plan: `docs/plans/2026-09-28-pricing-redesign-DEC-073.md` (12 phases; user decisions recorded in DEC-073).
- **Completeness (single rule):** new `App\Services\Vehicle\VehicleCompleteness` (16 always-required fields; Private + ICE →
  CC, Private + EV → Motor, Goods → GVW, Passenger / Misc → none — user decision, amends spec §3.3).
  `VehicleService::isComplete/missingFields` delegate. `VariantService` refuses `is_active = 1` for an incomplete vehicle
  (all write paths), new variants start inactive, taxi flag accepts Y/N.
- **Stubs:** price-list stubs carry only code / OEM names / colour code (LMM TZU → `NA`), status INCOMPLETE; colour name,
  custom variant and taxi flag are left for Vehicle Info. Unknown Fuel / Permit / Body values reject the Vehicle Info row
  (no more auto-created key values).
- **Migrations** (run on `xlrm` + `xlrm_testing`, rollback verified, backup `storage/app/backups/xlrm-vehicle-pricing-pre-DEC073-28-09-2026.sql`):
  `2026_09_28_210413_pricing_redesign_foundations` (snapshot key + permit, session change log, permit map seeded from the
  insurance Rules sheet, session progress / hold lists / upload / published / completed, holds.import_session_id,
  VEHICLE_STATUS INCOMPLETE + DISCONTINUED); `2026_09_28_210553_relax_variant_stub_defaults` (wheels / taxi_price
  nullable, is_active default 0).
- **Process engine:** `Session\PricingStage` enum, `Session\PricingSessionService` (gate, start with upload + holds,
  forward-only advance, record, markPublished, exact discard before publish, complete + reopen),
  `Session\PricingChangeRecorder` + `PricingChangeObserver` (every insert / update / soft delete / bulk expiry of a session
  is logged; discard replays it backwards), `PricingHoldService` (lists incl. LMM_TZU, CSD, TAXI).
- **Reader:** `Import\PricingWorkbookReader` — one sheet, columns ≤ BJ, 250-row chunks, formula cells → saved values,
  number / percent / yes-no normalisers (real BEV sheet: 421 rows in ~2 s, 62 MB).
- **Tests:** `VehicleCompletenessTest` (4), `PricingSessionTest` (4: one open process, exact discard incl. expired rows
  restored and stubs removed, no discard after publish, complete + reopen, forward-only stages); 2 existing tests updated
  for the new stub rules. Vehicle + pricing suites: 52 passed.
- **Guides:** `docs/domains/vehicle.md` (completeness, statuses, stubs), `docs/domains/pricing.md` (engine section).

## Pricing redesign — Phase 2: gate, start, detect (DEC-073)
- **Screens:** new `App\Http\Controllers\Admin\Pricing\Process\PricingProcessController` behind the existing route names
  `pricing.workflow.index` / `start-form` / `start` / `discard`, plus `pricing.workflow.status/{id}` (JSON, polled).
  Views `resources/views/admin/pricing/process/{index,start}.blade.php`:
  - Gate: one open process; Resume / Discard (before publish only).
  - Stepper (phones: "Step n of 9" bar).
  - Detect report per sheet.
  - Start form: drop-zone upload, price-list checkboxes (CSD off by default), WEF picker, holds + Hold all.
  - `PRC_WKFL_VIEW` views; `PRC_WKFL_MANAGE` starts / discards.
  - Labels in the new `resources/lang/en/pricing.php`.
  - Before → after: the old start page posted by AJAX with a dead "import prices now" box, WEF optional, and PV/CV only
    pre-selected. Now WEF is required and every chosen list must exist in the workbook (validation error names the
    missing ones).
- **Detect:** new `Import\PriceListDetectService` (streaming, 250-code chunks, known = full OEM code on the variant
  master, INCOMPLETE stubs, LMM TZU colour NA, CSD never creates, duplicates counted once, blank OEM Model reported) and
  `Jobs\Vehicle\Pricing\Process\DetectPriceListsJob` (inside the session change log, so Discard removes the stubs).
- **Migration** `2026_09_28_211919_pricing_sheet_headers_tzu_and_status` (run on `xlrm` + `xlrm_testing`, rollback verified):
  - Adds the `PRICE_LIST_LMM_TZU` header rows. Before, that sheet had none, so it was never read.
  - Adds a `status` column header to every price list.
- **Removed (replaced):**
  - `DetectPricingWorkbookJob` and the dead `ProcessPricingWorkbookJob`.
  - The legacy `index` / `startForm` / `startDetect` / `discard` actions and the `workflow/index`, `workflow/start` views.
  - The remaining legacy step screens still run until their phases.
- **End-to-end (xlrm_testing, real `Pricing.xlsx`, all 6 lists):** 4,862 codes; 3,414 stubs, 1,390 known, 14 CSD codes not
  in the master, 58 duplicate rows, 0 errors; ~5 min, 98 MB peak. Discard undid all 3,430 changes in 4 s (variant count
  back to 2,652). Found **BUG-199**: pre-DEC-051 variant rows keep the code without the colour, so Detect duplicates them
  on databases that were not purged (needs a decision).
- **Tests:** `PricingProcessStartTest` (4):
  - view-only user;
  - start + holds + job queued + gate;
  - missing list / WEF rejected;
  - detect stubs / TZU NA / CSD skipped / status JSON / report / discard.
  Pricing suite: 18 passed.
- **Guides:** `docs/domains/pricing.md` (detect service, job, screens; legacy start / detect rows marked replaced).
- **Phase 1 follow-up:** `VehicleMasterWriteTest` (2 tests) saved incomplete variants as Active, which the DEC-073 gate now
  refuses. The tests are about codes and colours, so they now save the rows inactive. Full suite: 385 passed, 1 skipped.

## BUG-199 decision (DEC-074)
- **Decision (user, option 2):** each environment backs up, purges its vehicle masters and rebuilds them through the
  pricing process. No code remap.
- **New:** `PriceListDetectService::legacyCodeCount()` counts variant rows whose code lacks the colour suffix. The Start
  screen shows a warning while any exist. It is a warning only: the test copy keeps its legacy rows (DEC-051).
- **Test:** `PricingProcessStartTest::test_the_start_screen_warns_about_old_format_vehicle_codes`.
- **Tracker:** BUG-199 → DECIDED (DEC-074). The purge is still to be done per environment.

## Pricing redesign — Phase 3: Vehicle Info round-trip (DEC-073, DEC-075)
- **New `Import\VehicleInfoWorkbookService`:**
  - **Export:** every vehicle, in the reference layout plus a `Missing Fields` column, sorted Segment → OEM Model → code.
    Fuel / Permit / Body Make / Body Type / Status are written as key-value codes.
  - **Import:** each row goes through `VehicleService::applyVehicleInfo()`. The summary counts completed / newly
    completed / still incomplete / rejected / unknown and lists the row issues.
  - **Before → after:**
    - The old export read a non-existent key-value table, so every lookup column came out blank. The lookups are now
      filled.
    - The old import created vehicles for unknown codes. Those codes are now rejected.
    - The old import set an incomplete "ACTIVE" row to inactive and kept its old status. It is now INCOMPLETE, with the
      missing fields listed.
- **New `ImportVehicleInfoJob`** (queued, inside the session change log, so Discard undoes it; round summary kept in
  `stats.vehicle_info`) and **`VehicleInfoController`** (screen, download, queued import, issues workbook, Continue to
  prices). The route names are unchanged, plus `vehicle-info-issues` and `vehicle-info-continue`.
- **Removed:**
  - `VehicleInfoExportService` and `VehicleInfoImportService`.
  - The legacy `vehicle-info` actions and view.
  - The unused `vehicle-info-progress` route and its cache-key progress.
- **Formats (DEC-075), in `VariantService` so every write path agrees:** GST% fraction 0.28 → 28; transmission At / Mt →
  Automatic / Manual.
- **Service additions:**
  - `VehicleService::statusCounts()`.
  - `PricingSessionService::putStats()`.
  - `@property` docs on `Variant`, `VehicleModel`, `Segment`, `SubSegment` and `Keyvalue`.
  - `Variant::vehicleModel()` gets a typed relation.
- **End-to-end (xlrm_testing, real files):** detect → export 6,066 vehicles in 17.5 s (lookups filled) → import
  `Vehicle_Info_6_COMPLETED.xlsx` in 428 s. Result: 3,803 rows; 2,551 complete (2,471 newly); 987 still incomplete — 923
  have Transmission and CC blank in the reference sheet, the rest miss Motor / GVW / Colour Name; 265 codes not in the
  master. Discard undid all 7,133 changes (variants back to 2,652).
- **Tests:** `PricingVehicleInfoTest` (4):
  - import outcomes (complete → ACTIVE, incomplete kept INCOMPLETE with its reason, unknown lookup and unknown code
    rejected, GST / transmission formats);
  - export codes + missing fields;
  - view-only user;
  - queued import + continue.
- **Guides:** `docs/domains/pricing.md`, `docs/domains/vehicle.md`.

## Pricing redesign — Phase 4: price import (DEC-073, DEC-076; BUG-200, BUG-201 fixed)
- **Column choices (user, DEC-076):** ex-showroom per list is:
  - PV / CV / BEV: "Ex-Showroom Price ORG";
  - LMM: "Ex Showroom Price(Org)";
  - LMM TZU: "Final Transaction Price" (no scheme discount);
  - CSD: "CSD Final Price".
- **BUG-200 fixed (Critical):** header labels with `-` `.` `_` never matched the registry, so the old importer stored
  MM Invoice as ex-showroom and imported no schemes. `SheetHeaderService` now normalises both sides the same way,
  prefers the primary label over aliases, and drops the pre-subsidy hard alias. `normalizeLabel()` is now public.
- **Migration** `2026_09_28_223347_pricing_price_list_columns_dec076` (backup
  `storage/app/backups/xlrm-pricing-headers-pre-DEC076-28-09-2026.sql`; run on xlrm + xlrm_testing; rollback verified):
  - Per-list registry labels and aliases: PV unprefixed schemes; LMM freight, VIN Scheme, margin and handling; TZU
    final price, scheme columns off; CSD final price.
  - Four eligibility columns on `xlr8_vehicle_pricing` (`curr/old_acc_elg`, `curr/old_shield_elg`).
- **New `Import\PriceListImportService` + `ImportPricesJob` + `PricesController`:**
  - Reuses the Start workbook or takes an updated one; lists and WEF; the run is queued and recorded for Discard.
  - The screen shows a per-list summary and the issues, with a download.
  - Before → after:
    - WEF: a new WEF with no material change used to insert a second active row. It now keeps the live row.
    - Older WEF than the live row: it is now rejected.
    - History: now written, one row per code (BUG-201: the `PricingHistory` model now matches its table).
    - Duplicate codes: conflicting duplicates are rejected.
    - PV's repeated OV block is read as OV.
    - Dealer margin = margin + handling.
    - GST% is derived when the sheet has none (TZU).
- **Removed:**
  - `PriceListPricingImporter`, `PriceListVehicleDetector` (its BUG-131 test moved to `PriceListDetectServiceTest`) and
    `ImportPriceListsJob`.
  - The legacy prices actions and view, the legacy `progress` route and the unused `sheetOptions()`.
- **End-to-end (xlrm_testing, real files):**
  - Import of all 6 lists at 3 WEFs: 41 s, 30 s and 19 s (122 MB peak). The runs gave 3,757 inserts, then 3,757
    same-WEF updates, then 3,757 unchanged at a later WEF.
  - No duplicate active rows. Discard undid all 22,164 changes.
  - Spot checks: PV Scorpio-N ₹22,76,500 (scheme 75,000 / 25,000 with GST, elg 0.7 / 1); CV Veero 8,24,500 (margin
    28,820 from Handling); BEV XEV 9S 25,95,001; LMM E-Alfa 1,77,219 (Org; assessable + freight 1,55,597; VIN scheme
    10,876); TZU Final Transaction Price; CSD channel with CSD Final Price.
  - Found and fixed during the run: the per-sheet counters were lost (an arrow function passed them by value).
- **Tests:** `PricingPriceImportTest` (4):
  - PV columns + NV/OV + skips/conflicts;
  - WEF update / keep / expire / reject + history;
  - LMM / TZU / CSD column choices;
  - queued screen + continue.
  `PriceListDetectServiceTest` (8). Pricing suites: 53 passed.

## Pricing process speed-up (DEC-073)
- **Cause:** Detect and the Vehicle Info import autocommitted every write (vehicle + change-log row), and each MySQL
  commit costs about 25–30 ms here. A stub took about 90 ms and a Vehicle Info row about 110 ms, against 9 ms and 15 ms
  of real work.
- **Fix:**
  - `PriceListDetectService` writes each 250-code chunk in one transaction (`createStubs()`).
  - `VehicleInfoWorkbookService` writes each 100-row batch in one transaction (`importBatch()`).
  - `VehicleService::kkvId()` memoises key-value lookups for the service instance (one import run).
  - The price import was already chunked.
- **Real files (xlrm_testing):** Detect 312–446 s → **57 s**; Vehicle Info 428–434 s → **68 s** (47 s on a re-run).
  Results are identical: 3,414 stubs; 2,551 complete / 987 incomplete / 265 unknown. Discard is still exact (10,796
  changes, 10 s).
- **Plan:** Phase 10b added — the standalone Price List menu (all logged-in users; read-only AG Grid per list, PDF
  layout), requested 28-09.

## Pricing redesign — Phase 5: add-ons & discounts (DEC-073, DEC-077)
- **New `Import\AddonDiscountWorkbookService` + `ImportAddonsJob` + `AddonsController`:**
  - **Export:** the reference `Addon-N-Discounts.xlsx` sheets for the ticked groups (all ticked by default). Every
    segment / model / scheme / category appears, with blanks where nothing is stored.
  - **Import:** queued. Each ticked sheet is one transaction: its group expires at the WEF and its rows are inserted.
    Groups not ticked are untouched.
  - **Before → after:**
    - Blank = no rule; 0 = an explicit zero rule.
    - Model names now resolve to model codes through the new `VehicleService::findModel()` ("Bolero Neo +" →
      BOLERO-NEO-PLUS); unknown models are rejected. Before, free text was stored.
    - Conflicting duplicate scopes are rejected; identical duplicates count once.
    - History is written.
    - Before, the whole workbook was loaded, rows were written without a transaction, and nothing was read by chunk.
- **Migration** `2026_09_28_232022_pricing_addon_sheet_aliases_dec077` (run on xlrm + xlrm_testing; rollback verified):
  reference labels for Exchange ("OEM Model", "Scheme", "Bonus OEM / DLR / TOTAL"), Corporate and RSA.
- **`DealerChargeService`:** an all-zero row is allowed at a specific segment and refused at ANY. The existing test was
  updated to cover both.
- **BUG-201 extended and fixed:** `AddonHistory` / `DiscountHistory` now match their tables and are observed by the
  session change log.
- **Shared UI:** `process/_progress.blade.php` (running-step card + 2 s polling) replaces three inline copies on the
  Vehicle Info, Prices and Add-ons screens.
- **Removed:** `AddonDiscountExportService`, `AddonDiscountImportService`, the legacy add-on actions / view, and the
  legacy importer test (its assertions moved to `PricingAddonImportTest`).
- **Real file:** `Addon-N-Discounts.xlsx` imports in 3.6 s:
  - 4 dealer-charge rows, 125 RSA, 76 Shield, 150 Exchange and 400 Corporate.
  - 8 Corporate rows rejected: model "3XO REVX" is not in the test master.
  - 1 identical RSA duplicate.
  - The export takes 0.2 s, and re-importing it is lossless (same counts, 0 issues).
- **Tests:** `PricingAddonImportTest` (3):
  - ticked-group replace, blank / 0 / Any / names / unknown / conflict, history;
  - export blanks + round trip;
  - screen export / queued import / continue.
  Plus the dealer-charge zero-row test updated. Pricing suites: 29 passed.

## Pricing redesign — Phase 6: standalone Insurance & RTO workbooks (DEC-073, DEC-078; BUG-202 fixed)
- **Shared parsers:**
  - `Rules\RuleRange` covers every range spelling in the sheets and flags inverted bands.
  - `Rules\RuleFormula` is a safe evaluator for "(10% * 1.25 * 2) / 15", "12.5% of Tax", "5% x OD",
    "1162 x (Seat -1)" and "150 Per Seat", with no `eval()`.
  - Both are unit-tested (13).
- **Migration** `2026_09_28_234119_pricing_rules_workbooks_dec078` (backup
  `storage/app/backups/xlrm-pricing-rules-pre-DEC078-28-09-2026.sql`; xlrm + xlrm_testing; rollback verified):
  - RTO rules gain `seater` and `assessable_range`. `RtoRule::findBestMatch()` already filtered on the missing seater.
  - Insurance base rules gain `heads` JSON and `tp_pa_owner`.
  - Insurance add-on rates gain `base_rule_id` and `rate_text`.
  - Registry rows for RTO_RULES / INSU_COMPANY / INSU_PREMIUM / PERMIT_MAP.
- **New:**
  - `Import\RtoWorkbookService` and `Import\InsuranceWorkbookService` (presence / export / import; one transaction
    per sheet; formulas and ranges validated; conflicting duplicates rejected).
  - `Rules\PermitMapService` + `PermitMap` model.
  - `ImportRulesJob` and `RulesController`. The screen has one card per workbook: "None stored — import required" or
    stored and kept, download current, upload + WEF, result and issues. Continue needs both kinds.
- **Before → after:**
  - Insurance add-on rates were never imported (GAP-01). Now 108 rates per premium row set.
  - Formula heads were lost. Now they are kept as written.
  - Re-importing the rules wiped insurance. Now each kind is replaced on its own.
- **BUG-202 fixed:** `InsDefault::getCompanies()` / `scopeActive()` used columns the table does not have.
- **Also:**
  - The legacy `RtoService` surcharge now uses `RuleFormula`.
  - `PricingChangeRecorder::captureBulk()` generic type fixed.
- **Removed:** `RulesWorkbookService`, the legacy rules actions / view / `rules-keep` route, and its legacy test.
- **Real files:**
  - `RTO-Rules.xlsx`: 45 rules in 1 s. The 3 BH rows with assessable "1000000 - 200000" are reported as never
    matching; the band probably means 1000000 - 2000000, which is for the user to fix in the sheet.
  - `Insurance.xlsx`: 62 company rows and 26 premium rules (108 add-on rates) in 1 s.
  - The export (4 s) re-imports losslessly: identical heads and counts.
- **Tests:**
  - `PricingRulesImportTest` (3): RTO formulas / ranges / rejects / expiry; insurance reference layout + round trip;
    the step's gate.
  - `RuleRangeAndFormulaTest` (13).
  - Pricing suites: 70 passed.

## Pricing redesign — Phase 7: impact summary + hold check (DEC-073, DEC-079)
- **User:** the reference BH band is "1000000 - 2000000"; the source workbook is corrected by the user, and the import
  keeps what the sheet says.
- **Migration** `2026_09_29_001155_pricing_price_list_source_dec079` (xlrm + xlrm_testing; rollback verified): a
  `price_list` column plus index on `xlr8_vehicle_pricing`. The price import sets it, and stamps it on an unchanged live
  row. `PriceService` gains the field.
- **New `Session\PricingImpactService`:** computes the summary live from the session change log and the masters:
  - new and activated vehicles;
  - price changes new / up / down / other per channel;
  - add-on groups and rule sets replaced or kept;
  - what will calculate per list, minus held lists (TAXI = taxi vehicles' Passenger snapshots);
  - what is skipped;
  - a downloadable incomplete list.
- **New `ImpactController` + `process/impact` view:**
  - Step 7 is always shown before calculating, with a "Reviewed — continue" action.
  - Step 8, the hold check, holds or reopens lists recorded in the session (Discard undoes them). It is followed by
    Calculate & publish, which arrives in Phase 8.
- **Removed:** the legacy `impactSummary` / `impactSummaryView` actions, their view and the JSON route.
- **Tests:** `PricingImpactTest` (2):
  - exact counts from the change log;
  - review → hold check → hold PV removes it → Discard undoes the hold.
  Plus a `price_list` assertion in the price import test.

## Pricing redesign — Phases 8 + 9: Calculate & Publish, process summary (DEC-073, DEC-080)
- **User decisions (DEC-080):**
  - The consumer scheme is deducted by default.
  - TCS = 1% × (ex-showroom − discounts) when ex-showroom ≥ ₹10 lakh.
  - Insurance OD discount 30%.
  - GST 18%, Goods TP 12%.
  - Default accessories = the accessory discount.
  - COD in on-road is controlled by the new setting `pricing.dealer_charges.include_cod` (default off).
- **New engine** `App\Services\Vehicle\Pricing\Engine\*`:
  - `PricingContract` v2 (fixed keys).
  - `VehicleFacts`, `ScopeMatcher` (spec §6), `RuleBook` (all rules in memory per chunk).
  - `ComponentResolver` (dealer charges, RSA, Shield, exchange, corporate).
  - `RtoCalculator` (rounded-up ESR / BH base / Fixed, surcharge formula, fees, BH option).
  - `InsuranceCalculator` (every company × plan, IDV slots, OD − 30%, CNG kit / IMT 23, TP heads with seat formulas,
    add-ons, GST, default NilDep + Consumables frozen).
  - `SnapshotBuilder` (permit × NV / OV × channel; taxi → Passenger on RTO Taxi / insurance Passenger).
  - `SnapshotPublisher` (one transaction per vehicle; the previous WEF is expired).
  - `PricingCalculationService` (queued `Bus::batch` of `CalculateVehiclesJob`, 100 vehicles each; held lists
    skipped; failures recorded; retry; finish).
  - `PricingFailure`.
- **New table** `xlr8_vehicle_pricing_calc_results` (migration `2026_09_29_004433`) and `CalcResult` model.
- **Settings** (`config/platform.php`, listed in `docs/utilities/16-reference.md`): `pricing.insurance.od_discount_pct`,
  `pricing.insurance.gst_pct`, `pricing.insurance.goods_tp_gst_pct`, `pricing.rto.round_up_to`,
  `pricing.dealer_charges.include_cod`.
- **Screens:**
  - `CalculateController`: start from the hold check; `summary/{id}` with progress, the batch %, per-list counts and
    failures; download of failed / skipped; Retry failed; Mark complete with reopen lists (releases the gate).
  - `pricing.workflow.status` returns the batch progress, and the shared `_progress` card shows a percentage bar.
- **Large-workbook fix:**
  - Step issue lists and detect's code lists are now kept in `storage/app/pricing/{id}/{step}-issues.json` through the
    new `Session\PricingIssueStore`. The session `stats` keep the counts and a 20-row preview.
  - The first full real run failed with "MySQL Out of memory" while rewriting a 197 KB stats blob on a machine whose
    virtual memory was nearly exhausted.
- **Removed (legacy):** `PricingWorkflowController`, `CalculatePricingSessionJob`, `RecalculateVehiclePricingJob`, and the
  old `Pricing\PricingSessionService`. `PricingEngineService` remains only for the unrouted v1 API (Phase 10).
- **Real run (all reference files, xlrm_testing):**
  - Timings: detect 38 s, Vehicle Info 61 s, prices 34 s, add-ons 5 s, insurance 2 s, RTO 1.5 s.
  - Calculate & Publish: 2,527 vehicles in 179 s (164 MB), 2,495 published as 9,266 snapshots.
  - 32 failed with reasons, all data gaps in the reference sheets:
    - 20 CNG vehicles: the RTO sheet has no CNG rows;
    - 12 taxi vehicles with more than 7 seats: the insurance Passenger row covers "1 to 7".
  - The Scorpio-N figures match a hand calculation.
- **Tests:** `PricingCalculationTest` (4):
  - on-road to the rupee, NV / OV, taxi snapshot, TCS, accessories;
  - fixed keys + the COD setting;
  - failure reason;
  - the queued run: publish, TAXI hold, retry, no discard after publish, complete + reopen.
  Pricing suites: 80 passed.
