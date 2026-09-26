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
