# AI changelog — 26-09-2026 (branch `feature/integrations`)

Decisions referenced are in `docs/decisions/decision-log.md`.

## Commits on `stage` / branch creation
- `280d052` (stage): 24/25-09 bug-fix sweep, data scoping option A, docs reorganisation (DEC-008). Branch `feature/integrations` created from it.

## Security
- `gscreds.json` untracked (`git rm --cached`; file kept locally, already gitignored). The key is still in history since `e6147f7` → **rotate in Google Cloud**; history purge pending approval (DEC-009).

## Test isolation
- New `php artisan testing:refresh-db` (`app/Console/Commands/RefreshTestingDatabase.php`): mysqldump `xlrm` → `xlrm_testing` (refuses production, same DB, non-`_testing` targets; small INSERT batches avoid local mysqld OOM).
- `phpunit.xml` `DB_DATABASE` `xlrm` → `xlrm_testing`; `.env.example` gains `MYSQL_BIN_DIR` (DEC-011).

## Runtime / bootstrap
- `bootstrap/app.php`: command `ImportRbacMasterCommand` (nonexistent) → `ImportRbacMaster` (DEC-010); aliases `role`, `permission`, `role_or_permission` registered (DEC-012).
- PHP 8.4.26 installed side by side (SHA-256 verified), php.ini mirrored from 8.3 (+redis 6.3.0, deprecated `session.sid_*` commented); 6 implicit-nullable params fixed (`XlDelivery`, `BookingStateService`, `OtpNotificationService`, `SystemSettingService::getForAdmin`, `EntityHistoryService::createMaster`, `EmpPostAssignmentFactory`) (DEC-014/019). User switched Laragon + PATH; WAMP entries removed from user PATH (backup `docs/decisions/path-backup-26-09-2026.txt`) (DEC-026).

## API & IAM
- `User` + `HasApiTokens` (OTP login can issue tokens); `/api/v1/pricing/*` `auth:api` (nonexistent guard) → `auth:sanctum`+`validate_device`; admin settings group `role:admin|super_admin` (roles never existed) → `permission:UTL_SETTINGS_MANAGE` (DEC-012/016).
- `NotificationController`/`SystemSettingApiController`: removed constructor `$this->middleware()` (fatal on Laravel 11+ — every notifications/settings API call failed) (BUG-159, DEC-017).
- `SystemSetting`: added `byTopic`/`byGroup` scopes, `getByTopic()`, `allByTopic()` (topic = column or key prefix); `set()` uses `save()` (no phantom `updatedby`, cache actually flushed); `flushAllCache()` no longer uses unsupported cache tags (DEC-021).
- New API methods: `NotificationController::revokeAllDevices()`, `SystemSettingApiController::{site,dealership,pricing}Settings()` (DEC-020).
- Roles = designations: Role menu removed; `iam/role` redirects to Org → Designation; `RoleRequest` unique rule on configured roles table; `IAM\Role` gets `CrudTrait`, drops misleading `$table`; `M_Post` import sheet disabled (DEC-018).
- `GrantDashboardPermissionSeeder`: `admin.dashboard` → 75 designations (run on `xlrm` + `xlrm_testing`; reversible via `storage/logs/grant-dashboard-permission-{db}.json`) (BUG-160, DEC-022).

## Routes removed / hidden
- Removed (methods never existed, no UI entry): `sales.booking.order-verify`, `sales.enquiry.pending`, `vehicle.model.destroy`, `accounts.receipt.destroy`, `api pricing/generate-quote`, later `api pricing/calculate-exchange` (called a method that never existed) (DEC-020/030).
- Hidden until Track B (menu + route): `sales.quotation.pending`, `sales.enquiry.erroneous` (DEC-023).

## Booking
- Migration `2026_09_26_120000_add_missing_columns_to_xlr8_booking_master`: `sale_type`, `final_data` (json), `consultant` (idx), `buyer_type`, `accessories`, `segment_code` (idx) — types approved; booking create/edit/OTF save no longer crash (BUG-104, DEC-025). Run on local `xlrm` + `xlrm_testing` (up/down/up verified).
- `BookingCoreService::store()`: `dd()` → `Log::error` + rethrow; `update()` keeps stored `col_type` when omitted (NOT NULL) (DEC-027).
- `BookingOtfService::generateVotfNumber()`: branch = booking → linked enquiry `dealer_branch` → FSC's primary branch (DEC-027/029); BUG-161 logged (no branch data).
- `editRefund` implemented via `BookingRefundService::applyRefundDetailsEdit()` (DEC-024).
- Tests: crash-documenting tests replaced by regression tests; +2 refund-edit tests; +2 VOTF branch tests.

## Dead-code purge (DEC-030)
468 tracked files / 58,671 lines removed after reference checks: `app/Models_backup/`, 8 dead helpers, 21 dead controllers + both pricing API controllers, 13 FormRequests, dead/duplicate models & traits, 5 dead services, unused imports/sheets, `oldImportEnquiriesJob`, Post-module tests/factories/bindings, `resources/views/backup/`, 11 orphan views, 182 Backpack overrides identical to the originals, stray files. Fixed instead of deleted: `OtpNotificationService` email view names (`emails.verification_success_email`, `emails.account_locked_email`).

## AI context consolidation (DEC-031)
- 65 files archived via `git mv` to `.ai/_archive/2026-09-26/` with `MANIFEST.md` (origin path, blob hash, coverage, new home).
- New: `.ai/README.md`, `.ai/guidelines/{00-project,10-workflow,20-architecture}.md` (+ overrides for Boost's Backpack/Laradocs guidelines), `.ai/rules/{admin-backpack,database,services,api,imports,ui,testing}.md`, `.ai/rules/modules/{iam-rbac,org-person,vehicle-pricing,sales}.md`, 5 ported skills with frontmatter, `.ai/knowledge/{specs/index,decisions}.md`, `.ai/state/current.md`.
- New `php artisan ai:refresh-context` → `.ai/knowledge/db/*.md` (135 tables, 10 cards), `.ai/state/bugs-index.md` (open bugs), `.claude/rules/` sync.
- `boost.json`: agents `claude_code`+`codex` (root `AGENTS.md`), dropped tailwind/cloud skills; root `CLAUDE.md`/`AGENTS.md` regenerated (≈41 KB → ≈19 KB always-loaded).
- `.mcp.json`: Boost server PHP path/cwd moved from nonexistent `C:` paths to `D:` PHP 8.4; `.claude/settings.local.json`: invalid `beforeCommand` hook removed; clean `.kilo` worktrees removed.

## Environment notes
- `composer dump-autoload` with `optimize-autoloader=true` stalls locally (~37k files in `google/apiclient-services` under on-access AV scanning). Local dumps were run non-optimized (composer.json unchanged); production keeps `-o`.

## Verification
Pint clean; `php -l` on all touched files; tests on `xlrm_testing`: **215 passed, 1 skipped, 1 failed** (only the pre-existing missing-fixture test); API round trips with device-bound tokens; admin smoke as superadmin + scoped user.
