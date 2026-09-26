# AI Findings — 21-09-2026

## Hardcoded-URL audit methodology had a blind spot spanning batches 30-34 (BUG-066)

While finishing batch 35 (Iam module), Pint's IDE-diagnostic hook surfaced an old, un-renamed URL
still present inside `ModulesCrudController.php`'s own PHP source, even though that entity's
Blade-view URL audit (the established per-batch check since batch 30) had already been completed and
signed off. Investigating turned up a structural gap in the audit process itself, not a one-off
mistake: every batch since 30 grepped `resources/views/**` for hardcoded `backpack_url()`/route
strings, but never grepped the controllers' own PHP source — even though controllers routinely build
`backpack_url()` calls internally (AJAX grid "Edit"/"View" button URLs assembled inside a
`->map()` callback, and `redirect(backpack_url(...))` calls after `store()`/`update()`). This means
every controller migrated in batches 30-34 (Sales's 6 entities, 9 smaller entities, Vehicle's 6
entities) plus batch 35's own 4 Iam controllers potentially carried this defect. Confirmed and fixed
all 20 affected files — full list and fix details in
`docs/refactor/ai-changelogs-21-09-2026.md` and `docs/refactor/known-bugs-report.md` BUG-066.

A second, compounding gap: the first-pass bulk fix script only matched single-line
`backpack_url('...')` calls (opening quote on the same line as the opening paren). Calls formatted
across multiple lines — `backpack_url(\n    "old/path/{$var->id}/edit"\n)` — were silently missed by
that script and had to be found via a separate, manual `grep -n "backpack_url($"` sweep (bare-paren
line) followed by hand-inspecting each hit's next 1-3 lines.

**Process fix applied**: `.ai/rules/module-structure.md` §6 now permanently requires, for every
future batch, both (a) grepping the controller's own PHP source for `backpack_url(`, not just its
Blade views, and (b) a separate grep for the bare multi-line form. This should prevent a recurrence
in the remaining Org/Pricing batches.

**Why this matters for future batches**: this is the second methodology gap found in this rollout
(after BUG-064's `'operation'`-key trap) that only surfaced because of incidental IDE-diagnostic
output, not because the established test/audit process caught it. Worth treating any future
diagnostic-hook surprise as a signal to re-check the audit methodology itself, not just the one file
it happened to be pointing at.

## Batch 35 (Iam) — routine notes

- All 4 Iam entities (Modules, Permission, Process, Role) share the same 2 permissions
  (`IAM_RBAC_VIEW`/`IAM_RBAC_MANAGE`), consistent with the pre-existing shared-permission pattern
  already used by Segment/SubSegment (`VEH_SEG_*`) and KeyValue/KeywordMaster/SystemSetting
  (`UTL_SETTINGS_*`) — not split into 8 more-granular per-entity permissions, to avoid silently
  changing effective access control for existing role assignments.
- `RoleCrudController` was previously flagged as fully broken for everyone (BUG-013 — `Role` model
  missing `CrudTrait`) — this remains open and unrelated to the URL/permission migration; the
  migration only changes routes/permissions/gating, it does not fix BUG-013.
- No new dead-route or missing-method issues found in this batch beyond BUG-066.

## Pricing module — routes conformed, but zero permission enforcement existed (BUG-068)

Checked Pricing's actual shape before assuming it needed a full route/namespace migration, per the
standing note in `.ai/rules/module-structure.md`'s module table ("pre-existing, not part of this
rollout"). Confirmed its routes (`admin/pricing/{process}/*`), route names
(`pricing.{process}.{activity}`), and namespace (`Admin\Pricing\*`) already conform — no rename
needed. However, `grep -n "->can(" app/Http/Controllers/Admin/Pricing/*.php` returned **zero
matches across all 6 controllers** — this module had never received any per-resource permission
enforcement at all, unlike every `CrudController` covered by the original 58-controller rollout
(these are plain `Controller`s, which is likely why they were out of that rollout's stated scope).
One of the 6, `PricingResetController`, is a genuinely destructive single-action endpoint (deletes
vehicle variant/model rows, flushes session/profile/price-history/hold data) reachable via a plain
`GET ...?confirm=1` with no permission check of any kind before this fix — the most severe gap found
in this rollout since the original BUG-001 (the emergency `/admin` gate). Minted 10 `PRC_*`
permissions and gated all 6 controllers; full detail in `known-bugs-report.md` BUG-068 and
`ai-changelogs-21-09-2026.md`.

Also found, while in this module: BUG-067 (a stale, incomplete duplicate route file,
`pricing_routes.php` — harmless, same dedup behavior as BUG-036, documented not fixed) and BUG-069
(the module's one menu link, "Price List", targets a URL with no registered route — its gate
mechanism was fixed for consistency with the rest of the app, but the dead target itself is a
separate feature/UX decision left undone).

**Takeaway for the remaining Org batch**: don't assume "already namespaced correctly" implies
"already permission-gated" — worth an explicit `grep -n "->can("` check per controller before
concluding a module needs only a route rename, since Org's controllers were namespaced in early
batches (1-23) well before the per-batch permission-naming convention settled, and it's worth
re-confirming each one actually has *a* gate (of either naming convention), not just checking
whether it needs *renaming*.

## Remaining scope

Per `docs/refactor/TASK_STATE.md`, after batch 35 + BUG-066 + Pricing (BUG-068) closeout: only
**Org** module remains (13 entities — likely needs its own dedicated batch or a split given its
size). Org's controllers already have permission gates from earlier batches (1-23), predating the
`MODULE_PROCESS_ACTIVITY` convention decision — they use the old lowercase `resource.action`
naming, still functionally correct but inconsistent with every batch since 26. Whether to rename
Org's existing permissions to the new convention (a larger, higher-risk change touching 13
already-shipped, already-gated controllers) or leave them as-is and only apply the route/namespace
parts of the module-structure rule is an open question for the next batch to resolve — likely worth
raising explicitly rather than assuming either way.

## RBAC data population is now real, but the Role→Permission matrix needs owner review

Following the demo/roles + demo/users UI mockup, populated the underlying data for real (see
`ai-changelogs-21-09-2026.md` for full detail): Module/Process rows, `module_code`/`process_code`
on all 225 permissions, a one-time Designation→Role backfill for all existing users, and a starting
Role→Permission assignment for 63 of 75 real roles.

**The one piece that's a judgment call, not a mechanical backfill**: `RolePermissionSeeder`'s
role→permission assignments are inferred from job-title semantics (Manager vs. Executive vs.
Consultant seniority, department-keyword matching to modules) because no access matrix for these 75
roles has ever existed in this app. This is a reasonable, internally-consistent starting point, but
it is **not a business-verified policy** — the app owner should review it (via the Role UI, or the
demo/roles screen once it's wired to persist) before relying on it for real access control. Flagged
prominently in the seeder's own docblock too, not just here.

**36 users still have no role** after the backfill — their employee's `designation_code` (`GM`,
`MAN`, `CNS`, `SWD`, `API`, `TST`, `RTO`, `DSA`) has no matching row in `xlr8_admin_designation`.
These look like abbreviated/placeholder designation codes from an older data source that never got a
real Designation record created for them. Worth a follow-up: either create matching Designation rows
for these codes, or confirm these are genuinely legacy/test data that can be ignored.

## Remaining work toward the real demo/roles + demo/users backend

The UI mockup still doesn't persist anything — every "Save" button just logs the intended payload.
Now that the underlying data is real, the next step (not started) is wiring actual controllers/routes
to `syncPermissions()`/`syncRoles()` using the same payload shapes already demonstrated in the
mockup's console.log output, plus deciding how "user-level overrides" should actually be modeled and
persisted (a new pivot table? a JSON column on `users`? Spatie's own per-user direct permission grants
layered on top of the role, which Spatie already supports natively via `model_has_permissions`?) —
this last question is a real design decision for the next session, not yet resolved.

**Update, same day**: this got resolved and built while implementing the Users Listing + View pages.
"Added" overrides use Spatie's native `givePermissionTo()`/`model_has_permissions` directly — no new
schema needed. "Removed" overrides needed a new table (`xlr8_iam_user_permission_denials`) since
Spatie has no native concept of revoking one role-granted permission from a single user. See
`ai-changelogs-21-09-2026.md`'s "Users Listing + View pages" entry and `known-bugs-report.md` for the
Gate-ordering bug this surfaced (Spatie's own `Gate::before()` was winning the short-circuit race
against ours, silently making denials a no-op until fixed).

## Two more severe pre-existing bugs found building the Users feature

Both found while building the Employment History card and the user show page — see
`known-bugs-report.md` BUG-073/074 for full detail. In short: `EmployeeHistory` (a well-designed,
pre-existing but completely unused table/model from migration batch 22) forced `SoftDeletes` on a
table with no `deleted_at` column, fataling on any query — fixed by dropping `BaseModel` in favor of
plain `Model`. Separately, and much more significant: `Employee::getDesignationCodeAttribute()`/
`getDesignationNameAttribute()` had their entire intended fallback logic written as a literal
array-index STRING instead of real PHP (`$this->attributes['designation_code ?? $this->desig_code']`
— that whole expression is just one string key that never matches anything), so both accessors
always silently returned `null` via Eloquent, for every employee, everywhere in the app. The
practical impact was softened by `??` fallback chains at several call sites (e.g. `OrgService.php`)
that happened to fall through to the legacy `desig_code` column instead — but this was still
defeating the intended "designation_code is preferred" migration, silently, with no error anywhere.
Worth a proactive `grep -rn "designation_code\|designation_name"` sweep in a future session to check
whether any other call site assumed the old (broken) behavior and would need adjustment now that the
accessors actually work.

## `php artisan test` was unusable before today — surfaced a backlog of pre-existing failures (BUG-078)

`phpunit.xml` had 3 latent misconfigurations (`DB_DATABASE=xlrn` typo, no `APP_URL` override, a
placeholder `APP_KEY`) that made every feature test fail immediately on a DB-connection or
encrypter error, regardless of what it tested — fixed as part of adding `DesignationCrudTest`
(details in the changelog). This means the ~47-test suite had likely never run successfully in this
repo, so nothing in it has been a reliable regression signal. Once fixed, running the full suite for
the first time surfaced **32 pre-existing failures unrelated to this session's work**:

- `Tests\Unit\Services\ReportingServiceTest` / `PostServiceTest` — `BindingResolutionException:
  Target class [App\Services\IAM\ReportingService] does not exist`. `HRJourneyService` is bound as a
  singleton in `AppServiceProvider`, but `ReportingService`/`PostService` (also bound there) appear
  to reference a class that either moved, was renamed, or was never created — needs investigation,
  not attempted here (out of scope for the Org CRUD work).
- `Tests\Unit\Models\EmpPostAssignmentTest` / `PostModelTest` / `PostReportingTest` — mix of
  `QueryException` (likely missing/renamed tables) and `Error` (likely missing classes/methods),
  same family as above — these look like an abandoned or in-progress "Post" (position) subsystem
  that predates this rollout.
- `Tests\Unit\StandaloneUsersImportTest` — `FileNotFoundException: File storage/user_data.xlsx does
  not exist` — the test expects a fixture spreadsheet that isn't committed to the repo.
- `Tests\Unit\RBACPersonEmployeeUserTest` — 1 failing assertion, not yet triaged.

Logged as BUG-078 (the phpunit.xml misconfiguration itself, fixed) and BUG-080 (this backlog of
now-visible failures, still open) in `known-bugs-report.md`. None of these touch Designation/Org
CRUD code — flagging so a future session doesn't mistake them for regressions from this branch.

## `actingAs($user, 'backpack')` is unsafe for permission-gated route tests in this app (BUG-079)

Laravel's `actingAs($user, $guard)` test helper calls `Auth::shouldUse($guard)` internally, which
(via `AuthManager::setDefaultDriver()`) overwrites `config('auth.defaults.guard')` for the rest of
the test. Spatie Permission resolves a permission's guard from that same config value whenever none
is passed explicitly (`Spatie\Permission\Guard::getDefaultName()`), so after
`actingAs($user, 'backpack')`, any `$user->can('SOME_PERMISSION')` check looks for a permission with
`guard_name = 'backpack'` — but every permission in this app (233 of them) was seeded with
`guard_name = 'web'`. Result: a real, valid permission grant silently fails every check made through
the test client, producing false 403s that look like application bugs.

This is a testing-only artifact, not a production bug — Backpack ships a
`UseBackpackAuthGuardInsteadOfDefaultAuthGuard` middleware that does the same `setDefaultDriver()`
call for real requests, but it's commented out in `config/backpack/base.php`'s middleware list in
this app, so real admin requests never touch `auth.defaults.guard` and stay on `'web'`, matching how
every permission was seeded. Worth leaving that middleware commented out; enabling it would break
every existing `ORG_ENTITY_MANAGE`-style permission check app-wide.

**Fix used in `DesignationCrudTest`**: log in directly on the guard without switching the default —
`$this->app['auth']->guard('backpack')->setUser($user);` — instead of `actingAs($user, 'backpack')`.
Session persistence across the test client's simulated requests still works (confirmed via
`assertAuthenticatedAs`), so this is a safe drop-in replacement for any future test that needs a
`backpack`-guard user with real Spatie permissions. Worth adding as a `TestCase` trait/helper before
the next batch of Org CRUD tests (Department/Division/Vertical/Branch/Location) to avoid
re-discovering this per test file.
