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

### DEC-036 | 26-09-2026 23:55 | A3 (UAT) | Web user import rebuilt on the fixed importer; export and history routes removed
- **Decision:**
  - `org/user/import` (form, template, POST) now runs `UsersImportWorkbook` / `StandaloneUsersImport` (DEC-035) and shows created/updated/skipped/failed plus row issues.
  - The template matches the `Users_Import` columns.
  - A "Bulk import" button on the Users list is shown only with `ORG_USER_IMPORT`.
  - Removed `org/user/export*` and `org/user/import/history` routes and methods: their views were never built (BUG-043) and the exporter crashes on missing pivots (BUG-158). Export isn't needed for UAT.
  - Deleted the broken `App\Services\Importers\UserImporter` (BUG-075; its only caller was replaced). `UserExporter` is kept, dead but pending the BUG-158 decision.
- **Note:** the import runs inside the request (~450 rows ≈ 1 minute locally). A queued version belongs in Track B.
- **Risk:** MED · **Approved-by:** user (importer instead of HR forms, DEC-034) · **Reversal:** revert.

### DEC-037 | 27-09-2026 00:30 | A3 (UAT) | Hide broken HR create/edit; hide out-of-scope broken menu links
- **HR (BUG-154, per DEC-034):**
  - Employee, Person Address and Person Banking keep their (working) lists.
  - Create buttons are removed.
  - Row "Edit" becomes "Open person" (`org/person/{id}/edit`, whose inline contacts/addresses/banking editing works).
  - The old `create` / `{id}/edit` URLs redirect to the list; `store`/`update` are no longer registered.
  - Employees are created and updated via Users → Bulk import or the User screen.
- **Menu:** hide Vehicle → Brand (no table, BUG-009); the 8 booking report links (missing tables, BUG-122; user choice — menu only, Sales code untouched); Spares links (module broken, out of UAT scope: BUG-030/031/032/116).
- **Risk:** MED (UAT-visible, approved scope) · **Approved-by:** user (DEC-034) · **Reversal:** revert.

### DEC-038 | 27-09-2026 01:10 | A3 (UAT) | Remove three dead in-scope links (org-demo, sub-segment brand AJAX, Price List)
- **Decision:**
  - Delete the `org-demo` route, `OrgDemoController` and its view. The page 500s via the retired Posts model (BUG-049), and nothing links to it.
  - Delete the `vehicle/sub-segment/segments/{brandCode}` route: the method never existed and the only caller is already commented out (BUG-010/065). Remove that dead JS block too.
  - Hide the Sales-config "Price List" menu link: `admin/pricing` has no route or screen, so it always 404s (BUG-069). Pricing screens stay reachable from the Pricing menu.
- **Options:** implement the pages instead. Rejected: there's no brand table and no price-list spec; both would be new features.
- **Risk:** LOW (dead or 500 surfaces only) · **Approved-by:** auto (the plan §2 drop list covers demo pages) · **Reversal:** revert the commit.
- **Addendum (01:40):**
  - All `vehicle/brand*` routes are replaced by redirects to `vehicle/segment`. The Brand controller, request and views stay in the tree, unrouted, for Track B reference.
  - Deleted the unreferenced brand writers `Imports/Sheets/SegmentSheet`, `Imports/Concerns/MasterDataSeeder` and `CodeGenerator`: no callers, and they write to a missing table and column.

### DEC-039 | 27-09-2026 02:00 | B2b (Track B, planning) | Ticket intake channels: staff UI + API now
- **Decision:** SUP-DEC-003 is resolved. Tickets can be raised from (1) the internal staff UI and (2) the API (mobile app and other systems). The customer portal and inbound email are deferred, not rejected.
- **Approved-by:** user (27-09-2026) · **Reversal:** add channels in a later phase.

### DEC-040 | 27-09-2026 02:05 | A3 (UAT) | User & RBAC export workbook that round-trips through the user importer
- **Decision:** add a reusable export (Maatwebsite `WithMultipleSheets`) via `php artisan users:export-rbac` and a button on Users → Bulk import (permission `ORG_USER_EXPORT`, which already exists). Sheets:
  - `Permissions` (Module → Process → Permission) and `Roles` (designations + permissions): read-only.
  - `Users_Import`: editable columns use the importer's own headers. Read-only columns are prefixed `[Read-only]` so the importer ignores them.
  - `User_Scopes`: one row per user + scope type + value; this is how multi-select is done.
  - Hidden `Lists` sheet with named ranges.
- **Dropdowns:** every master-data cell is a list validation (stop on invalid input) with the label `Name (CODE)`. Scope and primary org lists start with `ALL`. The User_Scopes value list depends on the scope type (`INDIRECT`).
- **Why not comma lists:** Excel list validation allows one value per cell. Multi-select needs VBA (.xlsm, blocked by most mail and AV policies) or free text (no validation). One row per value keeps every cell validated.
- **Importer semantics needed for a safe round-trip:**
  - Label parsing: `Name (CODE)` → CODE.
  - For a user listed in `User_Scopes`, the listed set becomes their scope (others deactivated with `to_date`, never deleted; primary codes always kept).
  - Employee columns absent from a sheet are left untouched instead of nulled or defaulted.
  - `Employee Status` and `Login Active` are honoured when present.
  - Excel date serials are parsed.
- **Risk:** MED: it changes how the bulk importer treats missing columns (safer) and adds scope removal (only via the new sheet). **Approved-by:** user request (27-09-2026) · **Reversal:** revert the commit.

### DEC-041 | 27-09-2026 03:30 | A3 (UAT) | Merge the booking team's origin/stage with feature/integrations
- **Decision:**
  - Merge `origin/stage` (93 commits, booking team) into `stage` with our 18 Track A commits.
  - Conflict rules: their Sales / booking / import work wins; our Track A fixes are kept or re-applied on top.
- **Resolutions:**
  - `app/Jobs/oldImportEnquiriesJob.php`: stays deleted. It had no references; they had moved its logic into `ImportEnquiriesJob`.
  - `routes/backpack/core.php`:
    - Import routes: theirs, via the new `Admin/Import/*` controllers. The `org-demo` route is dropped (its controller was deleted, DEC-038).
    - Brand redirect (DEC-038), sub-segment routes and HR create/edit retirement (DEC-037): ours. Their side only reformatted these.
  - `menu_items.blade.php`: their reformatted version, with our hides re-applied: Price List (DEC-038), Erroneous Entries and Pending Quotations (DEC-023; still no routes). Brand and Role are already hidden in their version. Reports and Spares are decided by smoke results.
  - Booking migrations:
    - Their `sale_type` (unsigned tinyint, codes 1/2) and `final_data`/`votf_no` migrations are guarded with `hasColumn`, because our DEC-026 migration already added `sale_type`/`final_data` on local DBs.
    - Our migration now uses their `sale_type` type.
    - A new migration converts the local varchar `sale_type` (0 rows set) to their type.
- **Risk:** MED (shared branch) · **Approved-by:** user (27-09-2026, "merge … resolve conflicts") · **Reversal:** local tags `backup/feature-integrations-pre-merge` and `backup/stage-local-pre-merge`; revert the merge commit.

### DEC-042 | 27-09-2026 | A3 (UAT) | Enable Backpack's guard-switch middleware (BUG-055)
- **Decision:** enable `UseBackpackAuthGuardInsteadOfDefaultAuthGuard` so that `auth()`, `@can` and `Gate` resolve the admin user in admin requests, and pin `User::$guard_name = 'web'`.
- **Why the guard pin:** with only the middleware on, Spatie resolved permissions against the switched default guard (`backpack`), while every permission is stored under `web`. Every non-superadmin permission check failed. The pin restores the previous permission semantics exactly.
- **Verified:**
  - Full smoke of all 169 parameter-free admin GET screens, as user 1 and user 40, is identical before and after.
  - 231 tests pass. New `AdminAuthGuardTest`.
- **Approved-by:** user (27-09-2026, "switch if it broke nothing") · **Reversal:** re-comment the middleware line.

### DEC-043 | 27-09-2026 | A3 (UAT) | Disable the 34 users that have no role (BUG-090/166)
- **Decision:** set `users.is_active = 0` for the 34 users whose employees carry retired designation codes (`MAN`×18, `CNS`×6, `DSA`×3, `GM`×2, `RTO`×2, `API`, `SWD`, `TST`). None had a role, scopes, or a login ever.
- **Scope:** disable, not delete. Employee and person rows stay, so bookings, enquiries and reporting-manager references keep resolving. Admin and OTP logins both refuse inactive users.
- **User ids:** 41,33,34,36,37,48,52,44,45,47,6,7,9,11,15,16,17,18,19,20,21,22,25,26,27,29,30,31,32,38,43,46,39,42.
- **Backup:** `storage/app/backups/xlrm-users-disabled-27-09-2026.sql` (gitignored).
- **Other environments:** set `Login Active = No` for these Emp Codes in the users workbook and import it.
- **Approved-by:** user (27-09-2026, "disable or remove them permanently") · **Reversal:** set `is_active = 1`, or re-import the backup.

### DEC-044 | 27-09-2026 | A1 (purge follow-up) | Remove dead code the 26-09 purge missed; fix or remove pivot-table relations (BUG-022/024/037/081/082/084/158)
- **Remove (all reference-checked: no route or caller, or callers removed in the same change):**
  - `VehicleAccessoryCrudController`: `Route::crud` registered nothing. Also its `vehicle-accessory` route line, 2 views and the export button view, which point at routes that never existed.
  - `Services/Exporters/UserExporter` (its route was removed in DEC-036).
  - `Services/Importers/RulesUserImporter` and the deprecated `UserDataScope` model (table missing).
  - `DesigDeptTreeCrudController` (unrouted; the `DesigDeptTree` model stays).
  - `DashboardController::getSuperAdminDashboard/getScopedUserDashboard` (never called).
  - The relations to non-existent `xlr8_admin_emp_*_pivot` tables: `User::branches/locations/departments`, `Employee::branches/locations/departments`, `Vertical::employees/employeeAssignments`, `Location::employeeAssignments`, and the 4 unreferenced `Employee{Branch,Department,Location,Vertical}Assignment` models on those tables.
- **Fix:** `Location::branch()` and `Branch::primaryEmployees()` join on `Branch.code`; `branch_code` is always NULL.
- **Risk:** LOW (dead code) · **Approved-by:** auto (plan §2 drop list: unrouted controllers, unused models/services) · **Reversal:** revert the commit.

### DEC-045 | 27-09-2026 | A0 (platform) | composer.json for PHP 8.4; trim unused packages; env-driven config/app.php
- **Decision:**
  - `php` → `^8.4`. Project name/description updated.
  - **Removed (no usage in app/config/routes/views/tests):** `graphp/graph`, `intervention/image`, `spatie/laravel-translatable`; dev `markwalet/laravel-changelog` and `laravel/sail` (Laragon only).
  - **Google API services:** only Sheets and Drive are used, so Google's supported `Google\Task\Composer::cleanup` keeps just those. This removes about 37k files and fixes the stalled autoload dump.
  - **In-constraint updates:** `composer update` (minor/patch only).
  - **Majors deferred until after UAT, as each is breaking for both teams' code:** Laravel 13, maatwebsite/excel 4, spatie/laravel-permission 8, kreait/firebase-php 8, PHPUnit 12/13, l5-swagger 11, tinker 3, kalnoy/nestedset 7.
  - `config/app.php`: every value env-driven with sane defaults; `faker_locale` en_IN. The timezone is unchanged (Asia/Kolkata, the booking team's value), but see BUG-169.
- **Deploy risk:** `stage`, `uat` and `main` auto-deploy with `composer install` on cPanel. These servers must run PHP ≥ 8.4 before this reaches them, or the install fails after `artisan down`. Held on `dev/admin` (which doesn't deploy) until confirmed.
- **Approved-by:** user (27-09-2026, "add/upgrade/remove packages as and where seems fit") · **Reversal:** revert `composer.json`/`composer.lock`.
- **Addendum (autoload speed, user request):**
  - **Root causes of the slow or stalled `dump-autoload`:**
    1. `google/apiclient-services` shipped 37k files. The cleanup now keeps 2 services.
    2. Four ambiguous vendor classes were duplicated in `laravel/pint/app` and in `league/flysystem/src/Local`. Both paths are now in `exclude-from-classmap`.
    3. Three of our files broke PSR-4:
       - `XlInsurer` ×2 declared `class Xlinsurer`. Fixed the case, which is also a latent Linux autoload bug.
       - `tests/Unit/Services/HRJourneyServiceTest.php` declared an `App\…` namespace and targeted the retired Post model. It never ran ("No tests found") and is removed.
    4. `config.optimize-autoloader: true` forced a full classmap scan on every local dump. Set to `false`: production is unaffected because `deploy.yml` passes `--optimize-autoloader`.
  - **Result:** local `composer dump-autoload` takes about 5s (the optimized one about 30s), where before it hung. No warnings remain.
  - Windows Defender real-time scanning of `vendor/` still adds time. Excluding `D:\laragon` is a machine setting for the user.

### DEC-046 | 27-09-2026 | A3 (UAT) | Timestamps stay IST; add an IT department
- **Timezone (BUG-169):** keep `Asia/Kolkata`. Timestamps written before the 26-09 switch stay as they are (UTC values, not converted). BUG-169 is closed as accepted, and the architecture rule now says timestamps are stored in IST.
- **IT department:**
  - Create department `IT`. Its default division is the existing division `IT`, which moves from Admin (`dept_code ADM → IT`). Division codes are unique, the `IT` code is unchanged, and nothing referenced Admin → IT.
  - Idempotent `ItDepartmentSeeder`, so other environments run `php artisan db:seed --class=ItDepartmentSeeder`.
  - BMPL-0365 and BMPL-0630 get primary department and division `IT` plus the matching scopes (dump: Primary Department = IT).
- **Server PHP:** the user confirmed the cPanel servers run PHP 8.4, which clears the DEC-045 deploy gate.
- **Approved-by:** user (27-09-2026) · **Reversal:** move the division back to ADM and delete the department.
