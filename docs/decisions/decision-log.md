# Decision log — xlrm (Track A) and Xceler8 programme

Append-only. One entry per non-trivial decision, written **before** the change it governs.
Format: `DEC-NNN | date time | track/phase | decision | options considered | rationale | risk | approved-by | reversal path`.
Risk: LOW (reversible, local, no behaviour change) · MED (behaviour change, reversible) · HIGH (needs explicit user approval — see the approved plan, §8).

---

### DEC-001 | 26-09-2026 14:35 | Programme | Two-track delivery
- **Decision:** Track A = strangler stabilisation of the current app for UAT; Track B = greenfield Xceler8 built in parallel with an ETL toolkit for switch-over.
- **Options:** strangler only; greenfield only (freeze current app); both in parallel.
- **Rationale:** user choice — UAT deadline needs the current app; long-term quality needs a clean build; the current app must not freeze.
- **Risk:** MED · **Approved-by:** user (26-09-2026) · **Reversal:** stop Track B at any phase; Track A stands alone.

### DEC-002 | 26-09-2026 14:35 | Track B | UI stack = Filament 5 + Livewire 4
- **Options:** Filament 5; keep Backpack and modernise; Inertia + Vue SPA.
- **Rationale:** user asked for the best long-run manageability/UX with a new panel built feature by feature. Filament is free OSS, reactive, with built-in dark mode and accessibility, and needs no hand-written jQuery. Track A stays on Backpack, to avoid building the same UI twice.
- **Risk:** MED · **Approved-by:** user (plan approval) · **Reversal:** Track B only; no effect on the current app.

### DEC-003 | 26-09-2026 14:35 | Track A | Full DB normalisation, manifest-driven, with automated code fixing
- **Options:** full normalisation; additive only; new schema + ETL.
- **Rationale:** user choice. One manifest drives the migrations, a Rector/Blade codemod, and a `LegacyAttributes` alias trait, so changes don't break code.
- **Risk:** HIGH for destructive steps (each needs approval + backup) · **Approved-by:** user (programme) · **Reversal:** every migration has `down()`; alias trait keeps old names working.

### DEC-004 | 26-09-2026 14:35 | Both | API v1 contract preserved; v2 alongside
- **Rationale:** a mobile app exists or is planned (user answer).
- **Risk:** LOW · **Approved-by:** user.

### DEC-005 | 26-09-2026 14:35 | Both | PHP 8.4; technology chosen on merit (no downgrades)
- **Rationale:** user instruction. `composer why-not php 8.4.0` shows no blockers; our code has only 6 implicit-nullable parameters to fix.
- **Risk:** MED (runtime switch) · **Approved-by:** user (principle); installing PHP 8.4 into Laragon still needs a separate go-ahead (§8 item 5).

### DEC-006 | 26-09-2026 14:35 | Programme | Postponed until after pilot/UAT
- **Items:** DB-level audit (triggers, stored procedures, DB users), the self-hosted AI utility, real SMS/WhatsApp/telephony vendor drivers.
- **Approved-by:** user.

### DEC-007 | 26-09-2026 14:35 | Programme | Execution protocol
- **Decision:** auto mode with every decision logged here. Manual approval is required for: history rewrites, pushes, non-local DB operations, destructive operations on real data, deletions outside the drop list, environment changes, dependency additions outside the approved list, business-rule ambiguity, security-sensitive changes, and UAT-visible behaviour changes.
- **Approved-by:** user.

### DEC-008 | 26-09-2026 14:36 | A0 | Commit the current tree on `stage` as-is, then branch `feature/integrations`
- **Rationale:** user choice "everything as-is" (26-09-2026 question). It records the 24/25-09 bug-fix sweep, data scoping option A, and the user's own doc/data file moves.
- **Risk:** LOW (local commit, no push) · **Approved-by:** user · **Reversal:** `git reset --soft HEAD~1` before any push.

### DEC-009 | 26-09-2026 14:36 | A0 | `gscreds.json`: untrack now, keep the local file, purge history only with approval
- **Options:** `git rm` (deletes the local file, breaking Google Sheets imports that read it); `git rm --cached` + `.gitignore` (stops tracking, keeps the file); history purge (rewrite).
- **Rationale:** untracking stops future exposure without breaking local imports. The key has already been in every commit since `e6147f7`, so **rotation by the user is the real fix**. History rewrite plus force-push is HIGH risk and waits for explicit approval.
- **Risk:** LOW (untrack) / HIGH (purge — pending) · **Approved-by:** auto (untrack); user approval pending for the purge.

### DEC-010 | 26-09-2026 14:50 | A0 | Fix wrong command class in `bootstrap/app.php`
- **Decision:** `ImportRbacMasterCommand::class` → `ImportRbacMaster::class`.
- **Rationale:** the class doesn't exist. It only went unnoticed because Laravel auto-discovers `app/Console/Commands`.
- **Risk:** LOW · **Approved-by:** auto · **Reversal:** revert one line.

### DEC-011 | 26-09-2026 14:52 | A0 | Test DB = local full copy `xlrm_testing` (schema + data), refreshed by a script
- **Options:** empty schema from `schema:dump` (breaks the many tests that read real reference data); keep testing on `xlrm` (unsafe); full local copy.
- **Rationale:** tests currently depend on live reference data (RTO rules, org users, user 1). A copy keeps them green while making it impossible for any test to damage `xlrm`. Refreshed by `php artisan testing:refresh-db` (mysqldump → xlrm_testing; refuses to target the source DB). The data is ~92 MB.
- **Risk:** LOW (local, additive) · **Approved-by:** auto · **Reversal:** point `phpunit.xml` back to `xlrm` and drop `xlrm_testing`.

### DEC-012 | 26-09-2026 15:10 | A0 | API auth fixes
- **Decision:** add Sanctum's `HasApiTokens` to `User`; move `/api/v1/pricing/*` from the nonexistent `auth:api` guard to `auth:sanctum` + `validate_device` (same as the rest of v1); register Spatie `role`/`permission`/`role_or_permission` middleware aliases.
- **Risk:** HIGH (security-sensitive) · **Approved-by:** user (26-09-2026 question) · **Reversal:** revert the commit; web/admin login is not involved.

### DEC-013 | 26-09-2026 15:10 | A0 | Roles model: `IAM\Role` uses the configured Spatie roles table (designation)
- **Options:** point the model at the configured table; create a new `xlr8_iam_roles` and migrate 167 assignments.
- **Rationale:** matches live data; minimal risk before UAT. Track B separates Roles (permission sets) from Designations (positions / approval levels).
- **Risk:** HIGH (permission model) · **Approved-by:** user · **Reversal:** revert.

### DEC-014 | 26-09-2026 15:10 | A0 | Install PHP 8.4 x64 TS into Laragon and switch; keep 8.3.30 for rollback
- **Risk:** HIGH (environment) · **Approved-by:** user · **Reversal:** switch Laragon back to 8.3.30.

### DEC-015 | 26-09-2026 15:10 | Track B | Greenfield project at `D:\laragon\www\xceler8`, new git repo, no remote yet
- **Risk:** LOW · **Approved-by:** user.

### DEC-016 | 26-09-2026 15:18 | A0 | Admin settings API gated by permission `UTL_SETTINGS_MANAGE`, not `role:admin|super_admin`
- **Rationale:** roles `admin` and `super_admin` don't exist (the only admin role is `superadmin`). The admin screen already checks `UTL_SETTINGS_MANAGE`. The permission middleware goes through the Gate, so the superadmin bypass still applies. It's the same rule on both surfaces.
- **Risk:** MED · **Approved-by:** auto (within DEC-012) · **Reversal:** revert the route middleware.

### DEC-017 | 26-09-2026 15:35 | A0 | Remove controller-level `$this->middleware()` calls (BUG-159)
- **Finding:** `NotificationController` and `SystemSettingApiController` called `$this->middleware('auth:sanctum')` in their constructors. That method doesn't exist on Laravel 11+ base controllers, so **every** notifications and settings API request fataled.
- **Decision:** remove the calls. `auth:sanctum` is already applied by the route group, so auth is unchanged.
- **Verified** on xlrm_testing with a device-bound token: `/auth/me` 200, `/notifications` 200, `/devices` 200; settings export 403 without the permission and 200 for superadmin. `/system-settings` index is still 500 (known A4 item: missing model methods).
- **Risk:** LOW · **Approved-by:** auto (within DEC-012 scope) · **Reversal:** revert.

### DEC-018 | 26-09-2026 15:55 | A0 | Roles = designations: remove the duplicate Role screen; fix hard-coded `xlr8_iam_roles`
- **Finding:** at runtime Spatie already uses the designation table (its constructor overrides `$table` from config), so role checks worked. What was broken was code that names `xlr8_iam_roles` directly: the Role list's `is_post` clause (500), `RoleRequest`'s unique rule (every role save failed), and the `M_Post` sheet of `import:rbac-master`.
- **Decision:**
  - Remove the Role menu item; `iam/role` now redirects to Org → Designation, which already manages permissions through the permission tree.
  - `RoleRequest` uses `config('permission.table_names.roles')`.
  - `IAM\Role` drops its misleading `$table` and documents the designation mapping.
  - Disable the `M_Post` sheet in the live `RbacMasterImport` (it was already disabled in the duplicate copy).
- **Risk:** HIGH (permission model, UAT-visible menu change) · **Approved-by:** user (option "Point Role at designation table") · **Reversal:** revert the commit.

### DEC-019 | 26-09-2026 16:20 | A0 | PHP 8.4.26 installed side by side; the switch happens from Laragon's menu; composer constraint unchanged for now
- **Done:**
  - Official `php-8.4.26-Win32-vs17-x64` (TS, SHA-256 verified) extracted to `D:\laragon\bin\php\`.
  - `php.ini` mirrors 8.3.30 (same extensions and limits); the deprecated `session.sid_*` keys are commented out.
  - phpredis 6.3.0 added.
  - Full suite on 8.4: 212 passed / 31 known failures (same as 8.3).
  - The 6 implicit-nullable parameters fixed; the rescan finds 0.
- **Not done, and why:**
  - I didn't edit `laragon.ini` or the Windows PATH. Laragon rewrites its ini on exit, and PATH also serves WAMP (8.3.14) and XAMPP installs, whose order would change. The user switches via Laragon → PHP → Version (one click), then Tools → Path → Add Laragon to Path.
  - `composer.json` stays `php ^8.2`. Bumping it to `^8.4` would break deploys if staging or production still runs 8.3. Bump it once the servers are on 8.4. The code is now clean on both versions.
- **Risk:** MED · **Approved-by:** user (DEC-014) · **Reversal:** switch Laragon back to 8.3.30 (still installed).

### DEC-020 | 26-09-2026 16:45 | A0 | Dead routes: remove unreachable ones, implement the ones with ready service methods, ask for business-rule ones
- **Removed** (no working UI entry point; they returned 500): `sales.booking.order-verify`, `sales.enquiry.pending`, `vehicle.model.destroy` and `accounts.receipt.destroy` (no delete buttons exist), `POST api/v1/pricing/generate-quote` (its `PricingService` is an empty file).
- **Implemented:**
  - `POST api/v1/devices/revoke-all` → `FirebaseService::revokeAllUserDevices()`.
  - `GET api/v1/system-settings/category/{site,dealership,pricing}` → `SystemSettingService::get*Settings()`.
- **Asking (business rules):** the menu-linked `sales/quotation/pending` and `sales/enquiry/erroneous`, and the booking show page's refund edit form (`sales.booking.refund.edit`).
- **Risk:** LOW/MED · **Approved-by:** auto · **Reversal:** revert.

### DEC-021 | 26-09-2026 16:45 | A0 | `SystemSetting`: add the missing topic/group methods; topic = `topic` column or key prefix
- **Finding:** the service and API call `SystemSetting::getByTopic/allByTopic/byTopic/byGroup`, which don't exist, so the settings API index, topic and category endpoints all 500. Every live row has an empty `topic` column; the topic is encoded in the key prefix (`site.name`).
- **Decision:**
  - Topic matches `topic = ?` OR (topic empty AND key LIKE `'{topic}.%'`).
  - `set()` uses `save()` instead of `saveQuietly()`. This drops the nonexistent `updatedby` write (BaseModel stamps `updated_by`) and runs the `saved` hook, so the cache is actually cleared.
  - `flushAllCache()` forgets each key instead of using `Cache::tags()`, which throws on the `database` store.
- **Risk:** MED · **Approved-by:** auto (the A4 "Settings fixes" scope, pulled forward because the API depends on it) · **Reversal:** revert.

### DEC-022 | 26-09-2026 17:05 | A0 | BUG-160: grant `admin.dashboard` to every designation (role), via an idempotent seeder
- **Options:** make the dashboard login-only; grant the permission to all roles; leave as is.
- **Rationale:** user choice — keep the permission check and fix the data.
- **Risk:** HIGH (permission data) · **Approved-by:** user · **Reversal:** the seeder logs every role it granted to `storage/logs/grant-dashboard-permission-{database}.json`; revoke exactly those via tinker (the command is shown in the seeder docblock).

### DEC-023 | 26-09-2026 17:05 | A0 | Hide Quotation → Pending and Enquiry → Erroneous until Track B
- **Decision:** remove both menu links and routes (their methods never existed). Track B's Quotation and Enquiry modules provide proper work queues.
- **Risk:** MED (UAT-visible) · **Approved-by:** user.

### DEC-024 | 26-09-2026 17:05 | A0 | Implement booking refund-details edit (`editRefund`)
- **Decision:** add `BookingRefundService::applyRefundDetailsEdit()`, which validates and updates the refund request's bank and deduction fields and its document, and records history. The status field is not changed by this form. Includes tests.
- **Risk:** MED · **Approved-by:** user.

### DEC-025 | 26-09-2026 17:05 | A0 | BUG-104: production also lacks the 7 booking columns; add them via migration (types reviewed with the user before running)
- **Risk:** HIGH (schema) · **Approved-by:** user (fix direction); column types still need user review.

### DEC-026 | 26-09-2026 17:12 | A0 | Retire WAMP/XAMPP from PATH; Laragon PHP 8.4.26 becomes the CLI PHP
- **Rationale:** user confirmed Laragon is the only local dev stack (WAMP/XAMPP unused).
- **Change:**
  - User PATH: remove `D:\wamp64\bin\php\php8.3.14` and `D:\wamp64\bin\mysql\mysql9.1.0\bin`.
  - Machine PATH: replace `D:\xampp\php` and `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64` with `D:\laragon\bin\php\php-8.4.26-Win32-vs17-x64`. This needs admin; if it's denied, the user runs the given one-liner in an elevated shell.
  - The previous values are saved to `docs/decisions/path-backup-26-09-2026.txt` for reversal.
- **Risk:** MED (environment) · **Approved-by:** user · **Reversal:** restore from the backup file.

### DEC-027 | 26-09-2026 17:55 | A0 | BUG-104 follow-ups: VOTF branch from the linked enquiry; replace `dd()` in booking store
- **Findings:**
  - `xlr8_booking_master` has no branch column. `getFullBookingData()` copies `branch_code` from the linked enquiry's `dealer_branch` for display only, so `generateVotfNumber()` (which loads a fresh booking) always threw "Branch code is missing".
  - `BookingCoreService::store()` called `dd()` on any save failure: it dumps the message, file and line to the browser and kills the request.
- **Decision:**
  - VOTF uses `$booking->branch_code`, falling back to `Enquiry::resolveByAnyReference($booking->enq_no)->dealer_branch` (the same source the display code uses).
  - `dd()` becomes `Log::error` + rethrow, so failures go through Laravel's normal error handling.
- **Risk:** MED (obvious bug fixes on UAT-critical paths) · **Approved-by:** auto (within the BUG-104 fix the user approved) · **Reversal:** revert.

### DEC-028 | 26-09-2026 18:40 | Track B | Adopt support-utility requirements from `docs/refactor/missing-info-utilities.md`
- **Decision:**
  - Add a **Knowledge Base** utility (new phase B2c).
  - Extend Chat into **conversations** (direct/group/team/ticket plus the entity journal, public/internal visibility, read state, idempotent sends).
  - Extend **Tickets** (configurable lifecycle, ULID public id, merge/split/links, resolution-on-close, CSAT, dashboards).
  - SLA calendars use `spatie/opening-hours` 4.2.2 (Packagist-verified).
  - Add a correlation-ID middleware and per-user timezone.
- **Deferred:** customer portal and live chat (after B10); inbound email and antivirus (post-UAT).
- **Noted:** the document's "confirmed packages" (Passport, MS Graph, DataTables, query cache) are not in this repo; it's treated as requirements only.
- **Open (asked before B2):** SUP-DEC-001/002/003/005/006/010.
- **Risk:** LOW (plan change) · **Approved-by:** user (instruction to pick the achievable items) · **Reversal:** plan edit.

### DEC-029 | 26-09-2026 19:10 | A / B | User decisions batch
- **BUG-161 (booking branch):** leave the data as is. VOTF may use the FSC's (sales consultant's) branch when it can be resolved, otherwise stays blank with the existing clear error. No schema change.
- **Chat variants (SUP-DEC-001):** all of them — direct, group, team, ticket-linked, entity journal and customer live chat (live chat is still sequenced after B10).
- **Knowledge Base audience (SUP-DEC-002):** both internal and customer-facing, with separate publication controls.
- **Ticket intake channels (SUP-DEC-003):** unclear to the user; explanation provided, decision still open.
- **SLA and retention (SUP-DEC-005/006):** on hold until those phases.
- **Environment:** the user cleaned PATH and switched Laragon to PHP 8.4 (DEC-019 complete).
- **Approved-by:** user.

### DEC-030 | 26-09-2026 19:45 | A1 | Dead-code purge (reference-checked; git history keeps everything)
- **Method:**
  - Candidates come from an automated reference scan across `app`, `routes`, `config`, `resources/views`, `database`, `tests` and `bootstrap`, plus a PSR-4 path check.
  - Views: backup directory, dotted-name reference check, and whitespace-insensitive comparison against the Backpack package originals.
  - Each item was reviewed manually. Business-relevant but unrouted code is **kept** and noted, not deleted.
- **Deleted:**
  - `app/Models_backup/`.
  - 8 dead helpers (a closed cluster: Chat/Quotes/Notification/Vehicle/Task/Docs/Branch/User).
  - 21 unrouted or dead controllers (Post*, Graph*, Emp*Assignment, HR*, Approval/ReportingHierarchy, Garage, UserType, DashboardControllerCrud, EmployeeJourneyController, old Enquiry, 2 unrouted spare reports), both pricing API controllers plus the empty `PricingService` and the `calculate-exchange` route (it called a method that never existed).
  - 13 unused FormRequests.
  - Dead or duplicate models (Emp*Pivot ×4, EmployeePayroll, EmployeeVehicleScope, UserDivisionAssignment, XlFeeCollection, the App\Models XlInsurance duplicate, XVehicleStock, X_Vh_Order, the Module/Exchange and Module/Rto duplicates, XFinanceTa, XlSpareBilledRo, XlSpareTransit, Chat, NotificationsMaster).
  - Unused traits (GraphTraversal, HasAuditFields, HasSlug).
  - Dead services (AuthenticationService, BookingStateService, RulesUserImportTemplateGenerator, SegmentService, VehicleMasterService).
  - Unused imports and sheets, duplicate class files, `oldImportEnquiriesJob`.
  - The Post module's tests, factories and `AppServiceProvider` bindings (BUG-080).
  - `resources/views/backup/` (26 files), 11 orphan views, 182 Backpack overrides identical to the originals.
  - Stray empty files, root scripts, and a jpg in `config/backpack`.
- **Kept on purpose:**
  - `CommonHelper`, `XCommonHelper`, `XpricingHelper` (live callers).
  - `DesigDeptTreeCrudController`, `TestDriveCrudController` and `ManagesOrgEntityCrud` (unrouted or unused but business-relevant; a decision for Track B).
  - Pricing models and `ProcessPricingWorkbookJob` (active module); `SystemSettingAudit` and `UserReportingService` (planned use).
- **Fixed instead of deleted:** the two OTP email views were "orphaned" only because `OtpNotificationService` asked for `emails.verification-success` / `emails.account-locked` (hyphens) instead of the real underscore names.
- **Risk:** MED · **Approved-by:** user ("start the dead code purge"; drop list in plan §2) · **Reversal:** `git revert` of the purge commit.

### DEC-031 | 26-09-2026 20:20 | A (AI context) | Consolidate AI context into one canonical `.ai/`; archive the originals
- **Archive:** every existing AI instruction file is moved with `git mv` to `.ai/_archive/2026-09-26/<original path>`, and `MANIFEST.md` records original path, git blob hash, lines, what each covered and where its content now lives. Moved:
  - `docs/{CLAUDE,AGENTS,00-core}.md`
  - `.ai/rules/*`, `.ai/skills/*`, `.ai/guidelines/xcelr8.md`
  - `.github/skills/*`, `docs/reference/pricing/.ai-rules/*`
  - `docs/refactor/{TASK_STATE,claude-findings,claude-first-inspection,standard-problems,system-checkup}.md`
- **New layout:**
  - `.ai/guidelines/` — always loaded, small.
  - `.ai/rules/` — loaded by path; synced to `.claude/rules/`.
  - `.ai/skills/` — loaded on demand, with frontmatter.
  - `.ai/knowledge/` — read only when linked; includes generated DB schema cards.
  - `.ai/state/` — current work and a generated bugs index.
  - Root `CLAUDE.md` / `AGENTS.md` are generated by Boost.
- **Contradictions resolved:**
  - Branches: `feature/*` or `refactor/*`, never `main`.
  - Commits: auto-mode checkpoint commits are allowed (DEC-007); push only on request.
  - Schema: migrations, not SQL-first (DEC-003).
  - Permissions: one scheme, `MOD_PROC_ACT` with `guard_name=web`.
  - Filament: forbidden in the current app, the standard in Track B.
  - The Approval Engine lock is lifted (FRS §7–8).
  - Tests: PHPUnit in the current app (runs on `xlrm_testing`); Pest in Track B.
  - The known-bugs tracker is grep-only; `state/bugs-index.md` is the summary.
- **Also:** fix the `.mcp.json` Boost server (PHP path and cwd were on C: with a non-existent PHP 8.3.28); drop the invalid `beforeCommand` hook; remove the clean `.kilo` worktrees (content is in history at 7043451).
- **Risk:** LOW (docs; git mv keeps history) · **Approved-by:** user (plan §6; "start ai-context consolidation") · **Reversal:** `git mv` back from `_archive`.

### DEC-032 | 26-09-2026 21:30 | B0 | Track B foundation choices
- **Project:** `D:\laragon\www\xceler8`, `laravel/laravel` 13.10 skeleton (PHP 8.4), own git repo (no remote until the user creates one), vhost `xceler8.test` via Laragon auto-vhosts.
- **Database:** local MySQL database `xceler8` (utf8mb4_0900_ai_ci). A read-only `legacy` connection to `xlrm` feeds the ETL. Locally it uses the same root credentials; a dedicated read-only DB user is a post-UAT DBA item (DEC-006).
- **Drivers:** start with `database` queue/cache/session. Switching to Redis (bundled with Laragon) is a one-line `.env` change once the user starts the Redis service (starting services needs approval, DEC-007).
- **Packages:** installed in batches from the approved Track B stack list, each batch verified before the next:
  - modular (`internachi/modular`, modules in `app-modules/`);
  - Filament 5 + Shield;
  - Spatie permission / medialibrary / data / model-states / webhook-client/server / opening-hours / simple-excel / icalendar / pdf / health;
  - owen-it auditing, Sanctum, Pennant, Scout, Pulse, laravel-phone, twig, symfony html-sanitizer, kalnoy nestedset, scramble;
  - dev: Pest 5, Larastan, Rector, Boost.
  - Reverb, Horizon and Meilisearch come when their services are started.
- **Modules (first cut):** platform, iam, org, vehicle, pricing, crm, quotation, booking, accounts, stock, service-spares, reports, legacy-sync.
- **AI context:** the same `.ai/` layout as this repo, with Track B rules (Filament, Actions/Queries/Data, Pest).
- **Risk:** MED (new project, environment) · **Approved-by:** user (DEC-015, plan §3 / §8) · **Reversal:** delete the `xceler8` directory and database; nothing in `xlrm` depends on it.

### DEC-033 | 26-09-2026 22:30 | Programme | Track B paused; focus on Track A UAT
- **Decision:** the user paused Track B at the end of B0 (xceler8 committed at `d9009db`, see its B-DEC-003). All effort goes to Track A for the UAT deadline. Finalised Track A patterns will then be recreated in Track B faster.
- **Approved-by:** user.

### DEC-034 | 26-09-2026 22:50 | A3 (UAT) | UAT scope and priorities (target: 3 days, with the booking team's merge)
- **In scope:** Org/HR/User admin; Vehicle master & Pricing.
- **Out of scope:** Sales (Enquiry/Quotation/Booking/Accounts). Another team owns it; their code merges into this branch later, after which we re-check what's broken. **We don't touch Sales code from now on**, except the menu-only hiding of the 8 broken booking report links (user choice).
- **HR data entry:** no UI for Employee / Person Address / Person Banking create-edit (BUG-154). A bulk create/update **importer** is enough to onboard test users. The broken create/edit entry points are hidden; the lists stay.
- **Merge note:** Sales-area files this branch already changed before the scope split are listed in `.ai/state/current.md` for the merge.
- **Approved-by:** user.

### DEC-035 | 26-09-2026 23:20 | A3 (UAT) | User bulk importer = `import:users` (`StandaloneUsersImport`); fix identity corruption (BUG-162)
- **Finding (xlrm_testing):** the importer is fed **every** sheet of the master workbook. The `Reporting` sheet also has an "Emp Code" column but no name/PAN/Aadhaar/mobile, so for its rows the importer generated sequence person codes, created nameless persons (+25 per run), and **re-pointed existing employees and users to them** (6 after two runs). Live `xlrm` is unaffected (never imported).
- **Decision:**
  - (1) A workbook wrapper that reads only `Users_Import` (other sheets ignored).
  - (2) An existing employee keeps its `person_code` (identity is immutable), so the importer never re-points it.
  - (3) Rows without the mandatory Employee Name are skipped with a logged reason.
  - A clear end summary: created / updated / skipped / failed.
  - The test proves idempotency and identity stability on a fresh `xlrm_testing` copy.
- **Risk:** MED (import path used for UAT onboarding) · **Approved-by:** auto (clear data-integrity bug fix inside the user-approved importer scope) · **Reversal:** revert.
