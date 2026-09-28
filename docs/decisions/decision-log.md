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

### DEC-047 | 27-09-2026 | A1 (purge follow-up) | Remove remaining dead IAM/legacy pieces (BUG-006/017/076); close stale tracker items
- **Remove (reference-checked):**
  - `CheckPermission` middleware and its `checkPermission` alias: no route uses it, and admin code gates inline.
  - The `App\Models\Core\ReportingHierarchy` model: no references.
  - Orphan views `admin/graph-edge/*` and `admin/graph-node/*`: their controllers were deleted earlier.
  - `RBACService::getAccessibleResources()` and `getModelClassForResourceType()`: never called, and they point at a missing brand table.
- **Keep:**
  - `ScopedQuery`, needed if data scoping is switched on (BUG-083 decision).
  - `ApprovalService` and the Graph models, used by `DocService`.
  - `BrandCrudController`, referenced by the booking team's `AdminImportController`.
- **Close as already fixed:**
  - BUG-080: the suite is fully green (240 passed).
  - BUG-147 and BUG-155: the dead-route checker finds none.
  - BUG-017: the listed dead classes are gone; `ScopedQuery` is kept on purpose.
- **Risk:** LOW · **Approved-by:** auto (plan §2 drop list) · **Reversal:** revert the commit.

### DEC-048 | 27-09-2026 | A3 (UAT) | Vehicle masters: codes immutable on edit; variant = one row per colour (BUG-171/172)
- **Facts:**
  - Colours are stored as separate variant rows: `code` plus `color`/`color_code`, 2,652 rows for 652 codes (user, 27-09-2026). `xlr8_vehicle_color` and the Colour screen are legacy (its menu is already hidden).
  - Editing any vehicle master re-saved its `code` through the space-stripping `code` transform. That orphaned children keyed on the old code: 588 variants and 584 legacy colour rows no longer match a model (for example `THAR ROXX` vs `THARROXX`).
- **Decision:**
  1. `code` is immutable on update for segment, sub-segment, model, variant and colour (as for the Org masters). The edit forms show it read-only.
  2. `VariantRequest`: `code` is unique per (`code`, `color_code`) among live rows, not table-wide. Before, every multi-colour variant failed to save with "code already taken".
  3. Variant form and model accept `color` and `color_code`.
  4. The variant deactivation guard no longer counts legacy colour-table rows.
- **Not done (needs the user):** repairing the existing mismatched codes. The canonical form (spaced OEM code vs squashed) is the user's call.
- **Risk:** MED (UAT-visible fixes) · **Approved-by:** auto (obvious bug fixes in UAT scope) · **Reversal:** revert the commit.

### DEC-049 | 27-09-2026 | A3 (UAT) | Canonical code format: upper-case with hyphens (THAR-ROXX)
- **Decision (user, 27-09-2026):** codes of this kind use upper-case letters and digits, with hyphens where the name has spaces: `THAR-ROXX`, `NON-XUV`, `E-ALFA-PLUS`. This applies to keywords and codes alike.
- **Code:** the shared `uppercase_alphanumeric_dash_underscore` transform (used by 18 models and the global `code` rule) now turns whitespace into a single hyphen instead of deleting it. Codes without spaces are unchanged.
- **Data (migration + `vehicle:normalise-codes` command, idempotent, dry-run available):**
  - **Model codes:** each spaced/squashed family (e.g. `THAR ROXX` / `THARROXX`) becomes the hyphenated form, in `xlr8_vehicle_model.code` and every reference column (`model_code` in all tables, `model` in CRM/booking tables, user scopes).
  - **Sub-segment:** `NON XUV` → `NON-XUV`, in the sub-segment table, every `sub_segment_code` column, user scopes and employees.
  - Old → new maps are written to `storage/logs/vehicle-code-normalisation-<db>.json` for reversal. The migration aborts if two master rows would collide.
- **Keyword (key-value) codes:** new and edited ones follow the rule. Existing ones (about 1,900 with spaces) are converted only after a reference audit, because other tables may store them as plain text.
- **Not codes:** consultant names (`sc_code`), and insurer and financier names, are left as they are.
- **Risk:** HIGH (mass remap). **Approved-by:** user. Backup taken before running locally. **Reversal:** the JSON map.

### DEC-050 | 27-09-2026 | A3 / project-wide | One field-rule set per entity, enforced by the entity service (SSOT); no correcting old data
- **Decision (user, 27-09-2026):**
  - **Old data:** stop correcting it. A fresh copy from the latest import replaces it.
  - **Field rules:** every field of an entity has exactly one definition of format, transformation, validation and label. It is enforced for every create/edit, whether from a CRUD screen, an import or an API, only through that entity's service. This is a global project rule.
- **Design:**
  - `App\Support\Entity\Field` is the fluent field definition: label, transforms (the `HasColumnTransformations` pipeline names), rules, unique scope, immutable-on-update.
  - `App\Support\Entity\EntityService` is the base for every entity service. `create()`, `update()` and `upsert()` run normalise → validate (throws `ValidationException`) → business guards → persist through Eloquent, in a transaction.
  - Models that declare `$entityService` take their transform backstop from the service's fields, so there is no second copy.
  - Controllers and importers only call the service. FormRequests no longer define rules for migrated entities.
- **Withdrawn:** the vehicle code normaliser, its command and the two data-correction migrations (DEC-049 data part; never deployed). The DEC-049 hyphen format stays, as a field rule.
- **Roll-out order:** Vehicle masters (segment, sub-segment, model, variant) first; then Org masters, Person, Employee/User (+ importer), KeyValue, Pricing entities.
- **Risk:** MED (write paths change) · **Approved-by:** user · **Reversal:** revert per-entity commits.

### DEC-051 | 27-09-2026 | A3 (UAT) | Purge the local vehicle master data before a fresh import
- **Decision (user, option 1):** hard-delete all rows of `xlr8_vehicle_segment`, `xlr8_vehicle_subsegment`, `xlr8_vehicle_model`, `xlr8_vehicle_variant` and the legacy `xlr8_vehicle_color` in the **local** `xlrm` only. The data is reloaded through the vehicle import, which now writes only through the entity services (DEC-050).
- **Not touched:**
  - Pricing tables, CRM/booking references and key-values.
  - `xlrm_testing`: it keeps its copy so the vehicle tests have data. Don't `testing:refresh-db` until the fresh import is in.
- **Backup:** `storage/app/backups/xlrm-vehicle-masters-pre-purge-27-09-2026.sql`. No foreign keys reference these tables.
- **Risk:** HIGH (destructive, local) · **Approved-by:** user · **Reversal:** restore the backup.

### DEC-052 | 27-09-2026 | A3 (UAT) | Org masters on entity services (DEC-050 roll-out)
- **Scope:** Branch, Location, Department, Division, Vertical and Designation.
  - Their services become `EntityService` subclasses. The FormRequests and model `$columnTransformations` are removed; the rules live in `fields()`.
  - Dependency guards, head office, the reports-to rank check, the default division and media move into `beforeCreate` / `beforeUpdate` / `afterSave`.
  - The RBAC master import sheets (department, division) write through the services.
- **One rule per field where the old copies disagreed:**
  - `phone`: cleaned by `IdentifierService::cleanMobile()` (+91 / 0 prefixes removed), must be 10 digits, on create **and** edit. Before, Branch accepted any string on edit.
  - Department `is_active`: the same rule on create and edit.
- **Framework additions:**
  - `Field::virtual()` for validated non-column inputs (image/documents uploads).
  - Callable transform steps.
  - The `afterSave` hook.
- **Approved-by:** user (DEC-050 roll-out order) · **Reversal:** revert.
- **Addendum (DEC-052):** retired `import:rbac-master` (`ImportRbacMaster`, `RbacMasterImport`, `BaseSheetImport` and its Branch/Department/Division/DesignationTree/Post/UsersImport sheets).
  - None of the sheets implements a Maatwebsite `To*` concern, so the import silently processed nothing while reporting "No errors" (proven on `xlrm_testing`: 0 inserted, 0 updated, data unchanged). `skip()` was also undefined.
  - It was a direct-to-table write path (DEC-050 violation).
  - Replacements: org masters go through the admin screens (entity services); users through `import:users` / Users → Bulk import.

### DEC-053 | 27-09-2026 | A3 (UAT) | Person, contacts, addresses and banking on entity services (DEC-050 roll-out)
- **Services (`App\Services\Person\`):** `PersonRecordService`, `PersonContactService`, `PersonAddressService`, `PersonBankingService`.
  - `PersonService` keeps its read API (`find/search/get/setPrimary`). Its `upsert/upsertContact/upsertAddress/upsertBanking` now delegate to these, so existing callers keep working.
  - Callers: the Person screen (and its inline contacts/addresses/banking), the standalone Contact screen, and the user importer.
  - 5 FormRequests deleted (`Person`, `PersonContact`, `PersonAddress`, `PersonBankingDetail`, `Employee`).
  - The unrouted create/store/edit/update methods of the retired Employee/Address/Banking screens (DEC-037) were removed.
- **Field rules (one set for screen and import):**
  - Names: Title Case for individuals; a legal entity's display name is kept as typed.
  - Missing name parts are split from the display name. "A B" → first A, last B; before, B became both middle and last.
  - `person_code`: derived when not given — individual: Aadhaar → PAN; legal entity: PAN → TAN; else `PERS-######`. Immutable. The importer no longer derives it itself.
  - Aadhaar: spaces/dashes removed. PAN/TAN/GSTIN: upper-case.
  - Aadhaar/PAN/TAN/GSTIN are unique across all persons, **including deleted ones**, matching the DB unique keys (was a DB error).
  - Enums (gender, marital status, salutation, types) are matched case-insensitively.
  - Dates accept any parseable date or an Excel serial.
  - `contact_detail` is formatted by type: Mobile 10 digits; Email lower-case and valid; Landline/Fax digits.
  - Pincode 6 digits; IFSC pattern; MICR 9 digits; account number alphanumeric.
- **Type slots** (`TypedSlots`; a DB enum plus a unique key per person):
  - No type given → Primary if free, else the next free type.
  - Asking for Primary promotes the row and demotes the old one.
  - Any other used slot is a validation error.
  - Promotion is a swap: the old Primary takes the promoted row's former slot (`SwapsPrimarySlot`).
- **Deletes of contacts/addresses/banking are permanent (BUG-175):** the unique keys cover soft-deleted rows, so a trashed row blocked its slot forever.
- **Kept as screen policy (not a field rule):** the Person create screen requires a primary mobile. Its format is the contact rule.
- **Importer:**
  - Blank person cells are left out, so the stored values are kept (as before).
  - A value breaking a field rule fails the row with the rule's message; `failures()` lists each failed row.
  - On `xlrm_testing`, 2 existing employees fail the round trip for bad stored data: an 11-digit Aadhaar (BMPL-0557) and an e-mail containing a space (BMPL-0669). Per DEC-050 the old data is not corrected.
- **Framework:**
  - `EntityService::derive()` for computed fields.
  - Defaults are applied before validation.
  - Immutable fields are dropped after normalisation on update.
  - `Field::choice()` and `Field::date()`; `unique(includeTrashed:)`.
  - `describe()` tolerates callable transforms.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED (stricter validation on the Person screens and the user import) · **Reversal:** revert.

### DEC-054 | 27-09-2026 | A3 (UAT) | Employees, login accounts and data scopes on entity services (DEC-050 roll-out)
- **Services:** `Org\EmployeeService`, `IAM\UserService`, `IAM\UserScopeService`.
  - **Callers:**
    - The User screen: create, edit, suspend, revoke, activate, add-on scopes.
    - The `Users_Import` sheet (`StandaloneUsersImport`): it no longer writes `xlr8_admin_employee`, `users`, `xlr8_admin_user_scopes` or `xlr8_admin_person_user_types` with `DB::table`; person user types go through `PersonUserTypeService::assign`.
    - The `User_Scopes` sheet.
    - `HRJourneyService` (designation changes).
  - `UserRequest` keeps only the workflow's inputs (user type, role, permission overrides, change reason/date/remarks) and the screen policy that an employee is onboarded with a full org placement. Every field format and existence rule is in the services.
  - The Employee model's `$fillable` now lists every column. `employment_status`, `oem_id` and others were missing; this only worked before because the importer bypassed Eloquent.
- **Field rules:**
  - **Employee:**
    - `code` must be `BMPL-####`; it's generated when blank and is immutable.
    - `person_code` is fixed at create.
    - Org/vehicle placement codes must exist (live rows).
    - `desig_code` always mirrors `designation_code`.
    - The employment enums are matched case-insensitively.
    - UAN is 12 digits and unique; the ESI number is unique.
  - **User:**
    - The username is lower-case (a-z 0-9 . _ - @) and unique, including deleted accounts.
    - The password is taken exactly as typed (not trimmed), minimum 8, stored hashed; blank on edit keeps it.
    - Roles, permission overrides and `bypass_data_scoping` stay IAM decisions of the calling workflow, not data fields.
  - **Scope:**
    - Type ∈ branch/location/department/division/vertical/segment/sub_segment/model/variant.
    - The code must exist in that type's master.
- **One revoke semantics:**
  - Before, the User screen soft-deleted dropped add-on scopes while the User_Scopes sheet deactivated them.
  - Now both deactivate (`is_active = 0`, `to_date` = today) and keep the row. Granting reuses the row (restoring a deleted one).
  - Readers already use active rows only. Before, the importer's grant also failed with a duplicate key on a soft-deleted row.
- **Framework (applies to all entity services):**
  - **On update only changed values are validated.** A stored value left as it is (legacy data, e.g. the 36 employees whose designation code is missing from the designation table, BUG-090) no longer blocks an edit of another field. Required fields must still be present.
  - Consequence: re-importing an unchanged export is again a strict no-op (0 failed rows), including the two persons with bad stored Aadhaar/e-mail noted in DEC-053.
  - New `Field::raw()`: no trimming or transforms, used for passwords.
- **Atomic writes:**
  - Each `Users_Import` row is one transaction (person, employee, user, scopes, role).
  - User-screen onboarding and edit are one transaction.
  - A rejected value leaves nothing half-written; before, a person and employee could be saved without their user.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED (User screen and user import validation; IAM orchestration unchanged) · **Reversal:** revert.

### DEC-055 | 27-09-2026 | A3 (UAT) | Keyword masters and values on entity services (DEC-050 roll-out); backstop transforms only changed attributes
- **Services:** `Utils\KeywordMasterService`, `Utils\KeyvalueService`. Reads stay on the cached `KeywordValueService`; its cache for the keyword is cleared after each write.
  - **Callers:**
    - The Keyword and Key Value screens (`KeyvalueRequest` / `KeywordMasterRequest` deleted).
    - The vehicle import (`AdminImportController::getOrCreateKeyValue`, was `DB::table()->insertGetId`).
    - The enquiry import (`ImportEnquiriesJob`, booking team's file, minimal change: its insert and its parent-list `UPDATE` now call the service; matching/caching untouched).
  - The models' `$columnTransformations` and the `Keyvalue` save hook (upper-casing `key`, code fallback to key) moved into the service.
- **One rule set where the copies disagreed:**
  - **Code uniqueness:** the screen rule was table-wide, while the data and the enquiry import use one code per keyword (the same code exists under several keywords). It is now unique **within its keyword**.
  - **`parent_id`:** the screen allowed one integer, while the enquiry import stores a comma-separated parent list. Now a comma-separated id list, with `addParent()` appending one.
  - **`extra_data`:** the screen posted JSON text but validated `array`, so any filled-in value failed. Now JSON text is decoded (`Field::json()`), and invalid JSON is reported.
  - **Codes:** the standard code format (DEC-049), fixed once created. The vehicle import looks existing values up by the normalised code before creating one.
  - **A value's keyword must exist.** Two keywords were in use without a master row: `PERMIT` (vehicle import, 4 values) and `FOLLOW_UP_REMARKS_TYPE` (19 values). Migration `2026_09_27_130000_add_missing_keyword_masters` adds them (idempotent, through the service). It has run on local `xlrm` and `xlrm_testing`; other environments get it on deploy.
- **Legacy codes are left as they are** (DEC-050: no correcting old data): 1,903 stored codes contain spaces (mostly `SPARE_BIN`).
- **Framework — BUG-176:** `HasColumnTransformations` re-ran every column's pipeline on **every update**, so editing any field of a row whose stored code had spaces silently rewrote the code (`OLD BIN A1` → `OLD-BIN-A1`), orphaning references. This is the BUG-171 pattern, and it applied to keyword values and every model with the backstop. Now, on update, only the changed attributes are transformed.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED (Key Value screens, vehicle/enquiry imports create keyword values through validation) · **Reversal:** revert; migration `down()` removes the two masters.

### DEC-056 | 27-09-2026 | A3 (UAT) | Pricing rules on entity services (DEC-050 roll-out, pricing group 1 of 4)
- **Services (`App\Services\Vehicle\Pricing\Rules\`):** `RtoRuleService`, `TcsConfigService`, `InsBaseRuleService`, `InsIdvSlotService`, `InsDefaultService`, `InsAddonRateService`.
  - **Callers:**
    - The RTO Rules and TCS screens (their inline validation was removed).
    - The Insurance + RTO rules workbook (`RulesWorkbookService`), which no longer writes with `DB::table`. It only maps sheet columns; the dead `importGeneric()` and its `onlyExisting()` / `expireTable()` helpers are gone.
  - WEF expiry of the live set is the services' `expireActive()`: `is_active = 0` and `expired_on = WEF`; history is never deleted (Machine Spec v3.1.1).
- **Spec-conformant field rules, where screen and import disagreed:**
  - **`wheels`:** the column is a tinyint. The screen accepted 2–16; the workbook sent scope text such as `ANY`, which failed silently. Now ANY/ALL/`*`/blank = all (null), else a whole number 2–255.
  - **Permit / Fuel:** synonyms are applied first on the screen too (before, only the import applied them).
  - **Amounts** (NOT NULL, default 0):
    - `₹` and separators are ignored ("10,00,000" → 1000000).
    - Sheet blank markers (`-`, NA, N/A, Nil, None) and blanks count as 0, as before.
    - **Other text is now reported as a row error** ("Row N: …"). Before, it was silently stored as 0.
  - **Surcharge / IDV:** percent parsing ("95% of Invoice" → 95), as in the importer.
  - The importer's sheet interpretation is unchanged: tax factor comes from Tax Basis when numeric, else Tax Slab; one defaults row per listed company, the first is the default; IDV formula columns.
  - **Plan "OD+TP" → od/tp years:** now derived by the service; an unrecognised plan is still never guessed.
  - **TCS:** saving an active configuration deactivates the others (the screen's rule is now the service's).
- **Integrity:** an insurance base rule and its IDV slots are saved in one transaction.
- **Models aligned to the real columns:** the models' `$fillable` listed non-columns (`InsDefault.default_company`/`company_priority_*`, `InsBaseRule.code`/`model_code`/`imt_23_rate`, `RtoRule.seater`) and missed real ones (`import_session_id`, `tax_basis`, `surcharge_formula`, `extra_json`…).
- **Framework:**
  - `Field::number()`, `percent()`, `scope()` (with synonyms), `parseNumber()` / `isBlankMarker()`.
  - A blank value (or a transform yielding blank) takes the field's default.
- **Not changed:**
  - `InsDefault::getCompanies()` / `scopeActive()` reference non-existent columns but have no callers (noted).
  - Engine-written records stay with the engine: sessions, change flags, affected, snapshots, history.
  - `PricingResetService` is a local-only reset tool.
- **Next pricing groups:** add-ons/discounts/dealer charges/CSD, prices + vehicle profiles, accessories.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED (rules import now rejects non-numeric amounts per row) · **Reversal:** revert.

### DEC-057 | 27-09-2026 | A3 (UAT) | Add-ons, discounts and dealer charges on entity services (pricing group 2 of 4)
- **Services (`App\Services\Vehicle\Pricing\Addons\`):** `DealerChargeService`, `AddonService` (RSA / Shield), `DiscountService` (Exchange / Corporate…).
  - `AddonDiscountImportService` writes only through them. It keeps the sheet mapping and its skip decisions (no scope; all-zero dealer charges; no year amounts). Its `onlyFillable()` / `expireAddons()` / `expireDiscounts()` are gone.
  - **Group expiry** (`expireActive($wef, ['addon_type' => 'RSA'])`): an RSA import never expires Shield rows, and vice versa (spec pitfall).
- **Field rules (Machine Spec):**
  - **Scope:** ANY / ALL / blank = all, synonyms first (Segment, Permit, Fuel). On NOT NULL scope columns "all" is stored as `ANY` (addon/discount `model_code` as before, dealer-charge `segment`); elsewhere as null.
    - Before, an ANY-segment dealer charge failed on the NOT NULL column and was lost. Now it is saved. The engine treats ANY, blank and null alike.
  - **Amounts:** as DEC-056 (₹ and separators ignored, blank markers = 0, other text reported).
  - A dealer-charge row with only zero amounts is refused ("never seed zero-value rows"). The importer already skipped such rows.
  - **Discount total:** a blank total = OEM share + dealer share, and `amount` follows the total, as the importer computed.
- **Models:** `DealerCharge` / `Addon` / `Discount` `$fillable` now equal the real columns (`Discount.segment` is not a column; `default_allocation`, `is_conditional`, `linked_to` were missing).
- **Framework:**
  - `Field::scope(..., anyIsBlank: true)`.
  - `expireActive()` group filter.
  - The model backstop keeps a value when its pipeline would blank it (the service already resolved it to the field default, e.g. `ANY`).
- **CSD:** there is no live writer (only the dead legacy `XpricingHelper`), so there is nothing to route.
- **Found, not changed (needs owner, BUG-178):** `PricingEngineService::dealerCharges()` reads narrow rows (`charge_name` + `amount`). The importer writes the spec's WIDE columns (CP-06: incidental/fastag/trc/rto_tape/cod), so imported dealer charges add 0 to the pricing JSON. `scopeHit()` also checks a `model` column where the table has `model_code`.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED · **Reversal:** revert.

### DEC-058 | 27-09-2026 | A3 (UAT) | Price-list vehicles and prices on entity services (pricing group 3 of 4)
- **`VehicleService`** (price-list detect, Vehicle Info import) wrote segments, sub-segments, models, variants and keyword values directly, which bypassed the vehicle entity services of DEC-050. It now calls `SegmentService`, `SubSegmentService`, `VehicleModelService`, `VariantService` and `KeyvalueService`:
  - `findOrCreate*` look up by the **canonical code first** (DEC-049 hyphen form, e.g. THAR-ROXX), then the legacy spellings, then the name. New rows get the canonical code; before, they got the spaced code, which the backstop then hyphenated.
  - **Stubs:** `createStubFromPriceList()` goes through `VariantService` (inactive, taxi NO, colour from the last 2 chars — unchanged).
  - **Vehicle Info:** `applyVehicleInfo()` updates the model and the variant through their services. Names follow the screens' Title Case; before, they were upper-cased. Completeness is judged on the values as they will be stored. A value that breaks a field rule rejects the row (listed as rejected).
  - `copySpecifications()` goes through `VariantService`.
  - `VariantService` gains the three columns this path writes: `motor`, `gst_percent` (percent) and `shield_pack` (upper-case).
- **Prices:** new `Vehicle\Pricing\Prices\PriceService` (`xlr8_vehicle_pricing`).
  - Key: (OEM code, channel, WEF), fixed once created, unique including deleted rows (DB key).
  - Amounts are NOT NULL with default 0; GST is a percent; `expire()` closes the live row at the new WEF.
  - `PriceListPricingImporter` keeps the spec's WEF logic (same WEF → update; new WEF with a material change → expire, then insert) and the ex-showroom derivation. It now parses through `PriceService::normalise()` and writes through `create/update/expire`.
  - Blank or "no value" cells are left out, as before: a same-WEF update keeps the stored amount.
  - Other text in an amount now fails the row with the rule's message; before, it was silently dropped.
- **Models:** the `Pricing` `$fillable` now equals the real columns (`variant_code`, `*_elg` were non-columns); `Variant` gains the three fields.
- **Left as engine / pipeline state** (derived, not data entry):
  - profiles (detect stubs and completeness flags);
  - change flags, affected rows, snapshots, sessions.
- **Approved-by:** user (DEC-050 roll-out, "continue") · **Risk:** MED (Vehicle Info names now Title Case; bad cells reject rows) · **Reversal:** revert.

### DEC-059 | 27-09-2026 | A3 | Seeders write through the entity services and are idempotent (DEC-050 roll-out, last group)
- **`EntityService::firstOrCreate($match, $values)`:** matches on the normalised values, else creates through `create()` (validated); an existing record is never changed.
- **Converted seeders:**
  - **`ItDepartmentSeeder`** (runs on deploy through its migration): Department/Division/Employee services and `UserScopeService::grant()`.
  - **`MasterDataSeeder`** (the default `db:seed` path):
    - Before, it **truncated every Org and vehicle master table**, the roles table (designations) included, then bulk-inserted demo rows. Running `db:seed` on a real database wiped it.
    - Now each row is found by code or created through its service; a rejected row is reported.
  - **`SuperAdminSeeder`:** Person/User services, username `sup001`. It assigns the real `superadmin` role; the old `super_admin` role does not exist, so it failed.
  - **`KeywordKeyvalueSeeder`, `SiteSettingSeeder`:** these matched on a non-existent `keyword_master_id` column and could not run. They now go through the keyword services by keyword code + code.
  - **`CrmStatusSeeder`, `EnumToKeyValueSeeder`:** keyword services.
- **Verified:** each seeder was run twice inside a rolled-back transaction on `xlrm_testing`. Both passes succeed and the second creates nothing.
- **Not converted:** `ProductionRBACSeeder::createTestUsers()`. It writes columns and pivot tables that no longer exist (`email_primary`, `person_id`, `designation_id`, users `email`, employee branch/department pivots) and hard-codes real names and phone numbers, so it cannot run. Rewriting it means designing new demo users (owner's call). The IAM seeders (permissions/roles) are outside the entity rule.
- **Approved-by:** user ("continue" — DEC-050 roll-out) · **Risk:** LOW (seeders are fresh-install / idempotent) · **Reversal:** revert.
### DEC-060 | 28-09-2026 | A3 | Legacy helpers removed; every call goes through a service
- **Decision (user, 28-09):** there must be no call or wiring to `app/Helpers`. Each live call moves to the relevant service, which is extended where needed. Models are fixed, then all helpers are deleted.
- **New service reads (same shapes the callers and views use today):**
  - `OrgService`: `branchRows()`, `locationRows()`, `locationsByState()`, `serviceBranches()`.
  - `VehicleService`: `segmentOptions()`, `modelOptions()`, `variantOptions()`, `colorOptions()`.
- **Colours:** read from the variant's sibling colour rows (one variant row per colour, DEC-048). Before, they came from the retired `xlr8_vehicle_color` table, which new imports no longer fill, so the booking colour dropdown was going stale or empty.
- **Spares "service branch":** `XCommonHelper::getServiceBranch()` read a non-existent `X_Location.service_branch` and crashed (BUG-030). It is interpreted as **locations flagged `is_workshop`** (2 rows locally). This is a reversible interpretation; say if spares should use another flag.
- **`site_date()`** moves to `app/Support/helpers.php`, loaded through composer `autoload.files`. That file also hosts the FRS thin aliases (`setting()`, `feature()`). `app/Helpers/` is deleted.
- **Scope:** minimal edits in Sales/Booking and Spares (call replacement only, no flow change), approved by the user in the 28-09 plan.
- **Reversal:** revert.
- **Model double-check (class-resolution sweep of `app/`):** models no longer reference missing classes.
  - Removed `posts()` relations to the retired `Post` model (BUG-080) from Branch, Department, DesigDeptTree, Division and Location.
  - Deleted the unreferenced `EmpPostAssignment` model.
  - `Booking::segment()` now points at the vehicle `Segment` by `segment_code`; it used to point at a missing `EnumMaster` via a non-existent `segment_id`.
  - Removed unused imports of missing classes: `HasHashedMediaTrait` ×7 and Enquiry's `App\Traits\*`.
  - `Variant::get*Options()` now use `KeywordValueService::getEnum()`; the `KeywordHelper` class they called did not exist.
- **Retired-Posts code paths:** `OrgService::usersByPost()` (no callers) was removed. So was the posts loop in `RBACService::getUserPermissions()`, which read a relation that does not exist.
- **Sweep leftovers:**
  - comment-only or `class_exists`-guarded mentions (RBACService, SystemSettingServiceProvider, AccessoryExportService);
  - `ApprovalService` (retired in DEC-063);
  - `ExportController` (BUG-180, logged).
### DEC-061 | 28-09-2026 | A (platform) | Platform core: Settings, Notify, Chat, Docs (FRS §1–4)
- **Where and how:** built in Track A (user choice, 28-09), in the Track B style.
  - `App\Services\Platform\*` services, each the only write path.
  - Facades in `App\Support\Facades\*`.
  - `App\Support\Result` `{ok, code, message, data}`.
  - Events in `App\Events\Platform\*`, traits, Blade components.
  - Entity types map to model classes through `config/platform.php`, not an enforced morph map, because existing comm rows store class names.
- **Settings:** extends the existing `xlr8_utils_system_setting` and audit tables.
  - Scoped overrides (Company → Branch → Desk) live in a new `xlr8_utils_setting_scope` table.
  - Types: string, int, decimal, bool, json, encrypted. Encrypted values are stored with `Crypt` and never listed.
  - `SettingsChanged` event plus cache bust; `@setting`, `@feature`, `setting()`, `feature()`; `settings:cache` / `settings:clear`.
- **Notify:**
  - A new dispatch master, `xlr8_utils_noty_dispatch` (unique idempotency key).
  - Inbox rows stay in the existing tables so the mobile v1 API is unchanged: kinds N and M go to `noty_notification` (new `kind` column), kind A to `noty_alert`. New `dispatch_id` and `archived_at` columns.
  - FCM is sent by a queued job. The actor is not notified unless `notifySelf`; a zero audience returns `ok` with `sent: 0`.
- **Chat:** on the existing `comm_master` / `comm_thread` (nested set).
  - New thread columns: `kind` (EVENT / REMARK), `is_internal`, `edited_at`.
  - New tables `xlr8_utils_comm_subscription` (follow a thread) and `xlr8_utils_comm_remark_read`.
  - `EntityHistoryService` / `HasCommunications::addHistory()` remain as adapters, so Booking and v1 keep working.
- **Docs:** the models pointed at non-existent `xlr8_docs_*` tables (the core of BUG-139). They now use the real `xlr8_utils_docs_*` tables.
  - New document columns: kind (IMAGE / DOCUMENT / INFORMATION), collection, library path (entity / location / category / sub / item / FY), `info_body`.
  - Entitlements in `docs_access` (user / designation / department / scope / `parent`).
  - The cart is the user's `_temp` group. Soft delete, with a purge job.
  - `DocService` stays as the v1 adapter.
- **Permissions:** new `UTL_{SET,NOTY,CHAT,DOCS}_*` codes (web guard), minted by migration.
- **Approved-by:** user (28-09 plan) · **Risk:** MED (additive schema; mobile contract preserved) · **Reversal:** migration `down()` plus revert.
- **Addendum (28-09, at implementation):**
  - `xlr8_utils_comm_remark_read` was not built (read receipts are not needed by any screen yet).
  - The permission migration mints every platform process up front (NOTY, CHAT, DOCS, TASK, TCKT, APPR, TPL, COMM). Everyday codes (`UTL_DOCS_VIEW/UPLOAD`, `UTL_TASK_VIEW/CREATE`, `UTL_TCKT_VIEW/CREATE`, `UTL_APPR_VIEW/REQUEST`) go to every designation in one bulk insert; administration codes stay with SuperAdmin.
  - Access follows the record. Each `config('platform.entities')` row names its view permission (e.g. BOOKING → `SLS_BKNG_VIEW`); a model may refine it with `chatCanView()`. A document attached to a record with no explicit entitlements follows that record; only an unattached library document falls back to `UTL_DOCS_VIEW`.
  - UAT-visible: the topbar alerts / notifications / messages dropdowns showed hard-coded sample data and now show the user's real inbox (`<x-notify.bell>`). The Utilities menu gains My Inbox (everyone), Documents (`UTL_DOCS_VIEW`) and Settings (`UTL_SETTINGS_VIEW`).
  - `NotificationService` and `DocService` are now adapters over Notify and Docs (the legacy Google Vision tag path is removed). The v1 envelope and routes are unchanged.

### DEC-062 | 28-09-2026 | A (platform) | Task and Ticket utilities (FRS §5–6)
- **Tables:**
  - `xlr8_utils_task` and `xlr8_utils_task_person` (role OWNER / ASSIGNEE / FOLLOWER / SNOOPER; unique per task, user and role).
  - `xlr8_utils_ticket` and `xlr8_utils_ticket_person` (role REQUESTER / OWNER / ASSIGNEE / FOLLOWER / SNOOPER).
  - `xlr8_utils_ticket_counter` (branch × FY sequence, taken with `lockForUpdate`).
  - The six audit columns and soft deletes on task and ticket.
- **Writers:** `App\Services\Platform\Task\TaskService` and `App\Services\Platform\Ticket\TicketService` are the only writers (`Task` / `Ticket` facades). Models `App\Models\Utilities\{Task,Ticket}\*` use `HasCommunications` and `HasDocuments`, and define `chatCanView` / `chatCanRemark`, so snoopers read but never post.
- **Task:**
  - Types come from KeyValue `TASK_TYPE`. `ASSIGNED_TASK` and `SELF_TASK` are added; the existing `GENERAL` / `SELF` values stay and are treated as ASSIGNED / SELF.
  - The rights matrix of FRS §5.3 is one table in the service. Where the FRS is silent, `REOPENED` counts as an open state for INPROGRESS / HOLD / SUBMIT.
  - Group flag is derived. People edits rebuild associations and notify those added and removed.
  - Four inboxes: CREATED (owner), ASSIGNED, FOLLOWED, SNOOPED.
- **Ticket:**
  - Number `TCK/{branch}/{fy}/{seq:05d}`: FY as `26-27`; branch is the requester's primary branch, else `HO`.
  - Categories come from KeyValue `TICKET_CATEGORY` (HARDWARE, ACCESS, DATA, BUG, ENHANCEMENT, PROCESS); priorities from `TICKET_PRIORITY` (P1–P4). SLA hours come from `sla.ticket.p{n}_hours`.
  - Legal edges:
    - NEW → ACKNOWLEDGED / INPROGRESS
    - ACKNOWLEDGED / INPROGRESS / REOPENED → INPROGRESS / WAITING_USER / RESOLVED
    - WAITING_USER → INPROGRESS / RESOLVED
    - RESOLVED → CLOSED / REOPENED
    - CLOSED → REOPENED
    - Force-close from any open state by the owner, with a reason.
  - Who may move it:
    - Owner, assignees or the desk (`UTL_TCKT_DESK`) move work states.
    - RESOLVED: owner or assignee only.
    - CLOSED from RESOLVED: the requester (or owner). REOPENED: the requester or owner.
  - SLA clock:
    - `due_at` is set at open and on a priority change.
    - WAITING_USER pauses the clock, and the paused time is added back when it leaves.
    - An hourly job flags breaches once (Alert to owner, assignees and desk, plus a Chat event).
    - A daily job auto-closes RESOLVED tickets after `ticket.autoclose_days` when `ticket.autoclose_enabled` is on.
  - Inboxes: REQUESTED, ASSIGNED (assignee or owner), FOLLOWED, SNOOPED, plus QUEUE (NEW tickets with no assignee, desk only).
  - Report: open by category, breached, mean time to resolve.
- **Team picker:** `OrgService::teamOptions()` lists active users who have an employee record; the picker never lists every user.
- **Screens:** `utils/tasks*` and `utils/tickets*` (inboxes, create, edit, view with follow-up / transition, report), menu under Utilities, and components `x-task.inbox`, `x-task.composer`, `x-ticket.inbox`, `x-ticket.sla-badge`.
- **Approved-by:** user (28-09 plan) · **Risk:** MED (new tables and screens; no existing flow changes) · **Reversal:** migration `down()` plus revert.

### DEC-063 | 28-09-2026 | A (platform) | Approval engine, topic tree, power sheet and reports (FRS §7–8)
- **Tables** (the reserved `xlr8_approval_*` prefix):
  - `topic`: tree; stable dotted `code`; `item_key` on items; `mode` inherited when null.
  - `rule`: topic plus scope tuple company / zone / state / branch / desk / segment / model / variant / permit / channel, where NULL means ANY; `valid_from` / `valid_to`.
  - `rule_level`: level no, designation, value type AMOUNT / PERCENTAGE / FLAG, std / min / max as DECIMAL(15,2); `user_ids` for STATIC.
  - `request`: a projection with a frozen snapshot of topic plus matched rule plus levels; `ask_revision`; the effective grant; branch and FY for reports.
  - `counter`: bound to an ask revision.
  - `event`: append-only.
- **Writers:**
  - Topics and rules are master data written through `EntityService` subclasses (DEC-050): `Platform\Approval\Entities\{ApprovalTopicService, ApprovalRuleService}`. Rule levels are written with their rule.
  - Requests, counters and events are written only by `Platform\Approval\ApprovalService`.
- **Behaviour (FRS law):**
  - `Topics::resolve` gives the chain Main / Sub / Item and the effective mode (inherited; VARIABLE with no resolver falls back to OPEN_TO_ALL and logs a warning).
  - `Rules::match`: deepest topic node that has a valid rule, then scope specificity variant > model > segment > permit > desk > branch > zone > company (channel / state count after company), then latest id. A rule matches only when every non-ANY dimension equals the request scope.
  - `open` snapshots the rule. Visibility is live: holders of a snapshot designation whose org (branch, via the employee) fits the rule scope, plus the requester; STATIC levels list users. `counter` requires visibility plus a level on the snapshot; LINEAR accepts only `current_level`.
  - Highest level wins on the current ask revision; the latest counter wins at the same level. `reviseAsk` (requester) makes older counters stale. `close` ACCEPTED / WITHDRAWN by the requester or a user holding `UTL_APPR_ADMIN`. No approver "reject".
  - Auto-accept within the requester's own power is behind the `approval.auto_accept_own_power` setting (APR-08) and is still snapshotted.
  - `authorize` and `preview` answer the simulation questions.
  - Scope dimensions company / zone / state / desk / channel are stored and matched as given; they only take effect once callers pass them (org entities for zone / desk do not exist yet — blueprint §7.4, still open).
- **Power-sheet import** (xlsx / csv): one row per level. Topic code, item key, scope columns, level, designation, value type, std / min / max, valid from / to. Synonyms are applied to scope values. Dry-run reports without writing; apply purges and replaces the rules of each topic in the sheet. Unknown designations or topics go to a downloadable error sheet.
- **Report:** from the request projection and events (never re-matching). Filters FY / branch / topic / level / actor / source. Figures: opened / accepted / withdrawn / open, granted vs asked, counters per level, auto-approved share, time to close. Export as xlsx.
- **Legacy:** `App\Services\ApprovalService` (graph approve / reject; no callers, no routes) and its binding are removed. `App\Models\Core\{ApprovalHierarchy, GraphNode, GraphEdge}` are also dead (their tables do not exist) but are left for owner sign-off.
- **Permissions:** `UTL_APPR_VIEW` / `REQUEST` go to all designations; `UTL_APPR_ADMIN` (topics, rules, import, simulation, force close) and `UTL_APPR_REPORT` are admin-only.
- **Approved-by:** user (28-09 plan) · **Risk:** MED (new tables; no Sales flow wired yet — Quote / Booking call `Approval::open` in a later change) · **Reversal:** migration `down()` plus revert.

### DEC-064 | 28-09-2026 | A (platform) | Comms plane: Templates, outbox, Email / SMS / WhatsApp / Telephony (FRS §12–17)
- **Tables** (`xlr8_comm_*`):
  - `template` (family per code × channel × locale) and `template_version` (DRAFT → IN_REVIEW → APPROVED → ACTIVE → RETIRED, plus PENDING_PROVIDER / REJECTED).
  - `outbox`, with the payload snapshot stored encrypted for exact resend; the visible body has PII masked.
  - `sandbox`, `consent` (person_code × channel), `suppression`, `otp` (hashed), `wa_thread` / `wa_message`, `call`, `webhook_event` (idempotency).
- **Services** (`App\Services\Platform\{Templates, Comms}`), each with a driver interface and a registry keyed by Settings:
  - `TemplateService`: `get` / `render` (safe `{{var}}` renderer; HTML vars escaped; missing required or unknown placeholder is an error, highlighted in preview; locale fallback, strict by setting), `saveDraft` (never touches ACTIVE), `submit` (Approval topic `COMMS.TEMPLATE`; ACCEPTED → APPROVED through an `ApprovalChanged` listener), `activate` (requires APPROVED; previous ACTIVE → RETIRED), `export` / `import` JSON (drafts only).
    - When no approval rule covers `COMMS.TEMPLATE`, a `UTL_TPL_ACTIVATE` holder may approve directly (audited as an event). Otherwise no template could go live until the power sheet has that topic.
  - `EmailService`: the locked option shape. Identity aliases come from `mail.identities`, with an allow-list. Also suppression, Docs attachments and `.ics`, `status`, `resend`. Drivers `laravel` (Laravel mailer — the only place `Mail::` is used) and `log` (sandbox).
    - `mail.redirect_to`: when set, every mail goes to that address. This is a dev safety valve; the local `.env` mailer is a real SMTP host.
  - `SmsService`:
    - E.164 normalisation; consent for PROMOTIONAL; the promotional window; `raw` only with `UTL_COMM_SMS_RAW`.
    - DLT mapping required for transactional sends when `sms.dlt_required`.
    - `otp` / `verify`: hashed code, rate limit; the code never reaches the outbox.
    - Failover driver.
  - `WhatsAppService`:
    - Template vs session send; SESSION_CLOSED outside the 24h window; a template send refuses extra text; polls degrade to a numbered list.
    - Inbound media goes to Docs `wa-inbound`; STOP flips consent.
    - Also `thread`, `history`, `markRead`, and link / assign / label.
  - `TelephonyService`: `dial` (the call row exists before the vendor returns; numbers masked towards the browser), webhook call events, recording to Docs `call-recordings` plus a Chat `CALL_RECORDED` event, `dispose` (KeyValue `CALL_DISPOSITION`), and a missing-recording sweep.
  - `CommsRouter::fromNotify` maps Notify EMAIL / SMS / WHATSAPP channel options onto the services. Plain `true` uses the `notify.generic` templates.
- **Sending:** outbox first, then the queued `SendOutboxMessage` job (timeout, tries, `failed()`). Idempotency is on every send (explicit key, or a hash of channel + recipient + template + vars + ref).
- **Drivers today:**
  - SMS / WhatsApp / telephony: sandbox only (writes `comm_sandbox` and the log; simulates delivery).
  - Mail: `laravel` or `log`.
  - Vendor drivers are added later as one class plus a Settings value.
- **Webhooks:** `POST /api/webhooks/comms/{channel}`, HMAC-SHA256 of the body with `comms.webhook_secret` (header `X-Signature`), idempotent on the provider event id. An unsigned call is accepted only while the secret is blank in local / testing environments.
- **Seeds:**
  - System templates `notify.generic` (EMAIL / SMS / WHATSAPP), `otp.sms` and `sms.stop.ack`, seeded ACTIVE as system-owned copy.
  - KeyValue `CALL_DISPOSITION` (CONNECTED, NO_ANSWER, BUSY, WRONG_NUMBER, VOICEMAIL, CALLBACK_REQUESTED).
- **Screens:** templates admin (list, draft editor, preview, version diff, submit / approve / activate), outbox + sandbox viewer with resend, WhatsApp inbox, call log. Components `x-template.preview`, `x-telephony.click-to-call`, `x-whatsapp.inbox|thread|composer`, `x-email.send-panel`.
- **Approved-by:** user (28-09 plan: sandbox SMS / WhatsApp / telephony, real mail) · **Risk:** MED (new tables; no vendor credentials; the mail default stays the Laravel mailer as decided) · **Reversal:** migration `down()` plus revert.

### DEC-065 | 28-09-2026 | A (platform) | Platform integration: acceptance pack, keyword seeds, rules
- **Tests:** `tests/Feature/Platform/*`. They use `DatabaseTransactions` on `xlrm_testing` and real users picked from the copy, per the suite convention.
  - The FRS §11 acceptance pack is one test per item: `PlatformAcceptanceTest`.
  - Service tests: approval decisions and precedence, the task rights matrix, the ticket SLA clock, template rendering, webhook signature and idempotency.
  - Item 10 (flip the SMS driver to MSG91) is skipped with its reason until a real SMS vendor driver and credentials exist.
- **Seed:** KeyValue `ENTITY_ACTIONS` (the Chat event vocabulary), through the keyword services.
- **Settings:** a key that is not in the seed pack now takes its type from its first value (bool / int / decimal / json / string). A flag such as `quote.csd_enabled` can be created from the admin screen or by code without a deploy (acceptance item 5).
- **Rules:** a new `.ai/rules/modules/platform.md` (laws, traps, permissions, tests), loaded by path for all platform code.
- **Approved-by:** user (28-09 plan) · **Risk:** LOW · **Reversal:** revert.

### DEC-066 | 28-09-2026 | A (UI) | Project-wide UI standards and the shared UI layer
- **Why:** the user asked for four standards everywhere: the site date format (display, lists, pickers), Select2 for multi-selects, drop-zone uploads, and modern / minimal / responsive screens (360 / 768 / desktop).
  - A scan showed they were not followed: 65 views hard-coded date formats, 33 used native date pickers, 17 flatpickr pickers ignored the site format, 9 used list boxes, 31 had bare file inputs, 88 used fixed pixel widths, and 25 had tables without a responsive wrapper.
  - The existing `.ai/rules/ui.md` rules (dates, colours) were also ignored, including by the platform sprints.
  - Rules recorded on request (`.ai/rules/ui.md`, 4 new sections).
- **Decision (user: "continue as per your best recommended plan"):**
  - One shared layer, `public/js/xl-ui.js` + `public/css/xl-ui.css`, loaded on every admin page (`header_metas`). It progressively enhances every screen: native date inputs → flatpickr in the site format (submitting ISO), `select[multiple]` → Select2, file inputs → drop-zone, bare tables → `.table-responsive`, plus a responsive safety net. Opt out with `data-xl="off"`.
  - An inline hook wraps `agGrid.createGrid` so AG-Grid date columns use the site format.
  - Backpack's date / datetime column formats are set from the setting at boot.
  - Why this approach: Sales (booking team) and other legacy screens comply without editing them.
- **Libraries:** the flatpickr and Select2 already used by 25 and 12 screens become the single approved versions (flatpickr 4.6.13, Select2 4.1.0-rc.0), pinned and cached by Basset from `config/backpack`. No new package.
  - The drop-zone is in-house, with no Dropzone.js / FilePond dependency.
  - Select2 is used as the library, not Backpack PRO fields.
- **Components for new code:** `x-ui.date`, `x-ui.select`, `x-ui.upload` (form or AJAX mode with per-file progress). New helpers `site_datetime()` / `@sitedatetime` and `DateFormatService::{phpDateTimeFormat, formatDateTime, isoFormat}`. New setting `display.time_format` (default `H:i`).
- **Converted now:**
  - All platform-utility screens and components.
  - The notification centre: a single bell with a combined badge and N / A / M tabs, replacing three hard-coded dropdowns.
  - Server-side display dates in 28 non-Sales views (to `site_date` / `site_datetime`).
- **Not converted in source (enhanced at runtime only):**
  - Sales / booking / PDF views (booking team) — about 250 violations.
  - Hex colours and inline `<style>` in legacy views (227 files).
  - These are the follow-up work.
- **Approved-by:** user (28-09) · **Risk:** MED (global JS on every page; guarded, idempotent, opt-out) · **Reversal:** remove the `header_metas` include / config entries.

### DEC-067 | 28-09-2026 | A (UI) | Tabler-parity shell, theme settings, AG-Grid theming and the dev UI kit
- **Why:** the user asked for the look and features of the Tabler admin preview: its menu system, a user block with an avatar and dropdown, a mode switcher and a colour switcher. They also want Tabler-level form elements, a CRM dashboard, a chat interface, and static sample pages on a dev-only route.
  - AG-Grid and whole pages must follow the mode and colour switches.
  - Only free / OSS or in-house code may be used.
- **Diagnosis (why dark mode broke):**
  - Our `layouts/horizontal` override hard-codes `#FFFFFF !important` on the header and `#F4F2EE` on the page.
  - The user dropdown uses inline hex colours.
  - AG-Grid is loaded unversioned (it resolves to v36). Since v33 the grid themes itself through its JS Theming API with a light Quartz theme, so the `--ag-*` → `--tblr-*` mapping in `ag-grid-tabler-theme.css` never reaches it.
  - Legacy views carry `bg-white`, `text-black` and inline hex colours.
- **Decision:**
  - **Theme settings panel** (off-canvas, like Tabler's), opened from the top bar and the user menu. Settings:
    - colour mode: light / dark / system (Backpack's `colorMode`);
    - primary colour: 12 Tabler colours;
    - base palette: slate / gray / zinc / neutral / stone;
    - font: sans / serif / mono / comic;
    - corner radius: 0 – 2;
    - menu layout: horizontal / vertical / vertical with a dark sidebar;
    - a reset button.
  - How it works:
    - Colours, fonts and radius come from Tabler 1.4's own `tabler-themes.min.css`: the same MIT package, pinned `@tabler/core@1.4.0` and cached by Basset. They are applied as `data-bs-theme-*` attributes on `<html>` by a render-blocking script, so nothing flashes.
    - The choice is stored per browser (`localStorage xl.theme`).
    - The layout is a per-browser cookie `xl_layout`, whitelisted, unencrypted, and read by the new `ApplyUiPreferences` admin middleware. The default stays `horizontal`.
  - **Shell:**
    - Hard-coded colours are removed from the horizontal layout.
    - A Tabler-style user block: avatar image or initials, name and designation, and a dropdown with profile, inbox, appearance and log-out.
    - The vertical layouts get a top header (search slot, mode, appearance, bell, user block).
  - **AG-Grid:** the existing global `createGrid` hook gives every grid that sets no `theme` of its own a Quartz Theming-API theme whose parameters are Tabler CSS variables. Every grid (86 views) therefore follows mode, primary colour, font and radius live, with no view edits.
  - **Dark-mode safety net** (`public/css/xl-theme.css`): under `[data-bs-theme=dark]` it remaps `bg-white`, `bg-light`, `text-black`, `text-dark`, `table-light` / `thead-light` and the common inline white or black colours. This covers the Sales views without editing them.
  - **Dev UI kit** `/admin/dev/ui/{page}`:
    - Pages: overview, forms, lists, elements, CRM dashboard, chat, pages.
    - Static reference screens built only from Tabler, the shared layer and the components.
    - Available only when `platform.dev_ui_kit` is on. It follows `XL_DEV_UI_KIT` and defaults to on only for `APP_ENV=local`; when off the route returns 404. No new permission.
  - **Libraries:**
    - ApexCharts 3.54.1 (MIT), pinned and Basset-cached, only on the UI-kit dashboard. It is the chart library Tabler itself uses.
    - No Tabler Icons: Line Awesome stays the single icon set.
    - No composer changes.
- **Approved-by:** user (request of 28-09: "free/oss or our own custom functionality only") · **Risk:** MED — global CSS/JS on every page and a new admin middleware; all opt-in or guarded · **Reversal:** remove the layout overrides, the `xl-theme` includes and the middleware entry.

### DEC-068 | 28-09-2026 | A (process) | Guides, stage merge, Sales/booking parity, branches in sync
- **Why:** the Sales team's code is on `stage` (`26ab25b`, 27-09). The user wants, in this order:
  1. developer guides for every model and service;
  2. `dev/admin` merged into `stage`;
  3. the merged code pulled into `dev/admin` and local;
  4. our backend and visual standards applied to Sales / booking;
  5. a merge back, so that `stage`, `origin/dev/admin` and local hold the same code and schema before the dev team starts.
- **Decision (user):**
  - Backend: **parity refactor**, same behaviour.
    - Their new `CommonHelper` call → `OrgService` (fixed inside the merge commit, so `stage` never breaks).
    - Legacy history / docs / notification adapters → Chat / Docs / Notify.
    - Raw KeyValue reads → `KeywordValueService`.
    - Display dates → `site_date()`.
    - No new business flows (approvals, tasks and messaging are not wired into sales yet).
  - Visual: **standards + tokens**.
    - AG-Grid pinned to 36.2.0 and themed by the global hook.
    - Per-view flatpickr / Select2 removed (the global copies stay).
    - Site date format, Select2 multi-selects, drop-zone uploads.
    - Hex / `bg-white` → Tabler tokens.
    - View `<style>` blocks kept but token-only.
    - PDF / print views exempt.
  - Guides: `docs/domains/` (per domain, models + services, every public method), coverage checked by a script. Two rules recorded on request (`.ai/rules/app.md`, `.ai/rules/components.md`): guides are updated in the same change as the code.
- **Approved-by:** user (plan approved 28-09, including pushes to `origin/dev/admin` and `origin/stage`, merges only) · **Risk:** MED (a large Sales diff; the booking team is asked not to edit Sales views until the final merge) · **Reversal:** revert the merge commits.

### DEC-069 | 28-09-2026 | A (Sales / platform) | Booking proofs move into Docs; the rest of the "later" list
- **Why:** the user asked to fix every item left for later after DEC-068:
  1. booking proof files outside Docs;
  2. about 37 non-Sales screens loading AG-Grid unversioned;
  3. the booking list toolbar clipping at phone width;
  4. no fresh-clone boot test.
- **Decision — proofs:**
  - Receipt proofs, finance instrument proofs, insurance policy copies, RTO TRC / tax receipts, refund proofs, delivery photos and the OTF chassis image become Docs documents attached to their own record (`Bookingamount`, `XFinance`, `XlInsurance`, `XlRto`, `Xl_Refunds`, `XlDelivery`, `Booking`).
  - Collection names are kept.
  - **Existing files:** a reversible local migration re-points each media row to a new `Document`. Spatie stores files under the media id, so nothing moves on disk. The original owner is kept in `tags.migrated_from`, and `down()` restores it.
  - The two policy copies stored under the misspelled model type `…\Insurance\Xlinsurer` (BUG-196) are attached to the insurance rows their `model_id` names.
  - **Reads and writes:** go through new generic `HasDocuments` helpers — `documentFor()`, `documentUrl()`, `hasDocumentIn()`, `replaceDocument()`, `removeDocuments()`.
  - **Links:** use the access-checked `utils.docs.download` route, which gains an `?inline=1` mode for previews.
  - **Access:** the satellite models get `chatCanView()`. Booking viewers (`SLS_BKNG_VIEW`) see booking proofs, and receipts are also visible to `ACC_RCPT_VIEW`. This replaces public `/storage` URLs, so the proofs are no longer open to anyone holding a link.
  - Out of scope: the DSA master's account proof (master data, not a booking proof).
- **Decision — the rest:**
  - AG-Grid is pinned to 36.2.0 and the legacy grid CSS removed in every remaining view.
  - The list toolbars wrap instead of using fixed widths.
  - A fresh clone is installed and booted in a temporary directory against the local database.
- **Approved-by:** user (28-09: "fix all issues marked for later"). A backup of `media` and the docs tables is taken before the migration. · **Risk:** MED (data re-pointing on local; reversible) · **Reversal:** `php artisan migrate:rollback --step=1`, then revert the commit.

### DEC-070 | 28-09-2026 | A (all modules) | Bug-fix sprint, wave 1: fixes that need no owner decision
- **Why:** the user asked to clear all bugs one by one after triaging each against the code (plan approved 28-09).
  The triage found mobile OTP login broken (`users` has no `mobile` column) and the login OTP written to the log in
  plain text; both are in scope here only as far as no auth behaviour changes.
- **Decision (wave 1, no business / auth choice):**
  - BUG-189: never log an OTP; log user id and masked numbers only. `AuthService` gets its missing imports.
  - BUG-184 audit names via `display_name`; BUG-185 unused `onlyRestored()` scope removed; BUG-186 `variantName()` returns the name.
  - BUG-192 / 193 relation keys (`id`, `bid`); BUG-102 dead exchange payload keys dropped (nothing saved changes).
  - BUG-097: OTF save rejects a VOTF number another booking already holds (row lock in a transaction).
  - BUG-195: PAN masked in new KYC history entries, like the Aadhaar.
  - BUG-168: Lead / Lead Source search and details routes carry `'operation' => 'list'`.
  - BUG-029 / 055 pattern: imports record the real actor, not user 1. BUG-179: importer debug output removed.
  - BUG-008 / 020 / 021: unused Create / Update traits removed (screens stay list-only; no files deleted).
  - Tracker, state and smoke-test citations corrected; fixed bugs closed with evidence.
- **Not in wave 1 (owner decisions D1–D29 in the plan):** mobile login repair, OTP randomness, v1 entity access,
  deletions of tracked files, menu / permission changes, pricing, data mappings and migrations.
- **Approved-by:** user (plan approval 28-09). · **Risk:** LOW · **Reversal:** revert the wave-1 commits.

### DEC-071 | 28-09-2026 | A (IAM, all modules) | Automatic, hierarchical data scoping with a code-level opt-out
- **Why:** the user wants every screen, function and API to show a user only the data inside their assigned scope,
  applied automatically, with a manual switch-off in code. The dormant scoping (BUG-083 / BUG-136, D27) filtered by id,
  failed closed, had no hierarchy and was switched on nowhere. Plan: `docs/plans/2026-09-28-data-scoping-DEC-071.md`.
- **Rules (user, 28-09):**
  - No scope rows for a dimension = full access on it.
  - A parent covers all its children down to the last level unless the user is restricted at a child level; a child
    restriction applies within the nearest assigned ancestor (PV + Thar → only Thar variants; PV + CV + Thar → Thar
    under PV and every CV model). Trees: Branch → Location; Department → Division; Segment → Sub-segment → Model →
    Variant. Vertical is standalone.
  - Applies to booking, quotation, enquiry and every other business entity.
- **Decisions (user, 28-09):**
  1. Rows whose dimension is empty stay visible (`scope.unassigned_rows = visible`) until backfilled; then `hidden`.
  2. Pickers / master lists are not scoped; only business data rows.
  3. Department / division / vertical filter only where a table carries the column (declared per entity).
  4. Bookings get `branch_code`, `location_code`, `sub_segment_code`, `model_code`, `variant_code`, set on save and
     backfilled (covers D22 / BUG-161 / BUG-092).
- **Mechanism:** one code-based resolver (`ScopeResolver` → `ScopeSet`), a global scope via `HasDataScope` driven by
  `config/data_scope.php`, opt-out per query (`withoutDataScope()`), per block (`DataScope::off()`), per route
  (`data-scope:off`); master switch `scope.enabled`. Jobs / console (no user) are never scoped.
- **Approved-by:** user (28-09: "start", plan saved to docs/plans). · **Risk:** HIGH (changes what users see) — mitigated
  by the master switch and visible-unassigned default. · **Reversal:** setting `scope.enabled = false`, then revert commits.

### DEC-072 | 28-09-2026 | A (IAM / UI) | My Account rebuild and a dynamic, permission- and scope-aware dashboard
- **Why:** the user asked (28-09) for a My Account page per user type (employee: personal info, designation, primary +
  add-on scopes read-only, employment history, contact, reporting manager; change password after checking the current
  one; edit display name and profile image; designation under the name) and a dashboard built per role / permission with
  data inside the user's scope instead of the static page. Plan: `docs/plans/2026-09-28-my-account-and-dashboard-DEC-072.md`.
- **Decisions (user, 28-09):** open enquiries = stage in Enquiry / Test Drive / Quotation / Booking / Postponed; aligned
  deliveries = invoiced bookings (status 2) with `del_date` in the period (delivered vs pending); password min 8 with
  letters and numbers, different from the current one, other sessions signed out; top-bar avatar = the person's profile
  photo (initials otherwise) instead of Gravatar (D17).
- **Mechanism:** own `MyAccountController` on Backpack's route names; display name / photo through
  `PersonRecordService`; username read-only. Dashboard widgets declared in `config/dashboard.php`, each gated by a
  permission and computed by a provider through scoped models / `DataScope::apply()`, cached per user + scope + period.
  Supporting indexes on enquiry / follow-up / test-drive / satellite columns (local migration, reversible).
- **Approved-by:** user (28-09). · **Risk:** MED (auth form, first screen every user sees) · **Reversal:** revert commits;
  `setup_my_account_routes` back to true; `migrate:rollback` for the index migration.

### DEC-073 | 28-09-2026 | A (Vehicle pricing) | Pricing import process redesign — 11 steps, gate to on-demand getPricing
- **Why:** the user asked for the full pricing process (0 gate … 11 getPricing). The study of the reference workbooks
  (`docs/reference/XLRM-Pricing-Data`), spec v3.1.1 and the code found a session that never completes, a discard that
  rolls back live data, colliding taxi snapshots, wrong prices (dealer charges 0 — BUG-178, TCS rate unread, accessories
  outside on-road), lossy round-trips, a double completeness source, no snapshot reader, an unrouted API and a mock
  quotation. Plan: `docs/plans/2026-09-28-pricing-redesign-DEC-073.md`.
- **User decisions (28-09):**
  - Completeness: Passenger **and** MISC need none of CC / Motor / GVW (Private + ICE → CC, Private + EV → Motor,
    Goods → GVW). **Amends locked spec v3.1.1 §3.3** (user override).
  - Taxi (taxi_price YES) publishes PRIVATE and PASSENGER snapshots; Passenger 4W uses RTO "Taxi" rules and insurance
    "Passenger" permit via one permit map (insurance "Rules" sheet); MISC → RTO "Ambulance", insurance "Misc".
  - CSD never creates vehicles (prices for existing vehicles, CSD channel); LMM TZU stubs get colour NA, OEM Model =
    Model Name, OEM Variant = Material Description.
  - Bases = ex-showroom: RTO ESR = ex-showroom rounded up to ₹1,000 (BH: assessable + dealer margin); IDV =
    ex-showroom × slot %; snapshot TCS when ex-showroom ≥ configured limit at the configured rate.
  - The quotation screen is rewired to getPricing as the last phase (hold enforcement, server-side gate re-validation).
- **Technical calls:** `PRC_WKFL_MANAGE` is the manage_pricing gate; add-on import blank = no rule, 0 = explicit zero;
  prices keep DEC-058 (material change) + history rows; add-on groups expire-and-insert per WEF; conflicting duplicate
  codes rejected; OV block only on PV/CV/BEV (others OV = NV); insurance stores per company × plan base + each add-on
  premium (default Base + NilDep + Consumables, frozen); snapshot `withheld` = 0 (quotation fills it); discard exact
  via a session change log and only before publish; snapshot key gains permit.
- **Approved-by:** user (plan approved 28-09). · **Risk:** HIGH (prices shown to customers) — every phase tested on
  fixtures + real reference workbooks; nothing merged to stage without approval. · **Reversal:** revert commits;
  migrations roll back.

### DEC-074 | 28-09-2026 | A (Vehicle pricing) | BUG-199: purge and re-import the vehicle masters before the first DEC-073 run
- **Facts:** variant rows loaded before DEC-051 keep the OEM code without the colour suffix (`AW62BMZR7TF08A00` + `BA`).
  Price lists carry the full code (`AW62BMZR7TF08A00BA`), so Detect creates duplicate INCOMPLETE vehicles.
- **Decision (user, option 2):** every environment empties its vehicle masters (segment, sub-segment, model, variant) and
  rebuilds them with the new pricing process, as local `xlrm` did under DEC-051. There is no code remap and no dual-format
  matching.
- **Guard:** the Start screen counts old-format variant rows (`color_code` set, `code` not ending in it) and warns before
  a process is started (`PriceListDetectService::legacyCodeCount()`).
- **Per environment (at deploy):**
  1. Take a backup.
  2. Purge the masters. This is a non-local DB operation and needs the user's go-ahead for each environment.
  3. Run the pricing process.
  `xlrm_testing` keeps its legacy copy until the local fresh import is complete (DEC-051).
- **Approved-by:** user (28-09). · **Risk:** HIGH (destructive per environment, done manually with a backup) · **Reversal:** restore the backup.

### DEC-075 | 28-09-2026 | A (Vehicle pricing) | Vehicle Info (DEC-073 step 3): value formats and import rules
- **Facts:** the reference `Vehicle_Info_6_COMPLETED.xlsx` gives GST% as a fraction (0.4, 0.28). The field format and the
  engine treat it as a percent. Transmission mixes spellings (At / Mt / Automatic / MANUAL).
- **Decision (technical, no business change):**
  1. `VariantService` stores GST as a percent: a value between 0 and 1 is multiplied by 100 on every write path.
  2. `VariantService` maps transmission "AT" → Automatic and "MT" → Manual. Other values keep their title-cased spelling.
  3. The Vehicle Info import never creates vehicles: unknown codes are rejected (vehicles come from price lists only, step 2).
  4. Blank cells keep the stored value.
  5. OEM Model / OEM Variant cells are not imported (they are OEM facts from the price lists).
  6. Status ACTIVE on an incomplete vehicle leaves it INCOMPLETE and lists the missing fields.
- **Approved-by:** auto (implements the approved DEC-073 step 3) · **Risk:** LOW · **Reversal:** revert the Phase 3 commit.

### DEC-076 | 28-09-2026 | A (Vehicle pricing) | Price import (DEC-073 step 4): which columns are the price, and the write rules
- **User decisions (28-09):**
  - **Ex-showroom per list:**
    - PV / CV / BEV: "Ex-Showroom Price ORG".
    - LMM: **"Ex Showroom Price(Org)"**. Not "Ex Showroom Price", which is lower by ₹3,000–13,375 on 48 of 79 rows.
    - LMM TZU: **"Final Transaction Price"** (post-subsidy price minus the scheme). TZU has no separate scheme discount:
      its Scheme and scheme columns are not imported.
    - CSD: **"CSD Final Price"** (before the 50% GST concession).
- **Found:** BUG-200. Registry labels were only lower-cased while sheet headers also turned `-` `.` `_` into spaces, so
  every label with a hyphen never matched. That covers "Ex-Showroom Price ORG" and all CV- / OV- scheme columns. The old
  importer therefore never read ex-showroom: it used MM Invoice (PV Scorpio-N: ₹21,80,958 instead of ₹22,76,500) and
  imported no schemes.
- **Technical calls:**
  1. Both sides are normalised the same way. When several columns match one field, the registry's primary label beats
     its aliases, so "OEM Scheme with GST" is used over "@ BNDP".
  2. The removed hard alias "ex showroom pre subsidy → ex_showroom" had been winning on TZU.
  3. **NV / OV:** only PV / CV / BEV carry an OV block. CV / BEV label it (CV- / OV-). On PV the OV block repeats the NV
     labels, so the second occurrence is OV. LMM / TZU / CSD store OV = 0 (the snapshot uses NV).
  4. **LMM:**
     - assessable with freight = "Assesable Value" + "Freight New".
     - OEM scheme with GST = "VIN Scheme" ("OEM Scheme @ BNDP" × 1.05).
     - dealer margin = "New Dealer Margin" + "Extra Handling".
  5. **Dealer margin** stored = Dealer Margin + Dealer Handling (CV carries its margin in Handling). Only the BH RTO base
     uses it.
  6. **Accessory / Shield discount eligibility** ("Acc Dsc Elg", "Sheld Disc Elg", NV and OV) is stored as a ratio in
     new columns, for the quotation discount gate.
  7. **WEF (DEC-058):**
     - Same WEF → update the live row.
     - New WEF with a material change → expire the previous row, then insert.
     - No material change → keep the previous row. Before this, it also inserted a second active row.
  8. **History:** every imported row is written to `xlr8_vehicle_pricing_history` (action insert / update / unchanged,
     parsed payload).
  9. **Rows rejected or skipped:** conflicting duplicate codes in one sheet are rejected (identical duplicates count
     once); incomplete vehicles are skipped; CSD codes not in the master are skipped.
- **Approved-by:** user (column choices), auto (technical calls) · **Risk:** HIGH (customer prices) · **Reversal:** revert;
  the migration rolls back.

### DEC-077 | 28-09-2026 | A (Vehicle pricing) | Add-ons & discounts (DEC-073 step 5): workbook shape and import rules
- **Export `Addon-N-Discounts.xlsx`:** the reference sheets and headers, with only the sheets ticked (all ticked by
  default). Every applicable group appears. Amounts come from the live rows; a cell is blank where nothing is stored.
  - **Dealer Charges:** one row per segment, split by permit where the segment sells more than one (for example PV
    Private / Passenger), plus any stored model-specific rows.
  - **RSA:** one row per model.
  - **Shield:** one row per PV / BEV / CV model (pack / transmission / fuel ANY), plus the stored specific rows.
  - **Exchange:** model × scheme (stored ∪ Exchange, Welcome, Scrappage).
  - **Corporate:** model × category (stored ∪ the reference's 8 categories).
- **Import** (queued, one transaction per sheet, recorded for Discard):
  - Each ticked sheet present in the file expires its group's live rows at the WEF, then inserts the sheet's rows.
    Groups not ticked are untouched.
  - Blank = no rule (not written); 0 = an explicit zero rule.
  - Model names resolve to model codes (`VehicleService::findModel`: canonical code, name or OEM name). "Any" = all.
    An unknown model rejects that row, which is reported.
  - Segment and fuel values go through synonyms (PERSONAL / PEROSNAL → PV, COMMERCIAL → CV).
- **Dealer charges:** an all-zero row is allowed at a specific segment (an explicit "no charges" rule), but still refused
  at ANY (pitfall: a zero row at ANY overrides everything).
- **Registry aliases** for the reference labels: Exchange "OEM Model / OEM Variant / Scheme / Bonus OEM / Bonus DLR /
  Bonus TOTAL"; Corporate "OEM Model / OEM Variant / OEM / DLR / TOTAL".
- **History:** add-on and discount history rows are written (action insert). BUG-201 extended: the AddonHistory and
  DiscountHistory models did not match their tables.
- **BUG-178** (engine: dealer charges and scope) is fixed with the builder in Phase 8, where the engine is rewritten.
- **Approved-by:** auto (implements the approved DEC-073 step 5) · **Risk:** MED · **Reversal:** revert; the migration rolls back.

### DEC-078 | 28-09-2026 | A (Vehicle pricing) | Insurance & RTO (DEC-073 step 6): standalone workbooks, lossless storage
- **Facts:**
  - The reference `Insurance.xlsx` and `RTO-Rules.xlsx` write rules as text: "(10% * 1.25 * 2) / 15",
    "12.5% of Tax", "5% x OD", "1162 x (Seat -1)", "150 Per Seat".
  - Ranges are written many ways: "0-3000", ">1500", "< 30KW", "1 to 7", "Above 2000000".
  - Insurance add-on rates differ per CC / power band. The old tables could not hold them: no seater or assessable
    band, no text heads, add-on rates only per company.
  - No rules are stored in any database yet.
- **Decision (technical):**
  1. **Shared parsers:** `Rules\RuleRange` (parse / contains) and `Rules\RuleFormula` (safe arithmetic with %, of, per,
     variables OD LPG SEAT IDV INVOICE TAX ESR TP). The engine (Phase 8) uses the same two classes. The import rejects
     a row whose range or formula does not parse, and warns on an inverted range (the reference BH band
     "1000000 - 200000" can never match; it is reported).
  2. **Schema** (migration, reversible):
     - RTO rules gain `seater` and `assessable_range`.
     - Insurance base rules gain `heads` (JSON: every OD / TP head exactly as written) and `tp_pa_owner`.
     - Insurance add-on rates gain `base_rule_id` (rates belong to one company / permit / band / plan row) and
       `rate_text`.
     - A `PermitMap` model and entity service for the existing permit map table.
  3. **Workbooks:**
     - **RTO:** one "RTO" sheet in the reference columns.
     - **Insurance:** "Insurance Co." (model + permit → up to 3 companies; Co. 1 is the default), "Insu Premium"
       (scope, plan, IDV 1–3 basis, every OD / TP head, 17 add-ons) and "Permit Map" (vehicle permit + wheels → RTO
       permit, insurance permit).
     - The reference "Rules" sheet (RTO-label → insurance permit) is not imported. Its content is already in the
       permit map (DEC-073), which the new "Permit Map" sheet edits.
     - Reference layouts import directly: typo labels are aliased, and the calculator columns A–D / AR+ are ignored.
       Where "IDV n" appears twice, the rightmost column is used.
  4. **Import:** queued, recorded for Discard. The RTO workbook replaces all RTO rules. The Insurance workbook replaces
     the companies, premium rules (with their IDV slots and add-on rates) and the permit map, each in one transaction.
     Blank = not set; numbers and formulas are stored exactly as written, so the round trip is lossless.
  5. **The step:**
     - No rules of a kind stored → an import is required.
     - Otherwise Keep, or download the current rules → edit → re-import.
     - Continue needs both kinds present.
- **Approved-by:** auto (implements the approved DEC-073 step 6) · **Risk:** MED · **Reversal:** revert; the migration rolls back.
