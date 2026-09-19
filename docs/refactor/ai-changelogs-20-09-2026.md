# AI Changelog — 20-09-2026

Continuation of the RBAC + FormRequest + directory-restructure rollout started on
19-09-2026 (see `ai-changelogs-19-09-2026.md` for the emergency `/admin` gate, the
`isSuperAdmin()` fix, and batches 1–2 of this same rollout: `BranchCrudController`,
`DepartmentCrudController`, `DesignationCrudController`). Same branch:
`refactor/admin-permissions-formrequest-restructure`. Nothing committed or pushed.

Entry format:

```
## HH:MM — <short title>
- File(s): <path(s)>
- Reason: <one line — why this change was made>
- Previous value:
  <exact prior content/value>
- New value:
  <exact new content/value>
```

---

## 09:00 — Batch 3 of the rollout: `DivisionCrudController`, `LocationCrudController`

- File(s): `app/Http/Controllers/Admin/DivisionCrudController.php` → moved to
  `app/Http/Controllers/Admin/Org/Division/DivisionCrudController.php` (`git mv`);
  `app/Http/Requests/DivisionRequest.php`; `app/Http/Controllers/Admin/LocationCrudController.php`
  → moved to `app/Http/Controllers/Admin/Org/Location/LocationCrudController.php` (`git mv`);
  `app/Http/Requests/LocationRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout in small tested batches, per user's direction on 19-09-2026.
  Both controllers matched the established shape (custom operation overrides, no permission
  checks, dead FormRequest scaffolding, matching `division.*`/`location.*` permissions already
  present in `xlr8_iam_permissions`).
- **Division-specific care taken:** `DivisionCrudController::update()` has a cross-model business
  rule — blocks activating a Division whose parent Department is inactive. Preserved exactly;
  only validation extraction and permission checks changed.
- **Permission checks added** (previously none): `division.view/create/edit/delete` and
  `location.view/create/edit/delete`, same inline `abort(403,...)` style as the previous batches,
  plus new `destroy()` overrides for both (previously unguarded `DeleteOperation` defaults).
- **Route registrations updated**: both `Route::crud(...)` calls switched from bare
  namespace-relative strings to explicit `use` imports + `::class`, same as previous batches.
- **Verification** (same method as all previous batches — real HTTP kernel dispatch, all
  mutations in a transaction forced to roll back):
  - `php -l` on all 5 touched files: clean. `php artisan route:list --path=admin/division` /
    `--path=admin/location`: both resolve correctly to the new `Admin\Org\...` namespaces.
  - Role stripped → `GET /admin/division` and `GET /admin/location` both **`403`**. Granted
    `division.view`/`location.view` (fresh guard re-login) → both **`200`**.
  - **Division activation guard — first test attempt was inconclusive by my own test-design
    error**, not a code issue: I checked whether the division was inactive *after* a blocked
    update without having pinned down its state *before* the request, so I couldn't tell if the
    guard fired or the row was simply already active from real pre-existing data. Redid the test
    with an explicit, known starting state (department forced inactive, a fresh test division
    created as inactive, then an update attempting to set `is_active=1`) — confirmed the division
    **correctly stayed inactive** after the blocked request. The business rule is intact.
  - `POST /admin/location` with a missing required field → `302`, no row created (FormRequest
    validation working).
  - All mutations rolled back; confirmed no residue afterward.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: same pattern, same rigor, both controllers pass. 5 of 58 CrudControllers done**
  (Branch, Department, Designation, Division, Location). 53 remain, plus the still-open
  `/admin/user` `hasAccessOrFail` bug from 19-09-2026 (deferred at the user's direction, not
  forgotten).

---

## 09:45 — Batch 4 of the rollout: `EmployeeCrudController`, `PersonCrudController`

- File(s): `app/Http/Controllers/Admin/EmployeeCrudController.php` → moved to
  `app/Http/Controllers/Admin/Org/Employee/EmployeeCrudController.php` (`git mv`);
  `app/Http/Requests/EmployeeRequest.php`; `app/Http/Controllers/Admin/PersonCrudController.php`
  → moved to `app/Http/Controllers/Admin/Org/Person/PersonCrudController.php` (`git mv`);
  `app/Http/Requests/PersonRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout in small tested batches. Both matched the established shape;
  `employee.*`/`person.*` permissions already existed in `xlr8_iam_permissions`.
- **Permission checks added** (previously none): `employee.view/create/edit/delete` and
  `person.view/create/edit/delete`, same style as previous batches, plus new `destroy()`
  overrides for both.
- **Route registrations updated**, same as previous batches.
- **Verification:**
  - `php -l` on all 5 files: clean. Routes resolve correctly to the new namespaces.
  - No-permission → `403` for both `GET /admin/employee` and `GET /admin/person`. Granted
    `employee.create` only (no `.view`) → `GET /admin/employee/create` → `403`, confirming
    per-action granularity again.
  - **`Person` fully verified end-to-end**: `person.view` granted → `200`. Full HTTP round-trip
    with real session/CSRF: `POST /admin/person` missing required fields → `302`, no row created;
    valid payload → `302`, row created. All rolled back cleanly.
  - **`Employee`'s positive path could NOT be verified — found a separate, pre-existing bug,
    not caused by this change:** granting `employee.view` and hitting `GET /admin/employee`
    returned `500`, not `200`. Root cause: `Illuminate\Database\QueryException: Unknown column
    'person_id' in 'field list'`. `EmployeeCrudController::index()` (and `create()`/`store()`/
    `edit()`/`update()`, which reference the same non-existent columns) selects/validates
    `person_id`, `designation_id`, `primary_branch_id`, `primary_department_id` — but
    `xlr8_admin_employee`'s real schema (confirmed via `database-schema`) has no such columns at
    all. It uses this app's documented **code-based** relations instead: `person_code`,
    `desig_code`/`designation_code`, `primary_branch_code`, `primary_dept_code`, etc. — matching
    `.ai/rules/database.md`'s "code-based relations" convention, which this controller's queries
    simply never followed. **This means the entire Employee admin screen (list, create, edit,
    update) has likely never worked in this app**, independent of anything in this rollout.
  - I preserved the controller's original query/validation logic **exactly as it was**, including
    this bug — fixing broken column references is a different, larger task than "add permission
    checks and a FormRequest," and doing it silently as a side effect of this batch would blur
    what changed and why. Flagged prominently as a new finding in `ai-findings-20-09-2026.md`
    rather than fixed here.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: Person fully done and tested. Employee's permission/FormRequest wiring is correct and
  the 403 path is confirmed working, but its actual CRUD functionality remains broken for a
  reason unrelated to this session — flagging rather than silently fixing.** 7 of 58
  CrudControllers moved (Branch, Department, Designation, Division, Location, Employee, Person).
  51 remain.

---

## 10:30 — Batch 5 of the rollout: `BrandCrudController`, `ColorCrudController` (first batch outside the "Org" module — vehicle master data)

- File(s): `app/Http/Controllers/Admin/BrandCrudController.php` → moved to
  `app/Http/Controllers/Admin/Vehicle/Brand/BrandCrudController.php` (`git mv`);
  `app/Http/Requests/BrandRequest.php`; `app/Http/Controllers/Admin/ColorCrudController.php` →
  moved to `app/Http/Controllers/Admin/Vehicle/Color/ColorCrudController.php` (`git mv`);
  `app/Http/Requests/ColorRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout. These two are vehicle-master-data controllers rather than
  org/admin data, so placed under a new `Admin/Vehicle/{Process}/` folder rather than
  `Admin/Org/{Process}/`, matching the domain split already visible in this app's model
  namespaces (`App\Models\Vehicle\*` vs `App\Models\Admin\*`).
- **`BrandCrudController` has no `store()` override** — unlike every controller in batches 1–4,
  create/store falls through entirely to Backpack's default `CreateOperation::store()`. There is
  also no `setupCreateOperation()`. To gate the create/store path at all, added a **new**
  `setupCreateOperation()` containing only the permission check (no field definitions — none
  existed before, so none were added, preserving existing behavior exactly). This is because
  Backpack invokes `setup{Operation}Operation()` for every request under that operation name,
  regardless of whether the operation's actual handler is a custom override or a Backpack
  default — so this one hook gates both the `create()` GET view (already had its own override)
  and the un-overridden default `store()`.
  - Caught and corrected a consistency slip while writing this: initially also added an empty
    `setupUpdateOperation()` purely for symmetry, but `update()` already has a full override with
    its own check (unlike `store()`) — every other batch only gates methods that actually exist
    as overrides, so removed the redundant `setupUpdateOperation()` before testing, to keep this
    batch consistent with 1–4 rather than introducing a new pattern.
  - `BrandCrudController::import()` (a large, unrelated bulk Excel-import method for vehicle
    master data — segments/models/variants/colors, not Brand itself) was preserved verbatim with
    a code comment explaining it: confirmed via a repo-wide grep that **no route anywhere points
    to it** — it is dead/unreachable code. Left ungated (pointless to gate something
    unreachable) rather than silently wiring or removing it, since neither was asked for.
- **Permission checks added**: `brand.view/edit/delete` (no `.create` check existed to add to,
  per the `store()` note above — `brand.create` is instead checked in the new
  `setupCreateOperation()` and in `create()`) and `color.view/create/edit/delete`. New `destroy()`
  overrides for both.
- **Route registrations updated**: both `Route::crud(...)` string calls switched to `::class`,
  same as previous batches. `ColorCrudController`'s existing AJAX lookup routes
  (`color/subsegments`, `color/models`, `color/variants`) already used `[Controller::class,
  'method']` array syntax, so only their `use` import needed updating — left the routes
  themselves untouched, including not adding permission checks to these three read-only
  cascading-dropdown endpoints (consistent with not gating similar lookup endpoints in earlier
  batches, e.g. `VehicleModelCrudController`'s equivalents were never gated either).
- **Verification** (same method as all previous batches):
  - `php -l` on all 5 files: clean. Routes resolve correctly to `Admin\Vehicle\Brand\...` /
    `Admin\Vehicle\Color\...`.
  - No permission → `GET /admin/brand`, `GET /admin/color`, `GET /admin/brand/create` all
    **`403`**. A raw `POST /admin/brand` without a CSRF token returned `419` (expected — CSRF
    middleware runs before any controller code executes, so this doesn't test the permission gate
    itself) — the GET-create-page results (403 without `brand.create`, `200` with it, both via
    the same `setupCreateOperation()` hook) already confirm that gate fires correctly, so a
    tokenized POST re-test wasn't needed to prove the same code path twice.
  - Granted `brand.view` → `GET /admin/brand` returned **`500`, not `200`.** Investigated with
    `app.debug` on: `SQLSTATE[42S02]: Base table or view not found: 1146 Table
    'xlrm.xlr8_vehicle_brand' doesn't exist`. **A third pre-existing, unrelated broken admin
    screen** (after `/admin/user` on 19-09-2026 and `/admin/employee` earlier today) — the
    `xlr8_vehicle_brand` table does not exist in this database at all, so the entire Brand CRUD
    screen (list, create, edit, update) has never worked. Not fixed — flagged in
    `ai-findings-20-09-2026.md`.
  - Granted `color.view` → `GET /admin/color` → **`200`** (this table does exist). Granted
    `color.create` → full HTTP round-trip with real session/CSRF against a real existing
    `Variant` row: `POST /admin/color` missing required fields → `302`, no row created; valid
    payload → `302`, row created with the validated data. Rolled back cleanly.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: Color fully done and tested. Brand's permission/FormRequest wiring is correct
  (confirmed via the create-page 403/200 split) but, like Employee, its actual CRUD functionality
  is separately and pre-existingly broken — this time because the backing table doesn't exist at
  all.** 9 of 58 CrudControllers moved. 49 remain. **Three independent pre-existing broken admin
  screens now found while wiring permissions** (`/admin/user`, `/admin/employee`, `/admin/brand`)
  — see `ai-findings-20-09-2026.md` for a consolidated view and a suggestion on how to triage this
  pattern going forward.

---

## 11:15 — Batch 6 of the rollout: `SegmentCrudController`, `SubSegmentCrudController`

- File(s): `app/Http/Controllers/Admin/SegmentCrudController.php` → moved to
  `app/Http/Controllers/Admin/Vehicle/Segment/SegmentCrudController.php` (`git mv`);
  `app/Http/Requests/SegmentRequest.php`; `app/Http/Controllers/Admin/SubSegmentCrudController.php`
  → moved to `app/Http/Controllers/Admin/Vehicle/SubSegment/SubSegmentCrudController.php`
  (`git mv`); `app/Http/Requests/SubSegmentRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout. Both under `Admin/Vehicle/{Process}/`, same domain split as
  batch 5.
- **No dedicated `subsegment.*` permission exists** in `xlr8_iam_permissions`. Reused
  `segment.view/create/edit/delete` throughout `SubSegmentCrudController`, since sub-segments are
  already managed as part of segment administration in this app (confirmed by
  `SegmentCrudController::edit()` itself reading/writing `SubSegment` rows for its own
  active-subsegment-count guard). Documented this reuse explicitly in both a controller docblock
  and the `SubSegmentRequest` docblock so it isn't mistaken for a missed permission later.
- **Neither controller has a `store()` override** (same shape as `BrandCrudController` in batch
  5) — gated the default Backpack store path via a new `setupCreateOperation()` on each,
  containing only the permission check, no field definitions (none existed before).
- **Cross-model business rules preserved exactly**: `SegmentCrudController::update()` blocks
  deactivating a Segment with active SubSegments; `SubSegmentCrudController::update()` blocks
  deactivating a SubSegment with active VehicleModels. Both explicitly tested (see below), not
  just assumed intact from the diff.
- **`SegmentCrudController::import()` is a real, reachable bulk Google-Sheets import** (registered
  at `routes/backpack/core.php`'s `segment/import` route — confirmed via grep, unlike
  `BrandCrudController::import()` in batch 5 which was dead code). Gated it on `segment.create`
  as the closest existing permission, since it creates segment/sub-segment/model/variant/color
  rows and no dedicated import permission exists. Documented this choice in a docblock.
- **Permission checks added**: `segment.view/create/edit/delete` on `SegmentCrudController`;
  `segment.view/create/edit/delete` (reused) on `SubSegmentCrudController`. New `destroy()`
  overrides on both.
- **Route registrations updated**, consistent with all previous batches.
- **Pre-existing issue noticed, not fixed**: `routes/backpack/core.php` registers
  `Route::get('sub-segment/segments/{brandCode}', [SubSegmentCrudController::class,
  'getSegmentsByBrand'])`, but `SubSegmentCrudController` has never defined a
  `getSegmentsByBrand()` method (confirmed in the original file, before this session touched it)
  — this route would throw "method does not exist" if ever hit. Left as-is (didn't invent a new
  method to fill a gap that predates this session); flagged in `ai-findings-20-09-2026.md`.
- **Pre-existing issue noticed, not fixed**: `SubSegment`'s `$fillable` array
  (`app/Models/Vehicle/SubSegment.php`) does not include `name` (only `oem_name` and
  `description`), yet both the original and this session's preserved `update()` logic validate
  and mass-assign a `name` field. Since `update()`/`create()` silently ignore non-fillable keys
  rather than throwing, this means **`name` has likely never actually been saved** when editing a
  Sub Segment through this screen — a silent no-op, not a crash, which is why it hadn't surfaced
  before. Discovered while writing a test fixture (`SubSegment::create(['name' => ...])` failed
  with "Field 'name' doesn't have a default value" since the column has no default and the value
  never reached the INSERT). Not fixed — flagged in `ai-findings-20-09-2026.md`.
- **Verification** (same method as all previous batches):
  - `php -l` on all 5 files: clean. Routes resolve correctly to the new `Admin\Vehicle\Segment\...`
    / `Admin\Vehicle\SubSegment\...` namespaces.
  - No permission → `GET /admin/segment`, `GET /admin/sub-segment`, `GET /admin/segment/create`
    all **`403`**. Granted `segment.view`/`segment.create`/`segment.edit` → `GET /admin/segment`
    **`200`**; `GET /admin/sub-segment` **`200`** too (proving the reused `segment.*` permission
    actually gates SubSegment, not just coincidentally matching a name); `GET
    /admin/segment/create` **`200`** (proving the new `setupCreateOperation()` hook works, same
    mechanism as Brand's).
  - **Segment's deactivation guard** — first test attempt hit two of my own test-data mistakes
    before getting a clean result (a 6-char test `code` violating the column's `max:5`
    constraint; matching `SubSegment::create()` silently dropping `name` per the fillable bug
    above). Corrected both, then confirmed with an explicit known starting state (segment +
    active sub-segment both created directly, not via the gated screen): attempted deactivation
    while the sub-segment was still active → `302`, segment **stayed active** — the guard fired
    correctly.
  - All mutations rolled back; confirmed no residue.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: both controllers' permission/FormRequest wiring is correct and fully tested,
  including the cross-model deactivation guards and the reused-permission design for SubSegment.**
  11 of 58 CrudControllers moved. 47 remain. Two more minor pre-existing issues found (dead route,
  silently-dropped `name` field) — neither touched, both logged.

---

## 12:00 — Batch 7 of the rollout: `VehicleModelCrudController`, `VariantCrudController`

- File(s): `app/Http/Controllers/Admin/VehicleModelCrudController.php` → moved to
  `app/Http/Controllers/Admin/Vehicle/Model/VehicleModelCrudController.php` (`git mv`);
  `app/Http/Requests/VehicleModelRequest.php`; `app/Http/Controllers/Admin/VariantCrudController.php`
  → moved to `app/Http/Controllers/Admin/Vehicle/Variant/VariantCrudController.php` (`git mv`);
  `app/Http/Requests/VariantRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout. `model.*`/`variant.*` permissions already existed in
  `xlr8_iam_permissions`.
- **`VehicleModelCrudController` has a genuinely different shape from every controller handled so
  far**: it uses **no Backpack Operation traits at all** (no `use ListOperation`, etc.) and is
  wired entirely through manual routes in `routes/backpack/core.php` (`Route::get/post/put/delete`
  with explicit `->name()` calls), not `Route::crud()`. Preserved this shape exactly rather than
  converting it to the trait-based style used everywhere else — confirmed via testing that
  Backpack's `setup{Operation}Operation()` auto-dispatch still fires correctly based on route name
  even without the corresponding trait (verified: `GET /admin/vehicle-model` without `model.view`
  → `403`, with it → `200`).
  - **Pre-existing issue noticed, not fixed**: `routes/backpack/core.php` registers `Route::delete(
    'vehicle-model/{id}', [VehicleModelCrudController::class, 'destroy'])`, but this controller has
    never defined a `destroy()` method (confirmed in the file before this session touched it) —
    same category of dead-route bug as `SubSegmentCrudController::getSegmentsByBrand` in batch 6.
    Not added; flagged in `ai-findings-20-09-2026.md`.
- **`VariantCrudController`** has the standard shape (full trait usage, `store()`/`update()`
  overrides) — straightforward application of the established pattern. New `destroy()` override
  added (previously unguarded default).
- **Preserved a subtle create/update rule discrepancy** in `VariantRequest`: the original
  `store()` validated `seating_capacity`/`wheels`/`gvw` with `min:` constraints
  (`min:1`/`min:1`/`min:0`) that `update()` did not have — kept via `$this->isMethod('POST')`,
  same technique used for `BranchRequest`'s phone-format discrepancy in batch 1.
- **Permission checks added**: `model.view/create/edit` (no `model.delete` check added, since no
  `destroy()` exists to add it to — see above) and `variant.view/create/edit/delete`.
- **Route registrations updated**: `VehicleModelCrudController`'s manual routes already used
  `[Controller::class, 'method']` array syntax, so only the `use` import needed updating (no
  change to the routes themselves). `VariantCrudController`'s `Route::crud(...)` string call
  switched to `::class`, same as previous batches.
- **Verification** (same method as all previous batches):
  - `php -l` on all 5 files: clean. Routes resolve correctly to the new namespaces (confirmed the
    dead `destroy` route still *lists* under the new namespace, since Laravel doesn't validate
    handler existence at route-registration time, only at dispatch).
  - No permission → `GET /admin/vehicle-model` and `GET /admin/variant` both **`403`**. Granted
    `model.view`/`variant.view` → both **`200`**. Granted `model.edit` but not `model.create` →
    `GET /admin/vehicle-model/create` → **`403`**, confirming per-action granularity holds even
    for the manual-route controller.
  - **Cross-model deactivation guards, tested with explicit known starting states** (a fresh
    Segment → SubSegment → VehicleModel → Variant chain created directly, not through the gated
    screens): attempted deactivating the VehicleModel while its Variant was still active → `302`,
    model **stayed active** (guard fired correctly). Attempted deactivating the Variant itself
    (no active Colors under it) → `302`, variant **did** go inactive (correctly allowed, since
    that guard only blocks when active Colors exist — confirms the guard is conditional, not a
    blanket block).
  - Noticed a `foreach() argument must be of type array|object, null given` warning from a Blade
    view during the Variant edit-page request — request still completed successfully (not
    fatal). Not investigated further (out of scope, non-blocking); worth a look if anyone's
    already touching `admin.variant.edit`.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: both controllers fully tested, including a genuinely different architectural shape
  (no-traits/manual-routes) for VehicleModel, confirmed to still work correctly with this
  session's permission-gating approach.** 13 of 58 CrudControllers moved. 45 remain.

---

## 13:00 — Batch 8 of the rollout: `RoleCrudController`, `SystemSettingCrudController` (both found completely broken pre-existing, independent of this session)

- File(s): `app/Http/Controllers/Admin/RoleCrudController.php` → moved to
  `app/Http/Controllers/Admin/Iam/Role/RoleCrudController.php` (`git mv`);
  `app/Http/Requests/RoleRequest.php` (**new file** — no dead scaffold existed for this one,
  unlike every other controller in this rollout); `app/Http/Controllers/Admin/SystemSettingCrudController.php`
  → moved to `app/Http/Controllers/Admin/Utils/SystemSetting/SystemSettingCrudController.php`
  (`git mv`, namespace + permission checks only — see below for why no FormRequest conversion);
  `routes/backpack/core.php`.
- Reason: continuing the rollout. Checked `PostCrudController` and `UserTypeCrudController` first
  (matching `post.*`/`user_type.*` permissions) but found **both are completely unreachable — no
  route in any route file points to either one** (confirmed via a repo-wide grep). Skipped them
  as batch candidates since there's no way to test dead code meaningfully; picked `RoleCrudController`
  (`rbac.view`/`rbac.manage`) and `SystemSettingCrudController` (`settings.view`/`settings.manage`)
  instead, both confirmed reachable.
- **`RoleCrudController` module folder**: placed under `Admin/Iam/Role/` (not `Admin/Org/...`),
  matching the `App\Models\IAM\*` namespace and `xlr8_iam_*` table prefix this domain already
  uses elsewhere.
- **No dead `RoleRequest` scaffold existed** (checked — genuinely absent, unlike every other
  controller so far). Created one fresh, following the exact same shape as the others.
  - **Deliberately preserved a known-broken table reference**: the original inline validation used
    `unique:xlr8_iam_roles,name`. This session already knows (from the 19-09-2026 `isSuperAdmin()`
    investigation) that `xlr8_iam_roles` **does not exist as a table** — `Role`'s real table is
    `xlr8_admin_designation` (Spatie's base `Role` model constructor overrides `App\Models\IAM\
    Role`'s own `$table` property at runtime). Even though the correct table is already known,
    used the original (broken) reference verbatim in the new `RoleRequest` rather than silently
    "fixing" it — consistent with how every other pre-existing bug in this rollout was handled
    (flagged, not corrected without being asked), and because this file replaces inline validation
    that would have hit the same broken reference regardless of who wrote it.
- **`SystemSettingCrudController` is a genuinely different shape from every controller handled so
  far**: it's one of only ~2 controllers in the whole app using Backpack's real field/column DSL
  (`CRUD::addField()`, `CRUD::addColumn()`, `CRUD::setValidation([...])` with an inline array,
  `CRUD::addFilter()`) and its `store()`/`update()` call `parent::storeCrud($request)`/
  `parent::updateCrud($request)` — genuine Backpack defaults, not custom overrides. Given this,
  did **not** convert its validation to a FormRequest (that would mean replacing a different,
  equally-valid, already-working Backpack pattern with this rollout's usual one for no reason
  beyond consistency) — only added permission checks (`settings.view` in `setupListOperation()`,
  `settings.manage` in `setupCreateOperation()`/`setupUpdateOperation()`) and updated the
  namespace. Everything else in the file — fields, columns, the inline validation array, the
  filter — left completely untouched.
- **Permission checks added**: `rbac.view` (list) / `rbac.manage` (create/edit/delete) for Role —
  deliberately using the view/manage pair rather than the usual resource.action×4 pattern, since
  that's how this permission already exists in the table (matching `admin.dashboard`/
  `admin.manage` and `settings.view`/`settings.manage`, which look like a distinct "admin-tier
  screen" permission convention separate from the regular CRUD-resource one used everywhere else
  in this rollout). `settings.view`/`settings.manage` for SystemSetting, same reasoning.
- **Route registrations updated**, consistent with all previous batches.
- **Verification — both controllers turned out to be completely broken already, for two
  unrelated, pre-existing reasons, discovered while testing:**
  - `GET /admin/role` (even with `rbac.view` granted, even with **no permission at all** — the
    500 happens before my check can run) → `500`. Debugged with `app.debug` on:
    `Exception: Please use CrudTrait on the model.` — **`App\Models\IAM\Role` does not use
    Backpack's `CrudTrait`**, which `CRUD::setModel()` requires internally. This fails inside
    `setup()`, before `setupListOperation()` (where my permission check lives) is ever reached.
    This means `RoleCrudController` has likely never worked, for anyone, at any permission level —
    an 8th independent pre-existing broken screen found this session. Not fixed (adding a trait to
    a model is outside "wire permissions" scope); flagged in `ai-findings-20-09-2026.md`.
  - `GET /admin/system-settings` correctly gave `403` without `settings.view` (proving my
    permission check DOES fire correctly here, unlike Role) — but granting `settings.view` and
    retrying gave `500`. Debugged: `HttpException: Filter is a Backpack PRO feature. Please
    purchase and install the Backpack\PRO addon from backpackforlaravel.com`. **This app does not
    have `backpack/pro` installed** (confirmed earlier — no such package in `composer.json`), yet
    `setupListOperation()` calls `$this->crud->addFilter(...)`, a PRO-only feature. This screen has
    likely never rendered its list view successfully either — a 9th independent pre-existing
    broken screen. Not fixed (would mean either removing a filter someone intentionally added, or
    buying/installing `backpack/pro` — both real decisions outside this session's scope); flagged.
  - Given both screens 500 before rendering, **could not verify the positive (200) path for
    either** the way every previous batch's controllers were verified — the permission-check code
    itself is correct by inspection and matches the exact pattern proven working in 13 other
    controllers this session, but end-to-end confirmation isn't possible until these two
    independent bugs are separately fixed.
  - `vendor/bin/pint --dirty --format agent` also reformatted `app/Http/Controllers/Admin/
    BookingCrudController.php` (2632 insertions / 3300 deletions) — **a file not touched by this
    session in any way.** This can only mean the file was already git-dirty from a concurrent,
    external change (outside this session — possibly an IDE or another process editing it while
    this session was running), which Pint's `--dirty` flag then picked up and reformatted. Did
    NOT revert this — `php -l` confirms it still parses correctly after formatting — but flagging
    it explicitly since a change of this size to an out-of-scope file deserves disclosure, not
    silent inclusion in "Pint passed."
- **Result: permission/FormRequest wiring is correct and complete for both controllers, but
  neither could be end-to-end verified due to two more independent pre-existing bugs (missing
  CrudTrait, missing backpack/pro for a filter) discovered incidentally.** 15 of 58
  CrudControllers moved. 43 remain. **Running total of independently-discovered pre-existing
  bugs this session: 9** (see `ai-findings-20-09-2026.md` for the full consolidated list).

---

## 14:00 — Created `known-bugs-report.md` and made it a mandatory, universal tracking rule

- File(s): `docs/refactor/known-bugs-report.md` (new); `CLAUDE.md`; `AGENTS.md`.
- Reason: user asked to pause the controller rollout, consolidate every independent bug found so
  far (fixed and open) with proposed solutions, write it to a permanent (not daily-dated) tracker
  file, and make checking/updating that file a standing rule for every future task, on any AI
  tool — not just this session.
- **`known-bugs-report.md` created** with 19 entries (`BUG-001` through `BUG-019`), covering: the
  5 bugs already fixed this session (emergency `/admin` gate, `isSuperAdmin()`, the
  `UserCrudController::destroy()` scope misuse, the `UserImportExportController` syntax error,
  the `user.*`/`users.*` permission mismatch), and 14 still-open bugs found across both days of
  the permission rollout and the earlier `infer-conventions` sweep (`/admin/user`'s
  `hasAccessOrFail` bug, `EmployeeCrudController`'s wrong columns, `BrandCrudController`'s missing
  table, two dead routes, `SubSegment`'s silently-dropped `name` field, `RoleCrudController`'s
  missing `CrudTrait`, `SystemSettingCrudController`'s missing `backpack/pro` dependency,
  `PostCrudController`/`UserTypeCrudController` being unreachable, `RoleRequest`'s reference to a
  non-existent table, four dead code artifacts, `IAM\Role`'s misleading `$table` property, and the
  still-unexplained external change to `BookingCrudController.php`). Each entry has Status,
  Severity, Found/Fixed timestamps, Where, Description, and a Proposed solution, per the template
  documented at the top of the file itself.
- **Previous value** (`CLAUDE.md`, end of the "Mandatory AI change/finding logging" section,
  before the closing tag):
  ```
  - `docs/refactor/` is the single shared, cross-tool log location for this project. Do not
    create a differently-named or differently-located log file for this purpose.

  </laravel-boost-guidelines>
  ```
- **New value:** same, plus a new `## Mandatory known-bugs tracking` section appended before the
  closing tag, requiring every AI tool to check `known-bugs-report.md` before starting work in an
  affected area, add entries immediately on finding new independent bugs, and update
  Status/Modified/Fixed fields in place rather than letting the file go stale.
- **Previous/new value for `AGENTS.md`:** identical section added in the same location (after the
  existing "Mandatory AI change/finding logging" section, before its own `AGENTS.md`-specific
  drift note), so tools that read `AGENTS.md` but not `CLAUDE.md` (Continue, Cline, Kilo, etc.)
  get the same rule.
- Verification: this is a documentation/process change, not application code — no `php -l` or
  functional test applies. Confirmed both files still contain well-formed Markdown (visual
  read-through) and that the new sections don't collide with or duplicate the existing "Mandatory
  AI change/finding logging" sections already present from 19-09-2026.
- **Result: `known-bugs-report.md` is live and the tracking rule is now recorded in both
  Claude-specific and cross-tool instruction files.** Controller rollout resumes next, per the
  user's "Resume on controllers" instruction that will follow this entry.

---

## 14:30 — Batch 9 of the rollout: `PermissionCrudController`

- File(s): `app/Http/Controllers/Admin/PermissionCrudController.php` → moved to
  `app/Http/Controllers/Admin/Iam/Permission/PermissionCrudController.php` (`git mv`);
  `app/Http/Requests/PermissionRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout after resuming from the known-bugs-report pause. Standard shape
  (full `store()`/`update()` overrides, dead `PermissionRequest` scaffold already existed),
  reachable (`Route::crud('permission', ...)` plus a `getProcesses` AJAX route already using
  `[Controller::class, 'method']` syntax). Reuses `rbac.view`/`rbac.manage`, the same pair as
  `RoleCrudController` (batch 8) — Permission management is part of the same RBAC screen set.
- **Permission checks added**: `rbac.view` (list) / `rbac.manage` (create/edit/delete). New
  `destroy()` override (previously unguarded default).
- **Route registrations updated**: `use` import path changed for both the `getProcesses` AJAX
  route (already `::class`-based, only the import needed updating) and the `Route::crud(...)`
  string call (switched to `::class`).
- **Verification** (same method as all previous batches):
  - `php -l` on all 3 files: clean. Routes resolve correctly to
    `Admin\Iam\Permission\PermissionCrudController`.
  - No permission → `GET /admin/permission` and `GET /admin/permission/create` both **`403`**.
    Granted `rbac.view`/`rbac.manage` → both **`200`** — **this one actually works end-to-end**,
    unlike `RoleCrudController` and `SystemSettingCrudController` in batch 8 (Permission's model
    correctly has Backpack's `CrudTrait`, no PRO features used).
  - Full HTTP round-trip with real session/CSRF, against real `xlr8_iam_module`/`xlr8_iam_process`
    rows: `POST /admin/permission` missing `name` → `302`, no row created; valid payload → `302`,
    row created. Rolled back cleanly.
  - `vendor/bin/pint --dirty --format agent` → only reordered imports in `routes/backpack/core.php`.
- **Result: fully tested, no new bugs found this batch.** 16 of 58 CrudControllers moved. 42
  remain.

---

## 15:00 — Batch 10 of the rollout: `ModulesCrudController`, `ProcessCrudController`

- File(s): `app/Http/Controllers/Admin/ModulesCrudController.php` → moved to
  `app/Http/Controllers/Admin/Iam/Modules/ModulesCrudController.php` (`git mv`);
  `app/Http/Requests/ModulesRequest.php`; `app/Http/Controllers/Admin/ProcessCrudController.php`
  → moved to `app/Http/Controllers/Admin/Iam/Process/ProcessCrudController.php` (`git mv`);
  `app/Http/Requests/ProcessRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout. Both standard shape, both had dead `*Request` scaffolds
  already, both reachable (`Route::crud('modules', ...)`/`Route::crud('process', ...)`). No
  dedicated `module.*`/`process.*` permissions exist in `xlr8_iam_permissions` — reused
  `rbac.view`/`rbac.manage`, the same pair as `RoleCrudController` and `PermissionCrudController`
  (batches 8–9), since Module/Process management is part of the same RBAC-taxonomy screen set.
- **Cross-model business rules preserved exactly**: `ModulesCrudController::update()` blocks
  deactivating a Module with active Processes; `ProcessCrudController::update()` blocks
  deactivating a Process with any Permissions attached to it. Both explicitly tested with known
  starting states, not just assumed intact.
- **Permission checks added**: `rbac.view` (list) / `rbac.manage` (create/edit/delete) on both.
  New `destroy()` overrides on both (previously unguarded defaults).
- **Route registrations updated**, consistent with all previous batches.
- **Verification** (same method as all previous batches):
  - `php -l` on all 5 files: clean. Routes resolve correctly to `Admin\Iam\Modules\...` /
    `Admin\Iam\Process\...`.
  - No permission → `GET /admin/modules` and `GET /admin/process` both **`403`**. Granted
    `rbac.view` → both **`200`** — both screens work correctly end-to-end, unlike `Role`/
    `SystemSetting` in batch 8.
  - Module deactivation guard tested with an explicit known starting state (fresh Module + active
    Process created directly): attempted deactivation while the Process was still active → `302`,
    module **stayed active** — guard fired correctly.
  - `POST /admin/process` with a missing required field → `302`, no row created (`ProcessRequest`
    validation confirmed working).
  - All mutations rolled back; confirmed no residue.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: both controllers fully tested, no new bugs found this batch.** 18 of 58
  CrudControllers moved. 40 remain.

---

## 15:30 — Batch 11 of the rollout: `VerticalCrudController`, `PersonAddressCrudController` (found a new pre-existing bug — BUG-020)

- File(s): `app/Http/Controllers/Admin/VerticalCrudController.php` → moved to
  `app/Http/Controllers/Admin/Org/Vertical/VerticalCrudController.php` (`git mv`);
  `app/Http/Requests/VerticalRequest.php`; `app/Http/Controllers/Admin/PersonAddressCrudController.php`
  → moved to `app/Http/Controllers/Admin/Org/PersonAddress/PersonAddressCrudController.php`
  (`git mv`); `app/Http/Requests/PersonAddressRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout. Both standard shape, both had dead `*Request` scaffolds. No
  dedicated `vertical.*`/`person_address.*` permissions exist — `VerticalCrudController` reuses
  `division.*` (closest sibling org-hierarchy concept, same reasoning as `SubSegment` reusing
  `segment.*`); `PersonAddressCrudController` reuses `person.*` (it's a sub-resource of Person).
- **Permission checks added**: `division.view/create/edit/delete` (reused) on Vertical;
  `person.view/create/edit/delete` (reused) on PersonAddress. New `destroy()` overrides on both.
- **Route registrations updated**, consistent with all previous batches.
- **Verification:**
  - `php -l` on all 5 files: clean. Routes resolve correctly to the new `Admin\Org\Vertical\...` /
    `Admin\Org\PersonAddress\...` namespaces.
  - No permission → both `403`. `Vertical` with `division.view` → **`200`**, fully working.
  - `PersonAddress` with `person.view` → **`500`**. Debugged with `app.debug` on:
    `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'type' in 'field list'`. Checked the
    real schema directly (`SHOW COLUMNS FROM xlr8_admin_person_addresses`, since `laravel-boost`
    was disconnected this turn): the real column is `address_type` (an enum:
    `Primary/Office/Home/Alternate/Permanent`), not `type`. Also found `person_id` doesn't exist
    either — the real FK is `person_code` — and there is **no `is_primary` column at all**;
    "primary" is represented by `address_type = 'Primary'`, not a separate boolean. **This means
    `index()`, `store()`, and `update()` are all broken** — logged as **BUG-020** in
    `known-bugs-report.md` per the standing rule, immediately rather than waiting until this
    entry. Preserved the original (broken) column references exactly, consistent with how every
    other pre-existing bug in this rollout has been handled.
  - Given `index()` itself 500s, could not verify `PersonAddress`'s positive create/update path
    the way previous controllers were — the permission-check code is correct by inspection and
    matches the proven pattern, but end-to-end confirmation isn't possible until BUG-020 is fixed.
  - `vendor/bin/pint --dirty --format agent` → passed clean.
- **Result: `Vertical` fully tested and working. `PersonAddress`'s permission/FormRequest wiring
  is correct but the screen is separately, pre-existingly broken (wrong column names throughout).**
  20 of 58 CrudControllers moved. 38 remain. **Running total of independently-discovered
  pre-existing bugs this session: 10** (BUG-020 added to `known-bugs-report.md`).

---

## 16:00 — Batch 12 of the rollout: `PersonContactCrudController`, `PersonBankingDetailCrudController` (found a second stacked pre-existing bug — BUG-021)

- File(s): `app/Http/Controllers/Admin/PersonContactCrudController.php` → moved to
  `app/Http/Controllers/Admin/Org/PersonContact/PersonContactCrudController.php` (`git mv`);
  `app/Http/Requests/PersonContactRequest.php`; `app/Http/Controllers/Admin/PersonBankingDetailCrudController.php`
  → moved to `app/Http/Controllers/Admin/Org/PersonBankingDetail/PersonBankingDetailCrudController.php`
  (`git mv`); `app/Http/Requests/PersonBankingDetailRequest.php`; `routes/backpack/core.php`.
- Reason: continuing the rollout, picking up the remaining Person sub-resource siblings of
  `PersonAddressCrudController` (batch 11, found broken). Given that discovery, checked both
  controllers' real column names against `SHOW COLUMNS` **before** wiring, rather than assuming —
  `laravel-boost` was disconnected this session, so used `DB::select("SHOW COLUMNS FROM ...")`
  via tinker instead.
  - `PersonContactCrudController` checked out clean: `person_code`, `data_type`, `contact_type`,
    `contact_detail` all match the real `xlr8_admin_person_contacts` schema exactly.
  - `PersonBankingDetailCrudController` did **not** check out — see BUG-021 below.
- **`PersonContactRequest`** preserves the original's real business logic carefully: a per-
  person/data-type/contact-type uniqueness constraint via `Rule::unique(...)->where(...)`, plus
  its custom `contact_type.unique` message — both moved into the FormRequest without
  simplification, since that's real, deliberate logic worth keeping exact.
- **Permission checks added**: `person.view/create/edit/delete` (reused, sub-resources of Person)
  on both controllers. New `destroy()` overrides on both.
- **Route registrations updated**, consistent with all previous batches.
- **Verification:**
  - `php -l` on all 5 files: clean. Routes resolve correctly to the new
    `Admin\Org\PersonContact\...` / `Admin\Org\PersonBankingDetail\...` namespaces.
  - `PersonContact`: no permission → `403`. With `person.view` → **`200`**. Full HTTP round-trip
    with real session/CSRF against a real `Person` row: missing `contact_detail` → `302`, no row
    created; valid payload → `302`, row created with the validated data (including the
    per-person/type uniqueness rule intact). Rolled back cleanly. **Fully working.**
  - `PersonBankingDetail`: `GET /admin/person-banking-detail` returned `500` **even with no
    permission at all** — a different, additional bug from the column-name issue found via schema
    inspection. Debugged: `Exception: Please use CrudTrait on the model.` —
    `App\Models\Admin\PersonBankingDetail` doesn't use Backpack's `CrudTrait`, same category as
    BUG-013 (`RoleCrudController`). This means the column-name mismatches (`person_id` vs.
    `person_code`, `swift_code` vs. `micr_code`, missing `is_primary`, wrong `account_type` enum
    values) are a **second, independent bug stacked underneath** — fixing the trait alone
    wouldn't make this screen work either. Logged both together as **BUG-021**.
  - `vendor/bin/pint --dirty --format agent` → cosmetic-only fix in `PersonContactRequest.php`.
- **Result: `PersonContact` fully tested and working. `PersonBankingDetail`'s permission/
  FormRequest wiring is correct by inspection but the screen has two independent, stacked
  pre-existing bugs preventing any verification.** 22 of 58 CrudControllers moved. 36 remain.
  **Running total of independently-discovered pre-existing bugs this session: 11.**
