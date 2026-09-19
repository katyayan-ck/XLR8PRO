# AI Findings — 20-09-2026

Continuation of the RBAC + FormRequest + directory-restructure rollout. See
`ai-findings-19-09-2026.md` for the original scope, the permission-taxonomy decision, and the
still-open `/admin/user` `hasAccessOrFail` bug (deferred at the user's direction, not forgotten).

---

## 13. `EmployeeCrudController` is fundamentally broken — references columns that don't exist

While testing batch 4 of the rollout (`ai-changelogs-20-09-2026.md`, 09:45 entry),
`GET /admin/employee` (with `employee.view` correctly granted) returned a `500`, not the expected
`200`. Root cause: `Illuminate\Database\QueryException: SQLSTATE[42S22]: Column not found: 1054
Unknown column 'person_id' in 'field list'`.

`EmployeeCrudController::index()` selects `person_id`, `designation_id`, `primary_branch_id`,
`primary_department_id` from `xlr8_admin_employee`. **None of these columns exist.** Confirmed
via `database-schema`: the real columns are `person_code`, `desig_code` (and a separate,
also-present `designation_code`), `primary_branch_code`, `primary_dept_code`,
`primary_div_code`, `primary_loc_code`, `vertical_code`, `segment_code`, `sub_segment_code`,
`reporting_manager_code` — this app's documented **code-based relations** convention
(`.ai/rules/database.md`), not integer foreign keys. `create()`, `store()`, `edit()`, and
`update()` all have the same problem — `store()`/`update()`'s validation rules require
`person_id`, `designation_id`, `primary_branch_id`, `primary_department_id` as
`exists:xlr8_admin_person,id` / `exists:xlr8_admin_designation,id` / etc., which will always fail
validation (or if bypassed, fail at the DB layer) since those columns don't exist on either side.

**This means the entire Employee admin screen (list, create, edit, update) has likely never
worked**, independent of anything touched in this session. This predates this rollout entirely —
the query was preserved verbatim during the directory move (see `ai-changelogs-20-09-2026.md`)
specifically so as not to blur a genuine pre-existing defect with an in-scope change.

**Not fixed here** — this needs its own investigation and fix, larger than "add permission
checks":
- Rewrite `index()`'s `select()` and the `$gridData->map()` closure to use the real code columns,
  presumably joining/eager-loading through the `Employee` model's actual relationship methods
  (`person()`, `designation()`, `primaryBranch()`, `primaryDepartment()` — need to check whether
  these relationship methods themselves already use the correct code-based foreign keys, in which
  case only the controller's raw `select()`/`validate()` field lists are stale, or whether the
  relationships are also broken).
  - `EmployeeCrudController@index` currently does `Employee::with(['person', 'designation',
    'primaryBranch', 'primaryDepartment'])` — if those relationship methods on the `Employee`
    model are correctly defined against code columns, `with()` would still work even though the
    raw `select([...])` column list is wrong (Eloquent would just throw when trying to select a
    nonexistent column, which is exactly the error seen) — meaning the fix may be as narrow as
    correcting the `select()` array and the `store()`/`update()` validation field names, without
    touching the relationships themselves. Not confirmed — needs a read of the `Employee` model.
- Rewrite `create()`/`store()`/`edit()`/`update()`'s validation and creation logic to accept and
  persist codes (`person_code`, `desig_code`, `primary_branch_code`, `primary_dept_code`) instead
  of ids, and update the corresponding Blade views
  (`admin.employee.create`/`edit`/`list`) if they reference `person_id`-style field names in
  `<select>` inputs — not checked in this session.

**Recommended priority:** similar tier to the `/admin/user` bug found on 19-09-2026 — both are
real, currently-broken admin screens discovered incidentally while wiring permissions, not
architectural gaps. Worth triaging both together rather than one at a time, since fixing either
may follow a similar "raw query vs. real schema" pattern investigation.

## 15. `BrandCrudController`'s backing table doesn't exist — a third independent, pre-existing broken admin screen

While testing batch 5 (`ai-changelogs-20-09-2026.md`, 10:30 entry), `GET /admin/brand` (with
`brand.view` correctly granted) returned `500`. Root cause:
`SQLSTATE[42S02]: Base table or view not found: 1146 Table 'xlrm.xlr8_vehicle_brand' doesn't
exist`. Unlike the `/admin/user` (finding #11) and `/admin/employee` (finding #13) cases — which
are wrong-column-name bugs against tables that do exist — here the entire table is absent from
the database. `xlr8_vehicle_variant`/`xlr8_vehicle_model`/etc. all carry a `brand_code` column,
but there appears to be no dedicated `brand` master table backing it at all in this environment.
**Not fixed** — same reasoning as #13: this needs its own investigation (was the table dropped,
renamed, or never migrated to this environment? is `brand_code` meant to be a free-text/lookup
value with no master table, in which case the entire `BrandCrudController` screen may be
obsolete rather than broken?) before anyone touches it. Preserved the controller's queries
exactly as they were.

## 16. Pattern check: three independent pre-existing broken admin screens found so far while wiring permissions

| Screen | Found | Root cause |
|---|---|---|
| `/admin/user` | 19-09-2026, finding #11 | Backpack's own `hasAccessOrFail('list')` throws unconditionally, before reaching app code |
| `/admin/employee` | 20-09-2026, finding #13 | Query/validation reference `person_id`/`designation_id`/etc. — columns that don't exist (schema uses code-based columns instead) |
| `/admin/brand` | 20-09-2026, finding #15 | The entire `xlr8_vehicle_brand` table doesn't exist |

Three different root causes, no shared mechanism — this doesn't look like one bug wearing three
disguises, more like several controllers in this codebase were written against an earlier or
assumed schema/Backpack-config shape that has since drifted, and nothing has exercised these
particular screens since. **Worth considering:** a fast, cheap triage pass — hit every
`GET /admin/{resource}` index route once (with a permissive test user) and record which ones
500 vs. render — would surface the full scope of this pattern in minutes, rather than discovering
one broken screen at a time as the rollout happens to reach it. Not done in this session (would
be a detour from the batch-by-batch rollout the user asked to continue), but flagged as a
cheap, high-value next step if/when the rollout pauses.

## 17. Rollout progress tracker (cumulative, both days)

| # | Controller | Status |
|---|---|---|
| 1 | `BranchCrudController` | ✅ Done, fully tested (19-09-2026) |
| 2 | `DepartmentCrudController` | ✅ Done, fully tested (19-09-2026) |
| 3 | `DesignationCrudController` | ✅ Done, fully tested (19-09-2026) |
| 4 | `DivisionCrudController` | ✅ Done, fully tested (20-09-2026) |
| 5 | `LocationCrudController` | ✅ Done, fully tested (20-09-2026) |
| 6 | `EmployeeCrudController` | ⚠️ Permission/FormRequest wiring done and tested (403 path); underlying CRUD functionality broken independent of this rollout — see finding #13 |
| 7 | `PersonCrudController` | ✅ Done, fully tested (20-09-2026) |
| 8 | `BrandCrudController` | ⚠️ Permission/FormRequest wiring done and tested (403/200 on create-page path); underlying table missing — see finding #15 |
| 9 | `ColorCrudController` | ✅ Done, fully tested (20-09-2026) |
| 10 | `SegmentCrudController` | ✅ Done, fully tested (20-09-2026) — includes a real, reachable bulk-import method now gated |
| 11 | `SubSegmentCrudController` | ✅ Done, fully tested (20-09-2026) — reuses `segment.*` permissions (no dedicated permission exists) |
| 12 | `VehicleModelCrudController` | ✅ Done, fully tested (20-09-2026) — unique no-traits/manual-routes shape; dead `destroy` route found, not fixed |
| 13 | `VariantCrudController` | ✅ Done, fully tested (20-09-2026) |
| 14 | `RoleCrudController` | ⚠️ Permission/FormRequest wiring done, correct by inspection; cannot be end-to-end verified — model missing `CrudTrait`, screen 500s for everyone regardless of permission — see finding #20 |
| 15 | `SystemSettingCrudController` | ⚠️ Permission wiring done and partially tested (403 confirmed); positive path 500s — screen uses a filter requiring uninstalled `backpack/pro` — see finding #21 |
| 16 | `PermissionCrudController` | ✅ Done, fully tested (20-09-2026) |
| 17 | `ModulesCrudController` | ✅ Done, fully tested (20-09-2026) — reuses `rbac.*` permissions |
| 18 | `ProcessCrudController` | ✅ Done, fully tested (20-09-2026) — reuses `rbac.*` permissions |
| 19 | `VerticalCrudController` | ✅ Done, fully tested (20-09-2026) — reuses `division.*` permissions |
| 20 | `PersonAddressCrudController` | ⚠️ Permission/FormRequest wiring done, correct by inspection; cannot be end-to-end verified — screen 500s regardless of permission, wrong column names throughout — see `known-bugs-report.md` BUG-020 |
| 21 | `PersonContactCrudController` | ✅ Done, fully tested (20-09-2026) — reuses `person.*` permissions |
| 22 | `PersonBankingDetailCrudController` | ⚠️ Permission/FormRequest wiring done, correct by inspection; cannot be end-to-end verified — screen 500s regardless of permission (missing `CrudTrait` + wrong column names, two stacked bugs) — see `known-bugs-report.md` BUG-021 |
| 23–58 | Remaining ~36 controllers | Not started |
| — | `PostCrudController`, `UserTypeCrudController` | Skipped as batch candidates — both completely unreachable, no route anywhere points to either (finding #22) |

Plus the still-open `/admin/user` `hasAccessOrFail` bug (19-09-2026, finding #11) — deferred at
the user's direction, continuing the rollout first.

## 18. Two more minor pre-existing issues found during batch 6 (neither fixed)

- **Dead route reference**: `routes/backpack/core.php` registers `Route::get(
  'sub-segment/segments/{brandCode}', [SubSegmentCrudController::class, 'getSegmentsByBrand'])`,
  but `SubSegmentCrudController` has never defined a `getSegmentsByBrand()` method — confirmed in
  the file as it existed before this session touched it. Hitting this route would throw "method
  does not exist." Low severity (looks like an abandoned AJAX helper, probably superseded by
  `getSubSegmentsBySegment` which does exist and is wired), but worth a cleanup pass — either
  implement the method or remove the dead route.
- **Silently-dropped field**: `SubSegment`'s `$fillable` (`app/Models/Vehicle/SubSegment.php`)
  omits `name`, but `SubSegmentCrudController::update()` (both before and after this session's
  changes — preserved exactly) validates and mass-assigns a `name` field. Since Eloquent mass
  assignment silently ignores non-fillable keys rather than throwing, **editing a Sub Segment's
  name through the admin screen has likely never actually persisted that change** — no error, no
  visible sign anything is wrong, just a silent no-op. Discovered incidentally while building a
  test fixture directly against the model (a raw `SubSegment::create(['name' => ...])` failed
  outright with a DB-level "no default value" error, revealing that the column has no default and
  the fillable list quietly drops it). This is the kind of bug that's easy to miss forever since
  the UI shows no error — worth checking whether other models in this codebase have similar
  fillable/validation-field mismatches, since this is now the second such report today (see
  finding #13, `EmployeeCrudController`, for a related-but-different fillable/schema mismatch
  category).

## 19. A third dead route found during batch 7 — `VehicleModelCrudController::destroy()` doesn't exist

`routes/backpack/core.php` registers `Route::delete('vehicle-model/{id}', [VehicleModelCrudController::class,
'destroy'])->name('vehicle-model.destroy')`, but this controller — which uniquely uses no
Backpack Operation traits and is wired via fully manual routes — has never defined a `destroy()`
method. Same category as the `SubSegmentCrudController::getSegmentsByBrand` dead route (finding
#18) and worth fixing in the same pass, whenever that happens: either implement `destroy()`
(trivial — `return $this->crud->delete($id);`, same one-liner used everywhere else in this
rollout) or remove the dead route. Not done here since it's new functionality, not a permission
fix. Three dead-route findings so far (this one, #18's `getSegmentsByBrand`, and by implication
any other manually-wired controller not yet reached) suggest checking `php artisan route:list`
against each controller's actual method list might be a worthwhile quick audit on its own.

## 20. `RoleCrudController` — completely broken for everyone, `Role` model missing `CrudTrait`

While testing batch 8, `GET /admin/role` returned `500` **even with no permission at all** —
meaning the crash happens before my permission check in `setupListOperation()` is ever reached.
Debugged with `app.debug` on: `Exception: Please use CrudTrait on the model.` —
`App\Models\IAM\Role` does not use Backpack's `Backpack\CRUD\app\Models\Traits\CrudTrait`, which
`CRUD::setModel()` requires and checks for internally, inside `setup()`. Since `setup()` runs for
every single request to this controller regardless of operation, this means **the entire Role
management screen — list, create, edit, delete — has likely never worked, for anyone, at any
permission level.** This is the actual RBAC role-management UI for the whole system this
rollout has been building permissions around, which makes it a notable one to prioritize whenever
pre-existing bugs get triaged. Not fixed here — adding a trait to a model is outside "wire
permissions onto an existing screen" scope, and worth understanding why it's missing (oversight,
or is `Role` deliberately not meant to be Backpack-CRUD-managed and this route/controller is
leftover/abandoned, like `PostCrudController`/`UserTypeCrudController` in finding #22?) before
just adding the trait.

## 21. `SystemSettingCrudController` — list view 500s, uses a Backpack PRO-only filter that isn't installed

Batch 8 also found: `GET /admin/system-settings` correctly returned `403` without `settings.view`
(the permission check added this session does work here), but granting `settings.view` and
retrying gave `500`. Debugged: `HttpException: Filter is a Backpack PRO feature. Please purchase
and install the Backpack\PRO addon from backpackforlaravel.com`. This app does not have
`backpack/pro` installed (confirmed earlier in this engagement — absent from `composer.json`),
yet `SystemSettingCrudController::setupListOperation()` calls `$this->crud->addFilter(...)`, which
is PRO-only. This means the System Settings list screen has likely never rendered successfully
either. Not fixed — the resolution here is a real decision (remove the filter, since there's no
free equivalent per `CLAUDE.md`'s own paid-feature guidance, or purchase/install `backpack/pro`),
not something to guess at silently.

## 22. `PostCrudController` and `UserTypeCrudController` are completely unreachable — no route anywhere

While looking for controllers matching the remaining unwired permissions (`post.*`, `user_type.*`),
found that neither `PostCrudController` nor `UserTypeCrudController` has a single route pointing
to it anywhere in `routes/backpack/*.php` (or any other route file) — confirmed via a repo-wide
grep for both class names. `PostCrudController` is a substantial, real-looking controller (org
scopes, vehicle scopes, a dedicated `PostService`, already calls `$this->crud->hasAccessOrFail(...)`
itself) that appears to be either mid-development and never wired up, or deliberately shelved.
Skipped both as batch candidates this session — wiring permissions onto genuinely unreachable code
has no way to be tested the way the rest of this rollout has been, and would just be guessing.
Worth a decision on whether these are meant to be finished and wired up, or removed.

## 23. Session-wide summary: 9 independent pre-existing bugs found while wiring permissions onto 15 controllers

For visibility, since these are scattered across two days of changelog entries:

| # | Screen/Method | Root cause | Found in batch |
|---|---|---|---|
| 1 | `/admin/user` | Backpack's own `hasAccessOrFail('list')` throws unconditionally | 19-09, standalone |
| 2 | `EmployeeCrudController` | Query/validation reference columns that don't exist (`person_id` etc. vs. real code-based columns) | Batch 4 |
| 3 | `BrandCrudController` | `xlr8_vehicle_brand` table doesn't exist at all | Batch 5 |
| 4 | `SubSegmentCrudController` → `getSegmentsByBrand` | Route registered, method never defined | Batch 6 |
| 5 | `SubSegment` model | `name` validated/submitted but not in `$fillable` — silently never saved | Batch 6 |
| 6 | `VehicleModelCrudController` → `destroy()` | Route registered, method never defined | Batch 7 |
| 7 | `RoleCrudController` | `Role` model missing Backpack's `CrudTrait` — whole screen 500s | Batch 8 |
| 8 | `SystemSettingCrudController` | Uses a filter requiring `backpack/pro`, which isn't installed | Batch 8 |
| 9 | `PostCrudController`, `UserTypeCrudController` | Both entirely unreachable — no route anywhere | Batch 8 |

None of these were introduced by this session — all were pre-existing and surfaced incidentally
while adding permission checks and testing each controller end-to-end. None were fixed, per the
consistent approach of flagging rather than silently repairing unrelated defects. Given the
density of findings (9 issues across 15 controllers, roughly 60%), the suggestion from finding
#16 (a fast triage sweep hitting every `GET /admin/{resource}` index route once, to see which of
the remaining ~43 controllers are already broken before investing per-controller effort) seems
increasingly worth doing rather than continuing to discover these one at a time.

---

## 24. MASTER BUG LIST — paused controller rollout at user's request to review everything found so far

User asked to pause controller-rollout work and consolidate every independent bug found (fixed or
still open) across both days, with a proposed solution for each. This section is that
consolidation. Nothing below was newly investigated for this entry — it's a reorganization of
findings already logged in `ai-changelogs-19-09-2026.md`, `ai-changelogs-20-09-2026.md`,
`ai-findings-19-09-2026.md`, and this file, plus `claude-first-inspection.md`/`claude-findings.md`
from the sessions before the rollout began. See those files for full evidence and test
transcripts; this is the decision-oriented summary.

Controller rollout is paused. Will resume when the user says "Resume on controllers."
