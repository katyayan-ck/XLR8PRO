# AI Changelogs — 22-09-2026

## Department CRUD: code immutability, dependency guard, media, ORG_ENTITY_MANAGE (Org CRUD phase, entity 2/N)

Continuing the Org CRUD update from 21-09-2026 (Designation) — same pattern applied to Department,
per the same spec (code immutable after creation, disable blocked by active dependents, image +
documents upload wired via the shared `BaseModel` media collections, `ORG_ENTITY_MANAGE` permission
only).

- **New: `app/Services/Org/DepartmentService.php`** — `create()`/`update()` (code stripped before
  update; `update()` returns `{ok:false, blockers:[...]}` instead of saving when disabling would
  orphan active dependents) and `syncMedia()`. Dependents: `Division` (`dept_code`, default
  `is_active` column) and `Employee` (`primary_dept_code`, `employment_status = 'active'` — same
  override needed as Designation→Employee, see BUG-077). Kept the pre-existing "auto-create a
  same-coded default Division on Department creation" behaviour as-is (unrelated to this task,
  wasn't asked to change it).
- **`app/Http/Controllers/Admin/Org/Department/DepartmentCrudController.php`**: rewritten to
  delegate to `DepartmentService`; all permission checks changed from
  `ORG_DEPT_VIEW/CREATE/EDIT/DELETE` to `ORG_ENTITY_MANAGE`. Replaced the old ad hoc
  `division_blocked` session-flash + `activeDivisions` array (which only ever checked Divisions, not
  Employees, and needed matching JS on every view) with the same `withErrors(['is_active' => ...])`
  pattern used for Designation — one code path, shown via a standard `@if ($errors->any())` block
  instead of a bespoke SweetAlert wiring. `index()`'s grid now includes an `image` column.
- **`resources/views/admin/department/{create,edit}.blade.php`**: code field read-only on edit (was
  live-editable); swapped the ad hoc image-only upload block for
  `@include('admin.org.partials.media-fields', ...)`; added the standard `@if ($errors->any())`
  block; removed the dead image-preview JS and the department-specific disable-warning JS/Blade
  (superseded by the shared server-side guard + generic error display).
- **`resources/views/admin/department/list.blade.php`**: AG Grid config now includes an `image`
  column (thumbnail or em-dash), added to the default-visible and customise-headers column lists.
- **`resources/views/admin/designation/edit.blade.php`**: retrofitted with the same
  `@if ($errors->any())` block (missed when Designation was done on 21-09-2026 — its blockers were
  visible via the `is_active` field's own error styling from Backpack conventions elsewhere, but had
  no explicit rendering block; Department's build surfaced the inconsistency).
- **New: `tests/Feature/Admin/Org/DepartmentCrudTest.php`** — 6 feature tests: code immutability,
  the pre-existing default-Division-on-create behaviour, dependency-guard block via Division, via
  Employee, dependency-guard pass once clear, and permission-gate denial.

### Verification

- `php -l` clean on all new/changed PHP files. `vendor/bin/pint --dirty --format agent` → passed,
  no changes needed.
- `php artisan test --filter=DepartmentCrudTest` → 6 passed, 16 assertions. Full
  `tests/Feature/Admin/Org/` directory (Designation + Department together) → 13 passed, 36
  assertions — confirms no cross-test interference between the two entities' fixtures.
- HTTP round trip via `app()->handle()` (relative-path form — see BUG-078 in known-bugs-report.md for
  why absolute-URL requests 404 in this dev environment): `org/department/create` → 200,
  `org/department/{id}/edit` → 200, `org/department` → 200.

## Division CRUD: code immutability, dependency guard, media, ORG_ENTITY_MANAGE (Org CRUD phase, entity 3/N)

Same pattern applied to Division — child of Department, assigned to Employee one-to-many via
`primary_div_code`.

- **New: `app/Services/Org/DivisionService.php`** — `create()`/`update()` (code stripped before
  update) and `syncMedia()`. Dependent: `Employee` (`primary_div_code`, `employment_status =
  'active'`). Also preserves the pre-existing, genuinely useful cross-entity rule — a Division can't
  be (re)activated while its parent Department is inactive — now raised as a `ValidationException`
  (same `is_active` error key as everywhere else) instead of the previous ad hoc
  `back()->withErrors()` duplicated inline in the controller.
- **`app/Http/Controllers/Admin/Org/Division/DivisionCrudController.php`**: rewritten to delegate to
  `DivisionService`; permission checks changed from `ORG_DIVN_VIEW/CREATE/EDIT/DELETE` to
  `ORG_ENTITY_MANAGE`. `index()`'s grid now includes an `image` column. `dept_code` stays editable
  (a Division can be re-parented to a different Department — only `code` itself is immutable, per
  spec).
- **`resources/views/admin/division/{create,edit}.blade.php`**: code field read-only on edit; swapped
  the ad hoc image-only upload block for the shared media partial; added the standard
  `@if ($errors->any())` block; removed dead image-preview/code-uppercasing JS. Kept the existing,
  still-useful client-side "Department is inactive, lock the checkbox" UX in `edit.blade.php` as-is
  (distinct concern from the dependency guard — it's a *parent*-state lock, not a *child*-dependents
  block).
- **`resources/views/admin/division/list.blade.php`**: AG Grid config now includes an `image` column.
- **New: `tests/Feature/Admin/Org/DivisionCrudTest.php`** — 5 feature tests: code immutability, the
  parent-department-inactive activation block, dependency-guard block via Employee, dependency-guard
  pass once clear, permission-gate denial.

### Verification

- `php -l` clean, `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --filter=DivisionCrudTest` → 5 passed, 15 assertions. Full
  `tests/Feature/Admin/Org/` (Designation + Department + Division) → 18 passed, 51 assertions.
- HTTP round trip: `org/division/create` → 200, `org/division/{id}/edit` → 200, `org/division` → 200.

## Vertical CRUD: code immutability, dependency guard, media, ORG_ENTITY_MANAGE (Org CRUD phase, entity 4/N)

Same pattern applied to Vertical — standalone entity, assigned to Employee one-to-many.

- **New: `app/Services/Org/VerticalService.php`** — `create()`/`update()` (code stripped before
  update) and `syncMedia()`. Dependent: `Employee` (`vertical_code`, `employment_status =
  'active'`). Deliberately does **not** use `Vertical::employeeAssignments()`/`employees()` — both
  point at a pivot table (`xlr8_admin_emp_vertical_pivot`) that doesn't exist in the live schema
  (confirmed via `Schema::getColumnListing()` returning empty); logged as BUG-081. Uses the real,
  populated `employee.vertical_code` column directly instead, same pattern as every other org
  entity's primary-assignment check.
- **`app/Http/Controllers/Admin/Org/Vertical/VerticalCrudController.php`**: rewritten to delegate to
  `VerticalService`; permission checks changed from the ad hoc reused `ORG_DIVN_VIEW/CREATE/EDIT/
  DELETE` (Vertical never had its own permission tier — its controller's own docblock noted this)
  to `ORG_ENTITY_MANAGE`, so it's now consistent with every other org entity instead of borrowing
  Division's. `index()`'s grid now includes an `image` column. Left the pre-existing `code`→
  `vert_code` sync as-is (the model's `setCodeAttribute()` mutator already keeps them in sync
  automatically; the controller's own manual `$validated['vert_code'] = $validated['code']` line
  was redundant and dropped along with the rest of the old body).
- **`resources/views/admin/vertical/{create,edit}.blade.php`**: code field read-only on edit; swapped
  the ad hoc image-only upload block for the shared media partial; added the standard
  `@if ($errors->any())` block; removed dead image-preview JS.
- **`resources/views/admin/vertical/list.blade.php`**: AG Grid config now includes an `image` column.
- **New: `tests/Feature/Admin/Org/VerticalCrudTest.php`** — 4 feature tests: code immutability,
  dependency-guard block via Employee, dependency-guard pass once clear, permission-gate denial.

### Bug caught during this batch (not a pre-existing issue — introduced and fixed within this same edit)

While stripping the dead per-field JS out of `vertical/edit.blade.php`, a stray `@endpush` was
accidentally left behind at the very end of the file with no matching `@push()` (the `@push`/
`@endpush` pair that used to wrap the removed script block got only its opening half removed).
`Illuminate\View\ViewException: Cannot end a push stack without first starting one` — caught
immediately by the same round-trip HTTP check used for every other entity (`org/vertical/{id}/edit`
→ 500 instead of the expected 200), before it was ever committed as "done." Fixed by removing the
orphaned `@endpush`. Re-verified `@push`/`@endpush` counts balance (`grep -c`) across every Blade
view touched in this phase (Designation, Department, Division, Vertical — all 2/2) as a quick
sanity sweep after the fix, and re-ran the full HTTP round trip for all 4 entities' create/edit/index
pages (12 checks) to confirm nothing else regressed.

### Verification

- `php -l` clean, `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --filter=VerticalCrudTest` → 4 passed, 12 assertions. Full
  `tests/Feature/Admin/Org/` (all 4 entities) → 22 passed, 63 assertions.
- HTTP round trip (all 4 entities, 12 checks: create/index for each + edit for each) → all 200 after
  the `@endpush` fix above.

## Branch CRUD: code immutability, dependency guard, media, ORG_ENTITY_MANAGE (Org CRUD phase, entity 5/N)

Same pattern applied to Branch — parent of Location, assigned to Location and Employee one-to-many.
Branch is routed by `code` (not numeric `id`), unlike the other 4 entities so far — preserved as-is.

- **New: `app/Services/Org/BranchService.php`** — `create()`/`update()` (code stripped before
  update; single-head-office enforcement kept exactly as it was) and `syncMedia()`. Dependents:
  `Location` (`branch_code`, matches `Branch.code`) and `Employee` (`primary_branch_code`,
  `employment_status = 'active'`, also matched against `Branch.code`). Deliberately does **not**
  use `Branch::primaryEmployees()` — that relation joins on `Branch.branch_code`, a column that's
  never populated (not in `$fillable`, confirmed `NULL` for all 3 real branches) while real
  `primary_branch_code` values on Employee actually match `Branch.code`; logged as BUG-082. Uses
  `Branch.code` directly instead, matching the pattern `Branch::locations()` (correctly) already
  uses for the same kind of check.
- **`app/Http/Controllers/Admin/Org/Branch/BranchCrudController.php`**: rewritten to delegate to
  `BranchService`; permission checks changed from `ORG_BRCH_VIEW/CREATE/EDIT/DELETE` to
  `ORG_ENTITY_MANAGE`. Dropped the now-unnecessary `setupCreateOperation()`/`setupUpdateOperation()`/
  `defineFields()` (Backpack-native field definitions that the hand-rolled `create()`/`store()`/
  `edit()`/`update()` never actually used — same dead-hook pattern already cleaned up for Users and
  Designation earlier this rollout). `index()`'s grid now includes an `image` column. Noticed (but
  did **not** fix, out of scope) that `setupListOperation()` shadows the `ScopedCrud` trait's data
  scoping — pre-existing since before this refactor, logged as BUG-083.
- **`resources/views/admin/branch/edit.blade.php`**: code field read-only (create's stays editable —
  code is only immutable *after* creation); swapped the ad hoc image-only upload block for the
  shared media partial; added the standard `@if ($errors->any())` block; removed dead image-preview
  and now-redundant code-length-validation JS; kept the phone/pincode digit-only formatting and the
  "switch head office?" confirmation dialog, both still genuinely useful UX.
  `resources/views/admin/branch/create.blade.php`: same media-partial/errors-block treatment, code
  field and its JS validation left untouched (still needed here).
- **`resources/views/admin/branch/list.blade.php`**: AG Grid config now includes an `image` column
  (this view uses a `getCols(fields)` helper rather than inline `ALL_COLUMNS.filter()` calls, unlike
  the other 4 entities' list views — followed its existing convention rather than rewriting it to
  match).
- **New: `tests/Feature/Admin/Org/BranchCrudTest.php`** — 6 feature tests: code immutability,
  dependency-guard block via Location, via Employee, dependency-guard pass once clear, the
  single-head-office-enforcement behaviour, permission-gate denial.

### Verification

- `php -l` clean, `vendor/bin/pint --dirty --format agent` → passed. `@push`/`@endpush` balance
  checked on both Branch Blade views before testing (lesson from the Vertical `@endpush` incident
  above) — both 2/2.
- `php artisan test --filter=BranchCrudTest` → 6 passed, 17 assertions. Full
  `tests/Feature/Admin/Org/` (all 5 entities) → 28 passed, 80 assertions.
- HTTP round trip: `org/branch/create` → 200, `org/branch` → 200, `org/branch/{code}/edit` → 200.

## Location CRUD: code immutability, dependency guard, media, ORG_ENTITY_MANAGE (Org CRUD phase, entity 6/6 — completes the originally-listed set)

Same pattern applied to Location — child of Branch, leaf of the org hierarchy.

- **New: `app/Services/Org/LocationService.php`** — `create()`/`update()` (code stripped before
  update) and `syncMedia()`. Dependent: `Employee` (`primary_loc_code`, `employment_status =
  'active'`). Deliberately does **not** use `Location::employeeAssignments()` — points at a pivot
  table (`xlr8_admin_emp_location_pivot`) that doesn't exist, same dead-pivot pattern as BUG-081
  (Vertical); logged as part of BUG-084. Uses `employee.primary_loc_code` directly instead.
- **`app/Http/Controllers/Admin/Org/Location/LocationCrudController.php`**: rewritten to delegate to
  `LocationService`; permission checks changed from `ORG_LOCN_VIEW/CREATE/EDIT/DELETE` to
  `ORG_ENTITY_MANAGE`. `index()` no longer eager-loads `Location::branch()` (that relation also
  joins on the broken `Branch.branch_code` column, always returning `null` — same root cause as
  BUG-082, folded into BUG-084) — looks branch names up via a `Branch::pluck('name', 'code')` map
  instead, which is both correct against real data and one query instead of N. `index()`'s grid now
  includes an `image` column.
- **`resources/views/admin/location/{create,edit}.blade.php`**: code field read-only on edit; swapped
  the ad hoc image-only upload block for the shared media partial; added the standard
  `@if ($errors->any())` block; removed dead image-preview and now-redundant code-length JS; kept
  the "Is Office Only exclusive of the other 6 type flags" toggle JS and the phone/pincode/lat/long
  numeric-formatting JS, both still genuinely useful UX untouched by this refactor.
- **`resources/views/admin/location/list.blade.php`**: AG Grid config now includes an `image` column.
- **New: `tests/Feature/Admin/Org/LocationCrudTest.php`** — 4 feature tests: code immutability,
  dependency-guard block via Employee, dependency-guard pass once clear, permission-gate denial.

### Verification

- `php -l` clean, `vendor/bin/pint --dirty --format agent` → passed. `@push`/`@endpush` balance
  checked on both Location Blade views (2/2 each).
- `php artisan test --filter=LocationCrudTest` → 4 passed, 12 assertions. Full
  `tests/Feature/Admin/Org/` (all 6 entities) → **32 passed, 92 assertions**.
- HTTP round trip, all 6 entities (13 checks: create + index for each, plus one edit-page spot check
  per entity across today's two sessions) → all 200.

## Phase summary: Org CRUD update (Designation, Department, Division, Vertical, Branch, Location)

All 6 entities the user originally listed ("So check and fix them while I am listing more") are now
done, each with: `code` immutable after creation, a dependency-checked disable guard (via the shared
`OrgEntityGuard` service), image + documents upload/remove wired through each entity's inherited
`BaseModel` media collections, and `ORG_ENTITY_MANAGE` as the sole CRUD permission (replacing 5
different `ORG_{ENTITY}_VIEW/CREATE/EDIT/DELETE` permission tiers). Designation additionally got a
real permission-management panel (Module → Process → Permission tree) since it doubles as a Spatie
role. 27 feature tests across 6 files, all passing (92 assertions total). Along the way, fixed the
`phpunit.xml` misconfiguration that had made the test suite entirely unusable (BUG-078), and found
and documented (without fixing, all out of scope for this phase) 7 pre-existing, independent data/
relation bugs: BUG-077 (Employee has no `is_active`), BUG-079 (testing-only Spatie guard interaction),
BUG-080 (32 pre-existing unrelated test failures), BUG-081/084 (three dead pivot-table relations —
Vertical, Location), BUG-082/084 (two relations joining on the perpetually-NULL `Branch.branch_code`
column — `Branch::primaryEmployees()`, `Location::branch()`), and BUG-083 (Branch's data-scoping
trait silently shadowed). The user said more entities may still be listed ("while I am listing
more") — this phase is complete for the 6 named so far, not necessarily the whole Org CRUD update.

## Follow-up: wired ORG_ENTITY_MANAGE into the actual permission-grant data and the nav menu

Switching all 6 controllers to `ORG_ENTITY_MANAGE` (above) changed what the CODE checks, but two
other places still needed to catch up for the change to actually work for real (non-superadmin)
users, found and fixed in the same sitting:

- **`ORG_ENTITY_MANAGE` had no `module_code`/`process_code`** — minted back in the Designation phase
  via a bare `Permission::firstOrCreate(['name' => ..., 'guard_name' => 'web'])`, so both columns
  were `NULL`. `database/seeders/RolePermissionSeeder.php` builds its per-role permission set from
  `Permission::whereNotNull('module_code')->groupBy('module_code')` — a permission with a `NULL`
  module_code is invisible to that seeder entirely, at any tier. Fixed by creating a real `ENTITY`
  process row under the `ORG` module in `xlr8_iam_process` (matching the existing `BRCH`/`DEPT`/
  `DESG`/`DIVN`/`EMPL`/`LOCN`/`PRSN`/`USER` processes already there, and the `'ENTITY' => 'Reference
  Data'` label already added to `PermissionTreeService` during the Designation phase) and backfilling
  `ORG_ENTITY_MANAGE`'s `module_code`/`process_code` to `ORG`/`ENTITY`.
- **Tier semantics decision**: once `ORG_ENTITY_MANAGE` had a `module_code`, it started showing up
  automatically for any role with `'ORG' => 'full'` (the seeder's 'full' tier just grabs every
  permission under a module — no code change needed there). It's still correctly *excluded* from
  `'basic'`/`'view'` tiers, since those filter by `_VIEW`/`_CREATE` suffix and `ORG_ENTITY_MANAGE`
  ends in `_MANAGE` — this is the right call, not an oversight: those tiers previously granted only
  read/create-level access to the old per-entity permissions, and `ORG_ENTITY_MANAGE` is a single
  blanket grant with no read-only equivalent, so auto-granting it to `'basic'`/`'view'` roles would
  be a privilege escalation (day-to-day HR/Admin Executive roles would suddenly get full CRUD over
  Designation/Department/Division/Vertical/Branch/Location, not just view/create). Re-ran
  `RolePermissionSeeder` for real — 63 roles re-synced; verified live that `HR Manager` (`'ORG' =>
  'full'`) now has `ORG_ENTITY_MANAGE` while `HR Executive` (`'ORG' => 'basic'`) correctly does not.
- **`resources/views/vendor/backpack/ui/inc/menu_items.blade.php`**: the Foundation section's
  Branch/Location/Department/Division/Designation/Vertical links were still individually gated on
  the old `ORG_BRCH_VIEW`/`ORG_LOCN_VIEW`/`ORG_DEPT_VIEW`/`ORG_DIVN_VIEW` (used twice — once for
  Division, again by copy-paste for Vertical)/`ORG_DESG_VIEW` permissions, which the controllers no
  longer check and which most roles (per the tier decision above) no longer hold — meaning a user
  with real `ORG_ENTITY_MANAGE` access could reach every one of these 6 pages directly by URL but
  wouldn't see any of them in the nav menu, since the menu was still gated on now-mostly-ungranted
  permissions. Collapsed all 6 links under a single `@if (backpack_user() && backpack_user()->can('ORG_ENTITY_MANAGE'))`
  block, matching what the controllers actually enforce.

### Verification

- Confirmed live (rolled-back where testing, real writes where fixing data): `ORG_ENTITY_MANAGE`
  now has `module_code=ORG`, `process_code=ENTITY`; the `ENTITY` process row exists under `ORG`;
  `RolePermissionSeeder` run for real, 63/76 roles re-synced; `HR Manager`
  (`hasPermissionTo('ORG_ENTITY_MANAGE')` → true) vs `HR Executive` (→ false) confirms the
  least-privilege tier boundary holds.
- HTTP round trip: a `HR Manager`-roled user hitting `org/designation` → 200, page content contains
  both the Designation link and the Branch link (menu renders correctly, no Blade errors from the
  `@if`/`@endif` restructure — checked balance: still even).
- `grep` swept `app/` and `resources/views/` for any other remaining `ORG_DESG_*`/`ORG_DEPT_*`/
  `ORG_DIVN_*`/`ORG_BRCH_*`/`ORG_LOCN_*` references — only `DemoRbacController.php` (spoofed mockup
  data, not enforced) remains; no other real enforcement point left stale.
- `vendor/bin/pint --dirty --format agent` → passed. Full `tests/Feature/Admin/Org/` suite → still
  32 passed, 92 assertions (unaffected by this follow-up, as expected).

## Added a "Development Workflow" rule to CLAUDE.md / AGENTS.md (explicit user request)

Per explicit instruction, added a 4-step Development Workflow section (Boost MCP tools → Larastan →
Pest/PHPUnit → commit only when Larastan passes) to both `CLAUDE.md` (under "XCELR8 Project-Specific
Rules", before "Non-negotiable for the standardization refactor") and `AGENTS.md` (as a new
"universal — all AI tools" section, matching the existing logging/known-bugs section style, since
`AGENTS.md` is the canonical cross-tool copy). Verified Larastan (`vendor/bin/phpstan analyse`)
actually works in this environment before relying on it in the rule — found it OOMs on a full-project
run here (Windows paging file too small; logged as BUG-085) but works fine scoped to a directory with
a higher `--memory-limit`. Used that scoped pattern for every Larastan check for the rest of this
session.

## Integrated Person CRUD: single-screen contact/address/banking management (explicit user request)

Built the integrated Person system the user asked for, studying `app/Imports/Sheets/
StandaloneUsersImport.php` and `app/Services/PersonService.php` first per their instruction — the
service already had a complete, well-designed API (`upsert`/`upsertContact`/`upsertAddress`/
`upsertBanking`/`setPrimary`) that the *existing* `PersonCrudController` wasn't using at all (it
called `Person::create()`/`update()` directly, bypassing contacts/addresses/banking entirely and
violating `.ai/rules/person-user.md`'s "Always use PersonService" rule). Rebuilt the controller to
use it as the single source of truth throughout.

- **`app/Models/Admin/Person.php::deriveCode()`**: flipped the individual-entity priority from
  PAN-first/Aadhaar-second to **Aadhaar-first/PAN-second**, per explicit instruction ("first choice
  is adhar, second is PAN and last is dynamically generated person-code") — this reverses the
  model's own prior documented design rule (`PAN (priority) → Aadhaar`), a deliberate, explicit
  product decision for this new screen, not a guess. Legal-entity priority (PAN → TAN) left
  unchanged — the user's minimum-fields spec (Name + Mobile) is individual-oriented. Only affects
  future person creation; existing person codes are immutable and untouched. Also fixed an
  unrelated bug found via Larastan in the same file — see BUG-087.
- **`app/Http/Requests/PersonRequest.php`**: rewritten. `display_name` required on both create/
  update; `mobile` required only on create (`isMethod('POST')`) — mobile isn't a `Person` column at
  all (it's a child `PersonContact`), so the edit screen's core-info form doesn't touch it; mobile
  management moves entirely to the Contacts card after creation. Every other field (identity
  numbers, DOB, gender, etc.) is optional on both.
- **`app/Http/Controllers/Admin/Org/Person/PersonCrudController.php`**: rewritten.
  - `create()`/`store()`: minimal — name + one primary mobile only. `store()` builds a
    `PersonService::upsert()` payload with a single `contacts` entry (`Mobile`/`Primary`) and
    **omits** `person_code` entirely so the service derives it via `Person::deriveCode()`.
    Redirects straight to the new person's edit screen ("add more contacts/addresses/banking
    below"), not back to the list.
  - `edit()`: eager-loads `contacts`/`addresses`/`bankingDetails` and renders the single integrated
    screen.
  - `update()`: core `Person` fields only, via `PersonService::upsert()` with the existing
    (immutable) `person_code` passed through explicitly — never touches contacts/addresses/banking.
  - **New**: 12 sub-resource actions — `store/update/destroy/primary` × `Contact`/`Address`/
    `Banking` — each delegating to `PersonService`'s existing methods
    (`upsertContact`/`upsertAddress`/`upsertBanking`, plus the child models' own
    `makesPrimary()`/`makePrimary()`) or a scoped `where('person_code', ...)->findOrFail()` +
    `->delete()` for removal (soft-delete, per `SoftDeletes` on all three child models — there's no
    separate `is_active` column on any of them, so "disable" and "remove" are the same operation
    here, and a removed row can be restored by the same route any future "restore" UI would use).
    Every mutation redirects back to the relevant card via a URL fragment (`#contacts`/`#addresses`/
    `#banking`).
  - **New**: `syncMedia()` wires the model's own `profile_photos` (single image) and
    `identity_documents` (multi-file) collections — different collection names than the generic
    `admin.org.partials.media-fields` partial used by the Org-entity batch, so this is bespoke
    markup rather than a shared include.
  - Permission checks unchanged (`ORG_PRSN_VIEW`/`CREATE`/`EDIT`) — Person wasn't one of the 6
    entities migrated to `ORG_ENTITY_MANAGE` earlier this rollout, and this task didn't ask for
    that.
- **`routes/backpack/core.php`**: added 12 new routes under `org/person/{id}/{contacts,addresses,
  banking}[/{childId}[/primary]]`, generated via a small `foreach` (mirroring the existing
  per-entity-loop convention already used elsewhere in this file) rather than 12 hand-written
  `Route::` calls.
- **`resources/views/admin/person/create.blade.php`**: rewritten from the old ~15-field form down to
  Name + Primary Mobile only, with an optional `<details>`-collapsed "More details" section (entity
  type, salutation, gender, DOB, Aadhaar, PAN, occupation) — everything else moves to the edit
  screen after creation.
- **`resources/views/admin/person/edit.blade.php`**: rewritten as 4 stacked cards — Core Info +
  Media (single form, all `Person` columns + profile photo + identity documents), Contacts,
  Addresses, Banking. The latter three each list existing rows (badge for type, "Make Primary"
  button when not already primary, "Edit fields"/"Remove" affordances) plus an "Add" section.
  Addresses and Banking only offer *unused* type slots in their "Add" dropdown (each type is a
  one-per-person slot, enforced by `PersonService`'s `updateOrCreate` composite key) — computed via
  `array_diff` against each collection's already-used types. Uses native HTML `<details>`/`<summary>`
  for all expand/collapse interactions instead of Bootstrap's JS `data-bs-toggle="collapse"` — no
  other page in this app currently uses Bootstrap's collapse component, and grepping the theme's
  compiled `tabler.js` bundle for `Collapse`/`data-bs-toggle` came back empty, so `<details>` was
  the safer, zero-JS-dependency choice, consistent with the same pattern already used in
  `create.blade.php`'s optional-fields section.
- **New**: `resources/views/admin/person/partials/{address-fields,banking-fields}.blade.php` —
  shared field sets for both the per-row "Edit fields" forms and the "Add" forms (the add form's
  type `<select>` is scoped to only the unused types; the edit form's type is locked via a
  `disabled` select + hidden input, since changing an existing row's type would let it collide with
  another slot).
- **`resources/views/vendor/backpack/ui/inc/menu_items.blade.php`**: removed the 3 separate "Person
  Contact"/"Person Address"/"Person Banking Detail" nav links — they're superseded by the integrated
  Person screen. Their controllers/routes/views are untouched and still directly reachable by URL
  (not deleted, just no longer surfaced in navigation), in case anything still depends on them.

### Bugs found and fixed along the way (not pre-existing knowledge — discovered via this task's own testing)

- **BUG-086 (High, FIXED)**: `PersonContact::makesPrimary()` / `PersonAddress::makePrimary()` /
  `PersonBankingDetail::makePrimary()` all fataled with a unique-constraint violation on a specific,
  realistic "promote the other one to Primary" sequence — MySQL's fixed-ENUM unique indexes don't
  support deferred constraint checking, so a naive demote-old-then-promote-new two-statement
  sequence collides when the row being promoted already sits in the exact slot the old Primary is
  about to be demoted into. Fixed all three with a transaction that stages the promoted row through
  a free slot first when that collision is possible. Full detail in `known-bugs-report.md`.
- **BUG-087 (Medium, FIXED)**: `Person::garages()` referenced `Garage::class` with no import,
  resolving to a nonexistent `App\Models\Admin\Garage` — the real model is `App\Models\Core\Garage`.
  One-line import fix, found via the newly-adopted scoped-Larastan workflow step.
- **BUG-088 (Low, OPEN — documented only)**: `StandaloneUsersImport`'s own person-code derivation
  logic is independent of `Person::deriveCode()` and still uses the old PAN-first order, now
  inconsistent with the model's (deliberately) flipped Aadhaar-first order. Flagged for a follow-up
  decision, not fixed — the import file was explicitly "study, don't modify" for this task.

### Verification

- `php -l` clean on every new/changed PHP file. `vendor/bin/pint --dirty --format agent` → passed
  (after a few auto-fixes on import ordering/brace style).
- Scoped Larastan (`vendor/bin/phpstan analyse app/Http/Controllers/Admin/Org/Person
  app/Models/Admin/Person*.php app/Http/Requests/PersonRequest.php --memory-limit=2G`) → only the
  known, pre-existing `@property`-docblock noise remains (documented in today's findings doc) after
  fixing BUG-087; no other real errors.
- `php artisan test --filter=PersonCrudTest` → 8 passed, 22 assertions, covering: minimal
  name+mobile creation with fallback code derivation, mobile-required validation, Aadhaar-over-PAN
  priority, code immutability on update, add/remove a contact, the BUG-086 primary-promotion
  collision path (via the real HTTP route), the equivalent address scenario, and permission-gate
  denial. Full `tests/Feature/Admin/Org/` directory → 40 passed, 114 assertions.
- HTTP round trip via `app()->handle()`: `org/person` (index) → 200, `org/person/create` → 200,
  `org/person/{id}/edit` → 200 both before and after adding a contact/address/banking row via the
  new sub-resource routes; `org/person-contact` (the now-unlisted-but-not-deleted legacy screen)
  still reachable directly → 200, confirming nothing was actually broken, just hidden from nav.

## User system: UserType seeder, Person→User onboarding wizard, and an Employee Journey service (explicit user request)

Three-part explicit request: (1) seed the missing DSA/Customer user types, (2) rebuild the "New
User" flow around a person typeahead + conditional org/vehicle/permission cards with cascading
selects, (3) a service to track and query every org/vehicle/permission change an employee has ever
had, with effective dates.

### 1. `database/seeders/UserTypeSeeder.php` (new)

Seeds `DSA` → "Direct Selling Agent" and `CUST` → "Customer" into `xlr8_iam_user_type` (which only
had `emp` → "Employee" before). Run for real (`php artisan db:seed --class=UserTypeSeeder`), not
just written — confirmed both rows exist.

### 2. `app/Services/HR/EmployeeJourneyService.php` (rewritten — was dead/unused code before)

Found an existing, unreferenced `EmployeeJourneyService` (confirmed via `grep`, zero callers besides
itself) with the right shape but a real bug — its `logChange()` never closed the previously-open
`EmployeeHistory` row, so every "change" would have left multiple simultaneously-open rows. Rewrote
it as the single source of truth for org/vehicle/permission history, registered as a singleton in
`AppServiceProvider` (alongside the existing, unrelated `HRJourneyService` — a separate, broken
"Post" subsystem per BUG-080; left untouched):

- `recordChange()` — closes the previous open period, writes a new one. Addon scopes (branches/
  locations/departments/divisions/segments/sub_segments beyond the single "primary" per type) and a
  permission snapshot (`{role, added, removed}`) both live inside the existing, previously-unused
  `scopes` JSON column as `{"addons": {...}, "permissions": {...}}` — no migration needed.
  `recordPermissionChange()` is a convenience wrapper for permission-only changes that carries the
  employee's current org/vehicle state forward unchanged.
- Query methods covering the user's explicit examples plus some creative additions: `stateOn()`/
  `roleOn()` ("where was keshav working, and as what role, during Aug '26"), `whoWas()` (matches
  against both the primary column AND that type's addon scopes — "who was Sales Manager at Bikaner
  Nokha for CV segment"), `journey()` (full chronological history), `journeyByName()` (resolve a
  name/username fragment to employee(s) via Person, then journey each — no need to know the emp
  code up front), `tenureInDesignation()` (total days across non-contiguous stints in the same
  role), `transferHistory()` (entries where the primary branch/location actually changed, as
  opposed to a same-location designation/permission change), `promotionHistory()` (entries where
  designation rank improved, via `Designation.rank`), `teamAt()` (everyone whose primary or addon
  branch was a given branch on a date, any designation — "who was on my team here").
- Verified all of the above against real seeded data in rolled-back tinker transactions before
  wiring anything into the controller: `recordChange()` correctly closes the prior row
  (`effective_to` = day before); `roleOn()` correctly resolves different designations at different
  dates across 2 recorded transitions; `whoWas()` correctly matches via both primary column and
  addon-scope JSON; `transferHistory()`/`promotionHistory()`/`tenureInDesignation()` all produced
  correct results against constructed scenarios.

### 3. Person → User onboarding screen (`app/Http/Controllers/Admin/Org/User/UserCrudController.php`, `app/Http/Requests/UserRequest.php`, `resources/views/admin/user/{create,edit}.blade.php`)

Rewrote `create()`/`store()`/`edit()`/`update()` and `UserRequest` entirely; `index()`/`show()`/
`suspend()`/`revoke()`/`activate()`/`destroy()` untouched.

- **New: `searchPersons()`** — `GET org/user/search-persons?q=`, live typeahead over persons who
  don't already have a `User` row, matching name/code/mobile, returning display name, salutation,
  mobile, email, and profile photo for the "autoload" requirement.
- **Create screen**: person typeahead (selecting a result fills a preview card and a hidden
  `person_code` — no new Person is ever created here, only linked) → User Type (from `UserType`,
  mapped to the real `users.user_type` ENUM via a small fixed map since the two are different,
  independently-cased vocabularies) → date of joining/username/password → **Org card** (shown only
  when the selected type is Employee): Designation doubles as the role (same "Designation IS the
  Spatie Role" convention as the rest of this app), Primary Branch (single) + Addon Branches
  (multi, primary auto-hidden from the options), Primary Location (single, cascades to children of
  the primary branch) + Addon Locations (multi, children of primary **and** addon branches, primary
  auto-hidden), the same primary/addon/cascade pattern repeated for Department→Division, plus a
  prepopulated Vertical (single) → **Vehicle card** (Employee only): the identical primary/addon/
  cascade pattern for Segment→Sub Segment → **Permission card**: the same Module→Process→Permission
  tree UI built earlier for `demo/roles`/Designation (`demo.partials.tree` + `RbacTree` JS,
  `mode: 'override'`), pre-checked from every real Designation-backed role's actual permission set
  (embedded once as `ROLE_PERMISSIONS[designationCode]`, swapped in live on designation change — no
  extra request), with add/remove captured as a `{added, removed}` diff and submitted as real
  `name[]` array inputs (not a JSON string — first draft got this wrong, caught before it shipped,
  see below).
- **`store()`**: creates the `Employee` row (code auto-generated as the next sequential
  `BMPL-####`) only when the type is Employee, creates the `User`, assigns the role
  (`Role::where('code', $designationCode)` — the Designation-as-Role link), applies permission
  overrides (`syncPermissions()` for adds, `UserPermissionDenial` rows for removes — same mechanism
  built earlier this rollout), syncs addon `UserScope` rows per type, and writes the **initial**
  `EmployeeHistory` record via `EmployeeJourneyService::recordChange()`.
- **Edit screen**: same structure, person is shown read-only (never re-searchable — matches
  `person_code` being effectively permanent once a user exists), every field pre-filled from the
  current `Employee`/`UserScope`/permission-override state, permission tree initialized via
  `applyOverride(rolePermissions, currentOverrides.added, currentOverrides.removed)` so existing
  overrides show correctly on load. Kept the pre-existing "org info changed → show reason/effective-
  date block, block submit without it" UX, extended to also watch the addon multi-selects and
  vehicle fields (not just the single primary columns the old version checked).
- **`update()`**: detects org/vehicle changes (primary fields **and** addon-scope-array diffs) and
  requires `change_reason`/`effective_date` before applying, exactly like before — same rejection
  path, verified live (unchanged branch data → no rejection; changed branch without a reason →
  rejected with the field's data preserved; changed branch with a reason → applied and a new
  `EmployeeHistory` row written via the service). **New**: when nothing org/vehicle-related changed
  but the permission diff differs from the last recorded snapshot, records a *separate*
  permission-only history entry via `recordPermissionChange()` (no reason/date required for this
  path — deliberately lower friction, permission tweaks aren't "employment journey" events the way
  a transfer is).
- Old `recordEmployeeHistory()` private method deleted — superseded by
  `EmployeeJourneyService::recordChange()`, called the same way but through the shared service
  instead of duplicated logic.

### Bugs found and fixed while building/testing this (not pre-existing knowledge)

- **BUG-089 (Medium, FIXED)**: `SubSegment` model's `$fillable` references a nonexistent `oem_name`
  column (real column is `name`) — the Vehicle card's `org/user/create` page 500'd on first load
  until this was caught and the *calling* code fixed to use the real column name. The model itself
  is still wrong (not fixed, flagged for a follow-up — see BUG-089's full entry).
- **BUG-090 (Medium, OPEN — documented only)**: while manually verifying `update()`'s org-change
  path against real employees, found 36 employees with a `designation_code` that doesn't exist in
  `xlr8_admin_designation` and 30 with an empty `primary_branch_code` — real, pre-existing data
  gaps that correctly (not a bug) block the new screen from recording an org/vehicle change for
  those specific employees until the missing primary fields are backfilled.

### Verification

- `php -l` clean on every new/changed PHP file. `vendor/bin/pint --dirty --format agent` → passed.
- Scoped Larastan (`vendor/bin/phpstan analyse app/Services/HR/EmployeeJourneyService.php
  app/Http/Controllers/Admin/Org/User/UserCrudController.php app/Http/Requests/UserRequest.php
  database/seeders/UserTypeSeeder.php --memory-limit=2G`) → only the already-documented, pre-existing
  `@property`-docblock noise remains; fixed the one real finding (a stale constructor docblock
  referencing a `$dataScopeService` parameter that hasn't existed for a while — updated to describe
  the actual 4 injected services).
- `php artisan test --filter=UserOnboardingTest` → 6 passed, 23 assertions: UserType seeding,
  full employee-user creation (Employee created, role assigned, initial history written), addon
  branches + permission add/remove applied on create, org-change-without-reason correctly rejected,
  org-change-with-reason correctly applied and recorded, permission-gate denial. Full
  `tests/Feature/Admin/Org/` directory (all of today's + yesterday's work) → **46 passed, 137
  assertions**.
- HTTP round trip: `org/user/create` → 200 (after the BUG-089 fix); `org/user/{id}/edit` → 200.
  Full manual `store()`/`update()` walkthroughs in rolled-back tinker transactions against real org
  data confirmed: minimal employee creation, addon branch + permission add/remove together, the
  org-change-requires-reason rejection, a real transfer (branch change) correctly recorded via
  `EmployeeJourneyService`, and a permission-only edit correctly recorded as its own history entry
  without requiring a reason/effective date.
