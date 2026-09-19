# Known Bugs Report

**This file is a continuously-updated, permanent bug tracker for this repository.** It is not
dated like `ai-changelogs-*`/`ai-findings-*` — it persists and gets edited in place. Every entry
stays forever, even once fixed, so there's a permanent record of what broke, when it was found,
and what fixed it (or what's still pending).

## Rule for every AI tool working on this repo

**Before starting work in an area, check whether it already has a known-bug entry here — don't
rediscover something already documented.** Whenever you find a new independent bug (not the
specific thing you were asked to fix, but something adjacent you noticed), add an entry
immediately, don't wait until the end of a task. Whenever you fix one, update its entry — don't
just note it in a changelog and leave this file stale. Whenever new information changes an
existing entry (root cause turns out different, severity changes, etc.), append to that entry's
Notes with a new "Modified" timestamp — never silently overwrite prior findings.

Entry format:

```
### BUG-XXX — <short title>

- **Status:** OPEN | FIXED | WON'T FIX (with reason)
- **Severity:** Critical | High | Medium | Low | Cosmetic
- **Found:** DD-MM-YYYY HH:MM — <how/where it was found>
- **Modified:** DD-MM-YYYY HH:MM — <what changed, if anything, since Found> (repeatable, one line per update)
- **Fixed:** DD-MM-YYYY HH:MM — <link to the ai-changelogs entry with the actual diff> (once fixed)
- **Where:** <file(s)/route(s)/screen(s) affected>
- **Description:** <what's actually wrong>
- **Proposed solution:** <what should be done, and any decision the fix depends on>
```

---

## Index

| ID | Title | Severity | Status | Found | Fixed |
|---|---|---|---|---|---|
| BUG-001 | `/admin` had zero permission enforcement beyond login | Critical | FIXED | 19-09-2026 | 19-09-2026 21:55 |
| BUG-002 | `User::isSuperAdmin()` called in 6 places, method didn't exist | High | FIXED | 19-09-2026 22:20 | 19-09-2026 22:20 |
| BUG-003 | `UserCrudController::destroy()` used `isSuperAdmin()` as a query scope | Medium | FIXED | 19-09-2026 22:20 | 19-09-2026 22:20 |
| BUG-004 | Stray `/` in `UserImportExportController.php` blocked `route:list` | High | FIXED | pre-19-09-2026 (reported, not fixed, in an earlier session) | 19-09-2026 23:15 |
| BUG-005 | `UserCrudController` checked `user.*` (singular) against real `users.*` (plural) permissions | High | FIXED | 19-09-2026 (~23:00) | 19-09-2026 23:45 |
| BUG-006 | `checkPermission` middleware registered but wired to no route | Medium | OPEN | 19-09-2026 (pre-rollout sweep) | — |
| BUG-007 | `/admin/user` — Backpack's `hasAccessOrFail('list')` throws unconditionally | Critical | OPEN | 19-09-2026 (~22:30) | — |
| BUG-008 | `EmployeeCrudController` references non-existent columns (`person_id` etc.) | High | OPEN | 20-09-2026 09:45 | — |
| BUG-009 | `BrandCrudController` — `xlr8_vehicle_brand` table doesn't exist | High | OPEN | 20-09-2026 10:30 | — |
| BUG-010 | `SubSegmentCrudController::getSegmentsByBrand` — dead route, method never defined | Low | OPEN | 20-09-2026 11:15 | — |
| BUG-011 | `SubSegment.name` validated/submitted but not `$fillable` — silently never saved | Medium | OPEN | 20-09-2026 11:15 | — |
| BUG-012 | `VehicleModelCrudController::destroy()` — dead route, method never defined | Low | OPEN | 20-09-2026 12:00 | — |
| BUG-013 | `RoleCrudController` — `Role` model missing `CrudTrait`, screen 500s for everyone | Critical | OPEN | 20-09-2026 13:00 | — |
| BUG-014 | `SystemSettingCrudController` — list view needs uninstalled `backpack/pro` filter | Medium | OPEN | 20-09-2026 13:00 | — |
| BUG-015 | `PostCrudController` / `UserTypeCrudController` — entirely unreachable, no routes | Medium | OPEN | 20-09-2026 13:00 | — |
| BUG-016 | `RoleRequest` validation references non-existent `xlr8_iam_roles` table | Medium | OPEN | 20-09-2026 13:00 | — |
| BUG-017 | Dead/unused traits and classes (`HasAuditFields`, `ScopedQuery`, `AfterImportListener`, `BrandCrudController::import()`) | Low | OPEN | 19-09-2026 / 20-09-2026 (see entry) | — |
| BUG-018 | `App\Models\IAM\Role` has a misleading, dead `$table` property | Cosmetic | OPEN | 19-09-2026 22:20 | — |
| BUG-019 | Unexplained large external change to `BookingCrudController.php` | Unknown | OPEN — needs owner input | 20-09-2026 13:00 | — |
| BUG-020 | `PersonAddressCrudController` references columns that don't exist (`type`, `person_id`, `is_primary`) | High | OPEN | 20-09-2026 15:30 | — |
| BUG-021 | `PersonBankingDetailCrudController` — missing `CrudTrait` + wrong column names (stacked) | Critical | OPEN | 20-09-2026 16:00 | — |
| BUG-022 | `VehicleAccessoryCrudController` — no Operation traits, `Route::crud()` registers nothing, entirely unreachable | Medium | OPEN | 20-09-2026 16:30 | — |
| BUG-023 | `Route::crud('keyvalue', 'KeyvalueCrudController')` — wrong case, works on Windows only | High | FIXED | 20-09-2026 16:30 | 20-09-2026 16:45 |

Not a bug (false positive, listed for reference): the original `infer-conventions` sweep flagged
"`SheetHeaderService`/`SynonymService` not used by importers" — re-investigation on 19-09-2026
found this was a false positive (the services are real, used correctly, and correctly scoped to
the vehicle-pricing pipeline only). No entry needed; no fix needed.

---

## Entries

### BUG-001 — `/admin` had zero permission enforcement beyond login

- **Status:** FIXED
- **Severity:** Critical
- **Found:** 19-09-2026 — flagged by the `infer-conventions` sweep before this rollout began; investigated same day.
- **Fixed:** 19-09-2026 21:55 — see `ai-changelogs-19-09-2026.md`, 21:55 entry.
- **Where:** `app/Http/Middleware/CheckIfAdmin.php`, applied via all 4 `routes/backpack/*.php` files.
- **Description:** `CheckIfAdmin::checkIfUserIsAdmin()` was the unmodified Backpack vendor stub (`return true;`). Any authenticated user of any `user_type` (Emp, Cust, DSA, Insurer, Associate) reached all 58 CrudControllers.
- **Proposed solution (implemented):** gate on `$user->user_type === 'Emp' && $user->is_active`, verified against real DB data showing zero overlap risk. Deliberately coarse — not per-controller RBAC, which is the separate rollout this file also tracks bugs from.

### BUG-002 — `User::isSuperAdmin()` called in 6 places, method didn't exist

- **Status:** FIXED
- **Severity:** High
- **Found:** 19-09-2026 22:20 — user asked to fix it directly.
- **Fixed:** 19-09-2026 22:20 — see `ai-changelogs-19-09-2026.md`, 22:20 entry.
- **Where:** `app/Models/User.php`; call sites in `RBACService`, `DataScopeFilter`, `ScopedCrud`, `CheckSuperAdmin` middleware, `UserCrudController`.
- **Description:** Every call site would fatal with "Call to undefined method." Confirmed via testing that this made the app's entire super-admin bypass logic unusable.
- **Proposed solution (implemented):** `isSuperAdmin(): bool { return $this->hasRole('superadmin'); }`, matching the only real precedent (`UserImporter::assignRolesAndPermissions()` already calls `assignRole('superadmin')`). Tested both directions (fails closed today since 0 users hold the role; works correctly once granted, verified in a rolled-back transaction).

### BUG-003 — `UserCrudController::destroy()` used `isSuperAdmin()` as if it were a query scope

- **Status:** FIXED
- **Severity:** Medium
- **Found:** 19-09-2026 22:20 — found while fixing BUG-002.
- **Fixed:** 19-09-2026 22:20 — see `ai-changelogs-19-09-2026.md`, 22:20 entry.
- **Where:** `app/Http/Controllers/Admin/UserCrudController.php:456` (line number as of discovery).
- **Description:** `User::isSuperAdmin()->count()` — once `isSuperAdmin()` became a real boolean instance method (BUG-002's fix), this static call would resolve via Eloquent's `__callStatic` to `(new User)->isSuperAdmin()` (a bool), then fatal calling `->count()` on a bool.
- **Proposed solution (implemented):** changed to `User::role('superadmin')->count()`, Spatie's built-in scope. Verified the exact "prevent deleting the last super admin" guard condition evaluates correctly once a real super admin exists.

### BUG-004 — Stray `/` in `UserImportExportController.php` blocked `route:list`

- **Status:** FIXED
- **Severity:** High (blocked all tooling that reflects this class; would 500 any real request routed to it)
- **Found:** pre-19-09-2026 — reported (not fixed) in an earlier session, logged in `claude-findings.md` §4.
- **Fixed:** 19-09-2026 23:15 — see `ai-changelogs-19-09-2026.md`, 23:15 entry (this rollout needed `route:list` working, so it was fixed here rather than left as report-only a second time).
- **Where:** `app/Http/Controllers/UserImportExportController.php:174`.
- **Description:** A single stray `/` on its own line (`/`) — a `ParseError`, not a warning. PHP could not compile the file at all.
- **Proposed solution (implemented):** removed the stray line.

### BUG-005 — `UserCrudController` checked `user.*` (singular) against real `users.*` (plural) permissions

- **Status:** FIXED
- **Severity:** High (silently blocked everyone, including legitimate admins)
- **Found:** 19-09-2026, during the permission-taxonomy investigation for the rollout.
- **Fixed:** 19-09-2026 23:45 — see `ai-changelogs-19-09-2026.md`, 23:45 entry.
- **Where:** `app/Http/Controllers/Admin/UserCrudController.php` (`setupListOperation`, `setupCreateOperation`, `setupUpdateOperation`).
- **Description:** Checked `can('user.view'|'user.create'|'user.edit')`; real permissions in `xlr8_iam_permissions` are `users.view`/`users.create`/`users.update`. A permission name that doesn't exist always evaluates false.
- **Proposed solution (implemented):** renamed the three checks to the real permission strings.

### BUG-006 — `checkPermission` middleware registered but wired to no route

- **Status:** OPEN
- **Severity:** Medium
- **Found:** 19-09-2026 — `infer-conventions` sweep, before this rollout began (`claude-first-inspection.md` §0.1).
- **Where:** `bootstrap/app.php` (alias registration); no `routes/*.php` file references it.
- **Description:** The middleware exists and is aliased, but zero routes use `checkPermission:...` or `permission:...`. Before BUG-001's fix, this meant no route-level permission enforcement existed anywhere.
- **Proposed solution:** superseded in effect by BUG-001's coarse gate and this rollout's per-controller `abort(403,...)` checks. Once the controller rollout is far enough along, decide whether to (a) leave the ad hoc in-method `abort()` style as the standard (what every controller in this rollout now uses) and remove the unused middleware alias, or (b) formally migrate to `->middleware('checkPermission:...')` on routes instead. Not urgent — no functional gap either way once the rollout finishes.

### BUG-007 — `/admin/user` — Backpack's `hasAccessOrFail('list')` throws unconditionally

- **Status:** OPEN
- **Severity:** Critical (a real admin screen — user management — is completely unusable for everyone, including a user with the wildcard `*` permission)
- **Found:** 19-09-2026 (~22:30), while testing BUG-005's fix.
- **Where:** `app/Http/Controllers/Admin/UserCrudController.php` (relies on Backpack's default `ListOperation::index()` — no custom override).
- **Description:** `GET /admin/user` returns `403` even for a user holding every permission including `*`, confirmed in a single fresh process (not a testing artifact). The 403 is Backpack's own `CrudPanel::hasAccessOrFail('list')` (`vendor/backpack/crud/src/app/Library/CrudPanel/Traits/Access.php:85-88`), thrown before `setupListOperation()` (where the app's own permission check lives) is ever reached. `UserCrudController::setup()` does call `$this->crud->allowAccess(['list','create','update','delete','show'])`, which should be sufficient for `hasAccess('list')` to return `true` — but empirically doesn't.
- **Proposed solution:** root-cause via targeted debugging: (a) add temporary logging inside `hasAccess()`/`allowAccess()` to confirm whether `list.access` is actually `true` by the time `hasAccessOrFail` reads it — a lifecycle/ordering issue is the leading theory; (b) check whether any service provider or shared trait calls `denyAccess()` after `setup()` runs; (c) compare against a *working* controller that also relies on Backpack's default `index()` (none confirmed working yet — every controller successfully tested in this rollout so far has a custom `index()` override that bypasses this code path entirely, so there's no positive control case yet). Once root-caused, likely a small, targeted fix.

### BUG-008 — `EmployeeCrudController` references non-existent columns

- **Status:** OPEN
- **Severity:** High (entire Employee admin screen — list, create, edit, update — likely never worked)
- **Found:** 20-09-2026 09:45, batch 4 of the permission rollout.
- **Where:** `app/Http/Controllers/Admin/Org/Employee/EmployeeCrudController.php` (`index()`, `store()`, `update()`).
- **Description:** Queries/validates `person_id`, `designation_id`, `primary_branch_id`, `primary_department_id` — none exist on `xlr8_admin_employee`. Confirmed via `database-schema`: real columns are `person_code`, `desig_code`/`designation_code`, `primary_branch_code`, `primary_dept_code`, etc. — this app's documented code-based relations convention.
- **Proposed solution:** rewrite `index()`'s `select()`, and `store()`/`update()`'s validation + mass-assignment, to use the real code-based columns. First confirm whether `Employee`'s relationship methods (`person()`, `designation()`, `primaryBranch()`, `primaryDepartment()`) are already correct — if so, only the controller needs fixing, not the model.

### BUG-009 — `BrandCrudController` — backing table doesn't exist

- **Status:** OPEN
- **Severity:** High (entire Brand screen non-functional)
- **Found:** 20-09-2026 10:30, batch 5 of the permission rollout.
- **Where:** `app/Http/Controllers/Admin/Vehicle/Brand/BrandCrudController.php`; model `App\Models\Vehicle\Brand`.
- **Description:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'xlrm.xlr8_vehicle_brand' doesn't exist`. The dead `import()` method (see BUG-017) hardcodes a single brand code `'MHD'`, suggesting this app may only ever have supported one brand and the whole entity was abandoned.
- **Proposed solution:** business decision needed before any code fix: was the table dropped/renamed, or is "Brand" no longer a real concept in this schema? Confirm intent, then either create the table, repoint the model, or remove the screen.

### BUG-010 — `SubSegmentCrudController::getSegmentsByBrand` — dead route

- **Status:** OPEN
- **Severity:** Low
- **Found:** 20-09-2026 11:15, batch 6 of the permission rollout.
- **Where:** `routes/backpack/core.php` (`sub-segment/segments/{brandCode}` route) → `SubSegmentCrudController`, which has never defined this method.
- **Description:** Hitting this route throws "method does not exist." Looks like an abandoned AJAX helper, possibly superseded by the working `getSubSegmentsBySegment`.
- **Proposed solution:** check whether any frontend JS still calls this endpoint; if not, remove the dead route. If it is called, implement the method (likely a simple `Segment::whereHas(...)` style query — needs the actual UI requirement to write correctly).

### BUG-011 — `SubSegment.name` validated/submitted but not `$fillable` — silently never saved

- **Status:** OPEN
- **Severity:** Medium (silent data-loss bug — no error shown anywhere)
- **Found:** 20-09-2026 11:15, batch 6 of the permission rollout (discovered while building a test fixture).
- **Where:** `app/Models/Vehicle/SubSegment.php` (`$fillable`); `SubSegmentCrudController::update()`.
- **Description:** `$fillable` omits `name` (only has `oem_name`, `description`), but `update()` validates and mass-assigns a `name` field. Eloquent silently ignores non-fillable keys on mass assignment rather than throwing — editing a Sub Segment's name through the admin screen has likely never actually persisted.
- **Proposed solution:** add `'name'` to `SubSegment::$fillable`. One-line fix. Worth a quick check for similar fillable/validated-field mismatches on other models while in there (this is the second such case found this session, alongside the different-category BUG-008).

### BUG-012 — `VehicleModelCrudController::destroy()` — dead route

- **Status:** OPEN
- **Severity:** Low
- **Found:** 20-09-2026 12:00, batch 7 of the permission rollout.
- **Where:** `routes/backpack/core.php` (`DELETE vehicle-model/{id}` route) → `VehicleModelCrudController`, which has never defined `destroy()`.
- **Description:** Same category as BUG-010 — route registered, handler missing.
- **Proposed solution:** trivial — add `public function destroy($id) { return $this->crud->delete($id); }`, the same one-liner used everywhere else in this rollout. Lowest-effort fix in this entire list.

### BUG-013 — `RoleCrudController` — `Role` model missing `CrudTrait`, screen 500s for everyone

- **Status:** OPEN
- **Severity:** Critical (this is the actual RBAC role-management screen the whole permission rollout depends on)
- **Found:** 20-09-2026 13:00, batch 8 of the permission rollout.
- **Where:** `app/Models/IAM/Role.php`; surfaces in `app/Http/Controllers/Admin/Iam/Role/RoleCrudController.php`.
- **Description:** `App\Models\IAM\Role` doesn't use Backpack's `CrudTrait`, which `CRUD::setModel()` requires internally. Fails inside `setup()`, before any permission check (added this rollout) can run. `GET /admin/role` returns `500` regardless of permission held.
- **Proposed solution:** add `use Backpack\CRUD\app\Models\Traits\CrudTrait;` to `App\Models\IAM\Role`. Low risk — standard trait already used by every other Backpack-managed model in this app. Before adding it, confirm this wasn't deliberate (e.g., is role management meant to happen some other way, via seeders/artisan commands rather than this UI?).

### BUG-014 — `SystemSettingCrudController` — list view needs uninstalled `backpack/pro`

- **Status:** OPEN
- **Severity:** Medium
- **Found:** 20-09-2026 13:00, batch 8 of the permission rollout.
- **Where:** `app/Http/Controllers/Admin/Utils/SystemSetting/SystemSettingCrudController.php::setupListOperation()`.
- **Description:** Calls `$this->crud->addFilter(...)`, a Backpack PRO-only feature. `backpack/pro` is confirmed not installed. `GET /admin/system-settings` 500s once permission checks pass (permission checks themselves work correctly — confirmed `403` without permission).
- **Proposed solution:** remove the filter (no free equivalent exists per this project's own Backpack guidance — `addClause` is explicitly documented as not a substitute), or purchase/install `backpack/pro` if filtering is actually needed on this screen. Needs a decision, not a guess.

### BUG-015 — `PostCrudController` / `UserTypeCrudController` — entirely unreachable

- **Status:** OPEN
- **Severity:** Medium (dead code, or unfinished feature — unclear which)
- **Found:** 20-09-2026 13:00, batch 8 of the permission rollout.
- **Where:** `app/Http/Controllers/Admin/PostCrudController.php`, `app/Http/Controllers/Admin/UserTypeCrudController.php`; no route in any route file references either.
- **Description:** `PostCrudController` is substantial and real-looking (org scopes, vehicle scopes, a dedicated `PostService`, already calls `hasAccessOrFail()` itself) — looks mid-development or deliberately shelved, not garbage. `UserTypeCrudController` is a standard, complete CRUD controller with no obvious reason to be unwired.
- **Proposed solution:** needs a decision — finish and wire these up (add the missing `Route::crud(...)` registrations), or remove them if superseded/abandoned. Can't tell from the code alone.

### BUG-016 — `RoleRequest` validation references non-existent `xlr8_iam_roles` table

- **Status:** OPEN
- **Severity:** Medium (blocked on BUG-013 — no point fixing validation on a screen that 500s before reaching it)
- **Found:** 20-09-2026 13:00, batch 8 of the permission rollout (while writing `RoleRequest` — the original inline validation had the same issue, just relocated verbatim).
- **Where:** `app/Http/Requests/RoleRequest.php`.
- **Description:** `Rule::unique('xlr8_iam_roles', 'name')` — this table doesn't exist (confirmed 19-09-2026 during the `isSuperAdmin()` investigation: `Role`'s real table is `xlr8_admin_designation`, via Spatie's base `Role` model overriding `App\Models\IAM\Role`'s own `$table` property at runtime). Deliberately preserved verbatim rather than silently corrected, consistent with how every other pre-existing bug in this rollout was handled.
- **Proposed solution:** change to `Rule::unique('xlr8_admin_designation', 'name')`. Trivial fix, but sequence it after BUG-013 (fixing this alone won't make the screen usable).

### BUG-017 — Dead/unused traits and classes

- **Status:** OPEN
- **Severity:** Low
- **Found:** 19-09-2026 22:20 (`HasAuditFields`, `ScopedQuery` — during the `isSuperAdmin()` investigation) and 20-09-2026 10:30 (`BrandCrudController::import()` — during batch 5). `AfterImportListener` was found earlier still, during the original `infer-conventions` Jobs/Imports sweep before this rollout began.
- **Where:**
  - `app/Models/Traits/HasAuditFields.php` — duplicates `BaseModel`'s actor-stamping logic exactly, never `use`d by any model.
  - `app/Models/Traits/ScopedQuery.php` — defines `withoutDataScope()`; `.ai/rules/rbac-scopes.md` describes this as load-bearing for jobs, but it's never actually `use`d anywhere in `app/`.
  - `app/Imports/Listeners/AfterImportListener.php` — 0 bytes, unreferenced.
  - `BrandCrudController::import()` (pre-move code, now at `app/Http/Controllers/Admin/Vehicle/Brand/BrandCrudController.php`) — large bulk Excel-import method; confirmed via repo-wide grep that no route calls it.
- **Description:** all four are confirmed dead code by direct search, not behavioral bugs — but `ScopedQuery` is concerning because `.ai/rules` documentation asserts it's actively used for job-level data-scope bypassing, when it demonstrably isn't wired into anything. Worth understanding whether data scoping in jobs is actually enforced some other way, or whether this is a real gap the documentation is papering over.
- **Proposed solution:** safe to delete `HasAuditFields`, `AfterImportListener`, and `BrandCrudController::import()` once confirmed nothing external references them (e.g. no queued job class-name lookup, no scheduled command). `ScopedQuery` needs the data-scoping-in-jobs question answered first, before deciding whether to wire it up for real or delete it as aspirational-but-unused.

### BUG-018 — `App\Models\IAM\Role` has a misleading, dead `$table` property

- **Status:** OPEN
- **Severity:** Cosmetic (not a functional bug — confirmed the runtime table resolves correctly regardless)
- **Found:** 19-09-2026 22:20, during the `isSuperAdmin()` investigation.
- **Where:** `app/Models/IAM/Role.php`.
- **Description:** Declares `protected $table = 'xlr8_iam_roles'`, a table that doesn't exist. Spatie's base `Role` model constructor forcibly overrides `$table` to `config('permission.table_names.roles')` (`xlr8_admin_designation`) at runtime, so the declared property never actually takes effect — confirmed empirically. Purely a maintainability footgun: a future reader could reasonably (and wrongly) assume the declared property is accurate.
- **Proposed solution:** delete the misleading line, or replace with a comment explaining the override. No behavior change either way — purely a readability fix.

### BUG-019 — Unexplained large external change to `BookingCrudController.php`

- **Status:** OPEN — needs owner input, not further investigation from inside a session
- **Severity:** Unknown (content not reviewed — could be entirely benign)
- **Found:** 20-09-2026 13:00, during batch 8's `vendor/bin/pint --dirty` run.
- **Where:** `app/Http/Controllers/Admin/BookingCrudController.php`.
- **Description:** Pint's `--dirty` flag (which only touches files already showing as git-modified) reformatted this file — 2632 insertions / 3300 deletions — despite this AI session never touching it in any tool call across two full days of work. This can only mean the file was modified outside this session, concurrently, by something else (an editor, another process, another person) between the batch-7 and batch-8 Pint runs. `php -l` confirms it still parses correctly after the reformat, but the underlying content/intent of the original (pre-Pint) change is unknown to this session.
- **Proposed solution:** not something an AI session can resolve — needs the repo owner to confirm what that change actually was and whether it was intentional, before it goes anywhere near a commit.

### BUG-020 — `PersonAddressCrudController` references columns that don't exist

- **Status:** OPEN
- **Severity:** High (entire Person Address admin screen — list, create, update — likely never worked)
- **Found:** 20-09-2026 15:30, batch 11 of the permission rollout.
- **Where:** `app/Http/Controllers/Admin/Org/PersonAddress/PersonAddressCrudController.php` (`index()`, `store()`, `update()`).
- **Description:** `GET /admin/person-address` 500s: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'type' in 'field list'`. Checked the real schema directly (`SHOW COLUMNS FROM xlr8_admin_person_addresses`): the real column is `address_type` (enum: `Primary/Office/Home/Alternate/Permanent`), not `type`. The controller also references `person_id` in `store()`/`update()`'s validation (`exists:xlr8_admin_person,id`), but the real FK on this table is `person_code`, not `person_id`. There is also **no `is_primary` column at all** — "primary" is represented by `address_type = 'Primary'`, not a separate boolean, so the `is_primary` field referenced throughout the controller and grid doesn't exist either. Same category of bug as BUG-008 (`EmployeeCrudController`) — a controller written against assumed column names that don't match the actual schema.
- **Proposed solution:** rewrite `index()`'s `select()`, and `store()`/`update()`'s validation and mass-assignment, to use `address_type` (with the real enum values) and `person_code` instead of `type`/`person_id`, and remove or replace the `is_primary` handling with an `address_type === 'Primary'` check. Needs a look at the `PersonAddress` model's actual relationships/casts first to confirm nothing else assumes the wrong column names too.

### BUG-021 — `PersonBankingDetailCrudController` — two stacked independent bugs (missing `CrudTrait` + wrong column names)

- **Status:** OPEN
- **Severity:** Critical (screen 500s for everyone, before any permission check runs)
- **Found:** 20-09-2026 16:00, batch 12 of the permission rollout.
- **Where:** `app/Models/Admin/PersonBankingDetail.php` (missing trait); `app/Http/Controllers/Admin/Org/PersonBankingDetail/PersonBankingDetailCrudController.php` (`index()`, `store()`, `update()` — wrong columns).
- **Description:** Two independent, stacked bugs on the same screen:
  1. `GET /admin/person-banking-detail` returns `500` **even with no permission at all** (the crash happens before `setupListOperation()`'s permission check runs): `Exception: Please use CrudTrait on the model.` — `App\Models\Admin\PersonBankingDetail` doesn't use Backpack's `CrudTrait`, same category as BUG-013 (`RoleCrudController`/`App\Models\IAM\Role`).
  2. Even once that's fixed, the controller's queries/validation still reference columns that don't exist on `xlr8_admin_person_banking_details`: `person_id` (real FK is `person_code`), `swift_code` (real column is `micr_code`), `is_primary` (doesn't exist at all), and `account_type` is validated against `savings/current/fd/rd/other` when the real enum is `Primary/Secondary/Joint/Trust`. Confirmed via `SHOW COLUMNS FROM xlr8_admin_person_banking_details`. Same category as BUG-008/BUG-020.
- **Proposed solution:** add `use Backpack\CRUD\app\Models\Traits\CrudTrait;` to `PersonBankingDetail` first (same low-risk fix as BUG-013's proposal), then separately rewrite the controller's column references to match the real schema (`person_code`, `micr_code`, drop or replace `is_primary`, correct the `account_type` enum values). Both fixes are needed before this screen works at all — fixing only one leaves the other blocking.

### BUG-022 — `VehicleAccessoryCrudController` is entirely unreachable — no Operation traits, so `Route::crud()` registers nothing

- **Status:** OPEN
- **Severity:** Medium (a real, substantial controller — import/export, custom columns — is completely dead)
- **Found:** 20-09-2026 16:30, while picking batch 13 candidates for the permission rollout.
- **Where:** `app/Http/Controllers/Admin/VehicleAccessoryCrudController.php`; registered via `Route::crud('vehicle-accessory', VehicleAccessoryCrudController::class)` in `routes/backpack/core.php`.
- **Description:** Unlike `PostCrudController`/`UserTypeCrudController` (BUG-015, simply never registered), this controller *is* passed to `Route::crud()` — but that macro only generates routes for operations backed by an Operation trait (`ListOperation` → index/search routes, `CreateOperation` → create/store, etc.), and `VehicleAccessoryCrudController` declares **none** of them (`use ListOperation`, `CreateOperation`, `UpdateOperation`, `DeleteOperation` are all absent). Confirmed via `php artisan route:list --path=admin/vehicle-accessory` (zero results) and a full `route:list | grep -i vehicle-accessory` (also zero results) — genuinely no route exists for this controller at all, not even its own `import()`/`export()`/`showImportForm()`/`showExportForm()`/`downloadTemplate()`/`importHistory()`/`exportHistory()` methods, despite those being real, complete-looking implementations (Excel import/export via `AccessoryImportService`/`AccessoryExportService`).
- **Proposed solution:** needs a decision — was this controller mid-migration (operation traits removed/never added) or is it meant to be wired up differently (e.g., via manual routes like `VehicleModelCrudController` uses)? If the latter, add manual `Route::get/post/...` entries for each method (including the operation traits it needs, or `index()`/`create()`/`store()`/etc. overrides) the same way `VehicleModelCrudController` was set up. If it's genuinely superseded/abandoned, remove it. Skipped as a permission-rollout batch candidate — no way to test wiring on completely unreachable code.

### BUG-023 — `Route::crud('keyvalue', 'KeyvalueCrudController')` references a class name with the wrong case — works on Windows, would fatal on Linux

- **Status:** FIXED (as an inevitable side effect of batch 13's routine string→`::class` conversion, not a separately-scoped fix)
- **Severity:** High (latent — invisible in local Windows dev, would break admin `/admin/keyvalue` entirely on any case-sensitive filesystem, i.e. virtually any production Linux server)
- **Found:** 20-09-2026 16:30, while picking batch 13 candidates for the permission rollout.
- **Fixed:** 20-09-2026 16:45 — see `ai-changelogs-20-09-2026.md`, batch 13 entry.
- **Where:** `routes/backpack/core.php` (`Route::crud('keyvalue', 'KeyvalueCrudController')`); actual class is `App\Http\Controllers\Admin\KeyValueCrudController` (capital `V`) in `app/Http/Controllers/Admin/KeyValueCrudController.php`.
- **Description:** The route string used `'KeyvalueCrudController'` (lowercase `v`), but the actual class/file is `KeyValueCrudController` (capital `V`). Windows/NTFS filesystems are case-insensitive, so Composer's PSR-4 autoloader successfully found and loaded the file anyway (confirmed via `php artisan route:list --path=admin/keyvalue`, which resolved and listed all 7 routes without error) — but on a case-sensitive filesystem (standard on Linux, where this app is almost certainly deployed in production), the autoloader's file-path lookup for `KeyvalueCrudController.php` would not match the actual `KeyValueCrudController.php` on disk, causing a fatal "Class not found" error the first time anyone hit any `/admin/keyvalue*` route.
- **Proposed solution (implemented as a side effect):** every controller wired in this rollout gets its `Route::crud(...)` string call converted to an explicit `use` import + `SomeController::class` reference — this is the standard, mechanical step already applied to all 22 previously-migrated controllers. Doing that same conversion for `KeyValueCrudController` requires using its *real* class name to even compile, which corrects the case mismatch automatically. Not a bug fix decided separately from the rollout's routine work.
