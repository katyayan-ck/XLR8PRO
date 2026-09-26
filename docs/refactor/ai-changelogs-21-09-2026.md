# AI Changelog — 21-09-2026

Continuation of the Module/Process/Activity structural migration (see
`.ai/rules/module-structure.md`). Batch 34 (Vehicle module) completed 20-09-2026. This file covers
batch 35 (Iam module) and the cross-batch BUG-066 remediation (controller-self-URL audit gap).

## Batch 35 — Iam module (Modules, Permission, Process, Role)

- **Files**: `app/Http/Controllers/Admin/Iam/Modules/ModulesCrudController.php`,
  `.../Permission/PermissionCrudController.php`, `.../Process/ProcessCrudController.php`,
  `.../Role/RoleCrudController.php`, `routes/backpack/core.php`,
  `resources/views/admin/{modules,permission,process,role}/*.blade.php`,
  `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`.
- **Permissions**: renamed `rbac.view`/`rbac.manage` → `IAM_RBAC_VIEW`/`IAM_RBAC_MANAGE` (all 4
  entities share these 2 permissions, matching pre-existing shared-permission behavior — not split
  into per-entity permissions).
- **Routes**: replaced 4 `Route::crud()` calls with 32 explicit registrations under a new
  `==================== IAM: ... ====================` section in `core.php`, each using the
  options-array form with the `'operation'` key (per BUG-064's mandatory pattern) —
  `Route::get('iam/module', ['uses' => ModulesCrudController::class.'@index', 'as' =>
  'iam.module.index', 'operation' => 'list'])`, etc. Also moved
  `permission/processes/{moduleCode}` → `iam/permission/processes/{moduleCode}` (route name
  `iam.permission.get-processes`).
- **Menu**: `menu_items.blade.php` RBAC dropdown's 4 links (Modules/Process/Role/Permission) now
  gated by `@if (backpack_user() && backpack_user()->can('IAM_RBAC_VIEW'))`, wrapped correctly around
  only those 4 items (the dropdown element itself and the unrelated "Post Permission"/"Approval
  Hierarchy" items below it are outside the `@if`, to avoid a structural Blade mismatch).
- **Why**: continuation of the standing full-app retrofit — Iam was one of the remaining unmigrated
  modules per `docs/refactor/TASK_STATE.md`.
- **Tests run**: HTTP-kernel round trip, rolled-back transaction. `iam/module` index confirmed `200`
  with `IAM_RBAC_VIEW` granted, rendered response contains the new `iam/module/{id}/edit` URL (not
  the old `modules/{id}/edit`).

## BUG-066 remediation — controller-self-URL hardcoded-URL audit gap

- **Discovered**: while finishing batch 35, Pint's IDE-diagnostic hook surfaced an old, un-renamed
  URL still present inside `ModulesCrudController.php` itself, despite that entity's Blade-view URL
  audit having already been completed. Traced the root cause to a blind spot in every batch's
  hardcoded-URL audit since batch 30: it only ever grepped Blade views, never controllers' own PHP
  source, and a first-pass bulk fix script additionally missed multi-line-formatted `backpack_url()`
  calls. Full description and root cause: `docs/refactor/known-bugs-report.md` BUG-066.
- **Files fixed** (internal `backpack_url()` calls — AJAX grid edit/view button URLs and post-save
  `redirect()` calls — repointed from pre-rename to current URLs, no other logic touched):
  - `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php`
  - `app/Http/Controllers/Admin/Sales/Campaign/CampaignCrudController.php`
  - `app/Http/Controllers/Admin/Sales/Enquiry/EnquiryCrudController.php`
  - `app/Http/Controllers/Admin/Sales/Lead/LeadCrudController.php`
  - `app/Http/Controllers/Admin/Sales/LeadSource/LeadSourceCrudController.php`
  - `app/Http/Controllers/Admin/Sales/Quotation/QuotationCrudController.php` (redirect after
    `store()`: `quotation-form/{id}/edit` → `sales/quotation/{id}/edit`)
  - `app/Http/Controllers/Admin/Accounts/JournalVoucher/JournalVoucherCrudController.php`
  - `app/Http/Controllers/Admin/Accounts/Receipt/ReceiptCrudController.php`
  - `app/Http/Controllers/Admin/Utils/KeyValue/KeyValueCrudController.php` (grid edit URL:
    `keyvalue/{id}/edit` → `utils/key-value/{id}/edit`)
  - `app/Http/Controllers/Admin/Utils/KeywordMaster/KeywordMasterCrudController.php`
  - `app/Http/Controllers/Admin/Spares/SpareRequest/SpareRequestCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/Brand/BrandCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/Color/ColorCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/Model/VehicleModelCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/Segment/SegmentCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/SubSegment/SubSegmentCrudController.php`
  - `app/Http/Controllers/Admin/Vehicle/Variant/VariantCrudController.php`
  - `app/Http/Controllers/Admin/Iam/Modules/ModulesCrudController.php` (grid edit URL:
    `modules/{id}/edit` → `iam/module/{id}/edit`)
  - `app/Http/Controllers/Admin/Iam/Permission/PermissionCrudController.php`
  - `app/Http/Controllers/Admin/Iam/Process/ProcessCrudController.php`
  - `app/Http/Controllers/Admin/Iam/Role/RoleCrudController.php`
- **Why**: these were functional regressions introduced by this rollout itself — clicking an "Edit"
  button on any of these entities' list screens, or completing a create/update form, would land on a
  dead pre-rename URL (404).
- **How to apply going forward**: `.ai/rules/module-structure.md` §6 updated with 2 new permanent
  audit requirements — grep the controller's own PHP source (not just Blade views) for
  `backpack_url(`, and separately grep for the bare multi-line form (`backpack_url($`) since
  single-line string matching alone misses it. Every future batch must follow this before
  considering a URL rename complete.
- **Verification**: bulk Python script (56 single-line replacements) + individually-targeted
  `Edit`/`sed` fixes for the multi-line cases found via manual sweep. Final repo-wide
  `grep -rn "backpack_url($"` across all affected module directories returns exactly 5 matches, each
  manually confirmed correct by inspecting surrounding lines. `php -l` clean on all 20 touched files.
  `vendor/bin/pint --dirty --format agent` applied — style-only fixes (`line_ending`,
  `unary_operator_spaces`, `not_operator_with_successor_space`, `single_quote`) on 6 Vehicle-module
  files, no logic changes. HTTP-kernel round trip (rolled-back transaction, real active user):
  `iam/module` index and `utils/key-value` index both render `200` with the correct new-scheme edit
  URL present in the response body and the old pre-rename URL absent.

## Pricing module (6 controllers) — first-time permission enforcement (BUG-068)

- **Files**: `app/Http/Controllers/Admin/Pricing/{Hold,Insurance,PricingReset,PricingWorkflow,
  RtoRule,TcsConfig}Controller.php`, `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`.
- **What**: Pricing's routes/namespaces already conformed to the module-structure convention
  (pre-existing, `admin/pricing/{process}/*`, `Admin\Pricing\*`, `pricing.{process}.{activity}`
  route names) — but none of its 6 controllers had any Spatie-permission check at all. Minted 10
  new permissions (ids 189-198, `guard_name = 'web'`): `PRC_HOLD_VIEW/MANAGE`, `PRC_INSR_VIEW`,
  `PRC_RESET_MANAGE`, `PRC_WKFL_VIEW/MANAGE`, `PRC_RTOR_VIEW/MANAGE`, `PRC_TCS_VIEW/MANAGE`. Gated
  every action method (GET/read → `*_VIEW`, POST/PUT/mutating → `*_MANAGE`), following the
  established inline `backpack_user()->can(...)` pattern (these are plain `Controller`s, not
  `CrudController`s — no `setupXOperation()` hooks, so BUG-064's trap does not apply here).
  `PricingResetController` (a single destructive `__invoke()` that deletes vehicle variant/model
  rows and flushes multiple pricing tables on `GET ...?confirm=1`) got its own dedicated
  `PRC_RESET_MANAGE` permission rather than reusing `PRC_WKFL_MANAGE`, so it can be restricted
  independently.
- **Why**: this was a real, live security gap (BUG-068, Critical) — any user past the coarse
  `CheckIfAdmin` gate could view and mutate pricing configuration, including triggering the
  destructive reset endpoint, with zero per-resource authorization.
- **Menu**: fixed the "Price List" link's visibility condition (the only menu entry covering all of
  Pricing) from `auth()->user()->hasPermissionTo(...)`/`hasRole(...)` (never resolves under
  Backpack's guard — BUG-055) to `backpack_user()->can(...)`/`hasRole(...)`, matching every other
  gate in this file. Its target URL (`backpack_url('pricing')`) has no matching route and remains
  dead — documented as BUG-069, not fixed (feature/UX decision, out of scope).
- **Found and documented, not fixed**: BUG-067 (`routes/backpack/pricing_routes.php` is a stale,
  incomplete duplicate of `pricing.php` — both auto-loaded, harmlessly deduped for the routes they
  share, same pattern as BUG-036).
- **Tests run**: HTTP-kernel round trip, rolled-back transaction, real active user. All 6 entities'
  representative endpoints (`tcs`, `hold`, `rto` index/create, `insurance/defaults`, `workflow`,
  `reset`) confirmed `403` with no permission, `200` with the specific permission granted. `php -l`
  clean on all 6 files. `vendor/bin/pint --dirty --format agent` applied (style-only fixes).

## Org module, batch 1 of 2 (11 entities: Branch, Department, Designation, Division, Employee,
## Location, Person, PersonAddress, PersonBankingDetail, PersonContact, Vertical)

- **Decision** (user, explicit, asked before starting): rename Org's existing `resource.action`
  permissions to the `ORG_{PROCESS}_{ACTIVITY}` convention, matching every batch since 26, rather
  than leaving them on the old naming. Chosen over the lower-risk "route/namespace only, leave
  permissions as-is" alternative.
- **Files**: all 11 controllers under `app/Http/Controllers/Admin/Org/*/`, `routes/backpack/core.php`,
  33 Blade view files (create/edit/list × 11 entities) under `resources/views/admin/{entity}/`,
  `resources/views/admin/dashboard.blade.php`, `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`.
- **Permissions minted** (ids 199-226, `guard_name = 'web'`): `ORG_BRCH_*`, `ORG_DEPT_*`,
  `ORG_DESG_*`, `ORG_DIVN_*`, `ORG_EMPL_*`, `ORG_LOCN_*`, `ORG_PRSN_*` (VIEW/CREATE/EDIT/DELETE
  each — 7 groups × 4 = 28 permissions). Preserved 2 pre-existing, deliberate shared-permission
  relationships confirmed via batch-11/20-22 history in `ai-changelogs-20-09-2026.md`: Vertical
  reuses `ORG_DIVN_*` (was `division.*`), and PersonAddress/PersonBankingDetail/PersonContact all
  reuse `ORG_PRSN_*` (were `person.*`) as sub-resources of Person — no new permissions minted for
  these 4, matching prior intent exactly.
- **Routes**: converted 11 `Route::crud(...)` registrations in `core.php` to an explicit `foreach`
  loop registering all 8 standard operations per entity under `admin/org/{slug}`, route names
  `org.{entity}.{activity}`. Added the `'operation'` key on every route per the BUG-064 blanket-safety
  recommendation, though a pre-check (grepping every controller for `setupListOperation()` vs. an
  inline `index()` override with its own permission check) confirmed none of these 11 rely solely on
  the hook — every one has a redundant inline check, so BUG-064 did not actually apply here; the key
  was added anyway for consistency and defense in depth.
- **`search()`/`showDetailsRow()` trait-default gap** (same pattern as BUG-047/051/054/063): none of
  the 11 controllers overrode these — added the standard trait-alias override
  (`use ListOperation { search as traitSearch; ... }`) with a permission check to all 11.
- **Self-inflicted regression caught and fixed inline**: the first bulk `sed` pass for the permission
  rename (e.g. `s/branch\.edit/ORG_BRCH_EDIT/g`) also matched view-path strings like
  `'admin.branch.edit'` (Blade view names, not permission strings), corrupting 7 files' view paths to
  `'admin.ORG_BRCH_EDIT'` etc. Caught immediately via the IDE-diagnostic hook showing the changed
  file content, and fixed with a second targeted `sed` pass restoring the correct lowercase
  dot-notation view paths (verified against `resources/views/admin/{entity}/{create,edit,list}.blade.php`
  actually existing on disk). PersonAddress/PersonBankingDetail/PersonContact/Vertical use kebab-case
  view paths and were not affected by this pattern.
- **Hardcoded-URL audit** (per BUG-066's now-permanent requirement — controller PHP source +
  multi-line form, not just Blade views): all 11 controllers' internal `backpack_url()`/`redirect()`
  calls fixed via per-entity `sed`; 33 Blade view files' `backpack_url()` calls fixed the same way;
  additionally found and fixed 6 real cross-references in `dashboard.blade.php` (4 dashboard-card
  links) and `menu_items.blade.php` (Foundation section's Branch/Location/Department/Division/
  Designation/Vertical links, Users Info section's Person/PersonContact/PersonAddress/
  PersonBankingDetail/Employee links) — none of these had ANY permission gating before this batch
  (unlike the Vehicles Info section, which already had `@if` gates from batch 34), so gates were
  added at the same time as the URL fixes.
- **Tests run**: HTTP-kernel round trip, rolled-back transaction, real active user, across all 11
  entities. All 11 correctly return `403` with no permission. With the permission granted: 8 return
  `200` with the new `org/` URL confirmed present in the rendered response; 3 return `500` — traced
  to already-documented, pre-existing bugs unrelated to this migration (`Employee` → BUG-008, column
  mismatch on `person_id`; `PersonAddress` → BUG-020, column mismatch on `type`; `PersonBankingDetail`
  → BUG-021, column mismatch on `is_primary`/`swift_code`/`account_type`). Confirmed via a warm-up
  request that an initial `404` on the very first route dispatched in a fresh `tinker` process is a
  test-harness artifact (self-corrects after any prior request in the same process), not a real
  routing defect — consistent with the same behavior observed in every batch tested today.
  `php -l` clean on all 11 controllers + `core.php`. Both edited Blade files confirmed compiling via
  `Blade::compileString()`. `vendor/bin/pint --dirty --format agent` applied (1 style-only fix).

## Org module, batch 2 of 2 (User, UserImportExportController) — closes out the entire module-structure migration phase

- **Files**: `app/Http/Controllers/Admin/Org/User/UserCrudController.php`,
  `app/Http/Controllers/Admin/Org/User/UserImportExportController.php`, `routes/backpack/core.php`,
  `routes/web.php`, `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`,
  `resources/views/admin/dashboard.blade.php`.
- **Permissions minted** (ids 227-232, `guard_name = 'web'`): `ORG_USER_VIEW/CREATE/EDIT/DELETE`
  (renamed from `users.view/create/update/delete`) and `ORG_USER_EXPORT/IMPORT` (renamed from
  `users.export/import`). No functional behavior changed — this is a straight rename, same as every
  other Org entity in batch 1.
- **Routes**: `UserCrudController`'s `Route::crud('user', ...)` (registered in both `core.php` and
  `routes/backpack/booking.php` — the pre-existing BUG-036 duplicate, left as-is, still harmlessly
  deduped) converted to explicit routes under `admin/org/user` with `org.user.*` names, `'operation'`
  key preserved on every route (this controller relies **purely** on `setupListOperation()`/
  `setupCreateOperation()`/`setupUpdateOperation()` hooks for list/create/edit — no inline overrides
  in `index()`/`create()`/`edit()` — so BUG-064's trap applies here directly, unlike the other 11 Org
  entities; the key was essential, not just defense-in-depth). `UserImportExportController`'s
  `routes/web.php` group moved from `admin/users` (plural, `users.*` names) to `admin/org/user`
  (`org.user.import`/`org.user.export`/etc.) for consistency with the CRUD controller's own prefix —
  they now share one URL namespace.
- **`search()`/`showDetailsRow()`/`show()` trait-default gap** (same pattern as every prior batch):
  none of the 3 were overridden — `UserCrudController` uses `ListOperation` and `ShowOperation`
  (the latter unique to this controller among the 12 Org entities); added trait-alias overrides with
  an `ORG_USER_VIEW` gate to all 3.
- **Menu**: fixed 2 previously-ungated "Users" links pointing at the old bare `user` URL — one nested
  inside the "Users Info" dropdown, and a second, apparently-duplicate top-level
  `<x-backpack::menu-item title="Users" ...>` entry outside any dropdown (both now gated on
  `ORG_USER_VIEW`, both repointed to `org/user`). Fixed 1 more reference in `dashboard.blade.php`'s
  quick-links card.
- **Explicitly not fixed** (per standing instruction — a route/permission migration does not fix
  functional bugs): `UserCrudController`'s `store()`/`update()`/`destroy()` still call nonexistent
  `parent::storeCrud()`/`updateCrud()`/`deleteCrud()` (BUG-040 — no user has ever actually been
  created/updated/deleted through this screen), the `select2`/`dropdown`-filter PRO-feature gaps
  (BUG-014), and `UserImportExportController`'s 4 missing views (BUG-043). All three still block the
  respective screens exactly as before, confirmed unaffected by this batch's changes.
- **Tests run**: HTTP-kernel round trip, rolled-back transaction, real active user (with a warm-up
  request first, to avoid the known first-request-in-a-fresh-process `404` artifact). All 4 sampled
  endpoints (`org/user`, `org/user/create`, `org/user/import`, `org/user/export`) correctly return
  `403` with no permission. With the permission granted, all 4 return `500` — confirmed via JSON
  error responses to be exactly BUG-014 (`"Filter is a Backpack PRO feature"`) and BUG-043
  (`"View [admin.users.import] not found"`), i.e. the gate passes correctly and the pre-existing,
  already-documented bug is reached — no new regression. `php -l` clean on both controllers.
  `routes/backpack/core.php`/`routes/web.php` both `php -l` clean; `route:list` confirms all 16
  `org/user*` routes registered with no old bare-`user`/`admin/users` URLs remaining.
  `vendor/bin/pint --dirty --format agent` applied — a large sweep touched many files across the
  whole session's uncommitted changes (line-ending normalization only, `git status`/`php -l`-verified
  no logic changes) after an earlier `git stash`/`stash pop` (used briefly, unnecessarily, to inspect
  a pre-change route list — flagged here as a process note: should have been avoided per the
  standing caution around git operations touching a large uncommitted working tree; it resolved
  cleanly with nothing lost, confirmed via `git stash list` being empty afterward).

**This closes the entire Module/Process/Activity structural migration phase.** All 43 entities across
Sales, Accounts, Finance, Insurance, Rto, Spares, Utils, Vehicle, Iam, Pricing, and Org now conform
to the `{module}/{process}/{activity}` route pattern, `{MODULE}_{PROCESS}_{ACTIVITY}` permission
naming, and menu-gating requirements set out in `.ai/rules/module-structure.md`.

## Post-phase cleanup: BUG-036 and BUG-067 (duplicate route registrations)

- **BUG-036** (`routes/backpack/booking.php`): removed the stale `Route::crud('user', UserCrudController::class);`
  line and its now-unused `use App\Http\Controllers\Admin\Org\User\UserCrudController;` import. This
  was originally logged as a harmless duplicate (both `booking.php` and `core.php` registered the
  identical `admin/user` routes), but became a real problem once the Org User batch converted only
  `core.php`'s copy to the new `admin/org/user` URL scheme — `booking.php`'s untouched copy kept the
  *old* bare `admin/user` URL fully live and functional, silently bypassing the whole point of the
  migration. Verified via `route:list --path=org/user` (16 routes, all correct, all under
  `admin/org/user`) and confirming zero remaining `admin/user` (bare) routes in the full table.
- **BUG-067** (`routes/backpack/pricing_routes.php`): deleted the file outright — confirmed via
  `grep -rn "pricing_routes"` across `app/`, `routes/`, `config/` that nothing referenced it by name
  (purely picked up by the directory glob). It was a stale, incomplete subset of the still-present
  `pricing.php`. Verified via `route:list --path=admin/pricing` (39 routes, unchanged) that nothing
  was lost.
- **Tests run**: `php -l` clean on both touched route files. `vendor/bin/pint --dirty --format agent`
  → `{"result":"passed"}`. HTTP-kernel round trip (rolled-back transaction): `org/user` still returns
  its expected pre-existing `500` (BUG-014, not a regression) with `ORG_USER_VIEW` granted;
  `pricing/tcs` still returns `200` with `PRC_TCS_VIEW` granted — both confirm the respective route
  files still work correctly after the cleanup.

## SuperAdmin bypass mechanism fix + top-level menu hiding (BUG-070, user-reported priority issue)

- **Files**: `app/Providers/AppServiceProvider.php`, `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`.
- **SuperAdmin fix**: added `Gate::before(fn ($user, $ability) => $user->isSuperAdmin() ? true : null)`
  in `AppServiceProvider::boot()`. Fixes every `backpack_user()->can('CODE')` check across the whole
  app in one place, since Spatie registers each permission as a Gate ability and `->can()` resolves
  through `Gate::forUser($user)->allows(...)`. Full root-cause and verification detail in
  `known-bugs-report.md` BUG-070 — in short: the bypass mechanism was completely missing (confirmed
  via `grep -rn "Gate::before" app/` returning nothing), and separately, no `superadmin` role/user
  currently exists in the database at all (the seeded `SUP001` account holds "Accessories Executive,"
  not any admin role). The mechanism fix is done and verified; granting it to a real account needs
  the app owner's explicit choice of which account(s) — not something to decide unilaterally.
- **Menu hiding**: audited every top-level (`nested` omitted) and nested `<x-backpack::menu-dropdown>`
  for whether it could ever render fully empty. Found only one dropdown where 100% of its content is
  permission-gated with no always-visible fallback links: "Vehicles Info" (inside the "Admin"
  top-level dropdown) — wrapped its whole `<x-backpack::menu-dropdown title="Vehicles Info">` in an
  `@if` OR-ing its 5 existing per-item checks (`VEH_BRND_VIEW || VEH_SEG_VIEW || VEH_MDL_VIEW ||
  VEH_VAR_VIEW || VEH_CLR_VIEW`), so the dropdown itself disappears when the user has none of them.
  Every other dropdown checked (Foundation, Users Info, RBAC, HR Operations, Accounts' Manager/
  Cashier/Executive, Imports) contains at least one link with **no permission gate at all** — mostly
  confirmed-dead/unreachable routes from BUG-015/024/045, plus some genuinely-live, ungated legacy
  links (e.g. RBAC's "Approval Hierarchy," Accounts' Cash Collection Reconciliation, all of Imports)
  — meaning those dropdowns structurally can never go empty regardless of the logged-in user's
  permissions, so wrapping them in a hide-if-empty `@if` today would be cosmetic only and not change
  real behavior. `Quotation`/`Booking`/`Transactions`/`Spares` were already correctly wrapped at the
  whole-dropdown level from earlier batches in this rollout (gate applied to the outer dropdown, not
  just inner items) — no change needed there.
- **Tests run**: `Blade::compileString()` confirms `menu_items.blade.php` still compiles after the
  `Vehicles Info` change (structural `@if`/`@endif` balance intact). SuperAdmin fix verified via
  HTTP-kernel round trip (rolled-back transaction): a temporary `superadmin`-role test user got `200`
  on 5 unrelated modules with zero explicit permissions and `can('TOTALLY_MADE_UP_PERM') === true`;
  a regular zero-permission user still correctly got `403` (no regression). `php -l` clean;
  `vendor/bin/pint --dirty --format agent` applied (import ordering/unused-import cleanup on
  `AppServiceProvider.php`, no logic change — re-verified the `Gate::before` bypass still works
  after Pint's edit).

## SuperAdmin role granted to SUP001 (real data change, per explicit user decision)

- Created the `superadmin` role for real: `Role::firstOrCreate(['name' => 'superadmin', 'guard_name'
  => 'web'], ['code' => 'SUPERADMIN', 'description' => '...'])` — id 79.
- Assigned it to `SUP001` (user id 1, the account `SuperAdminSeeder` originally created for this
  purpose) via `$user->syncRoles(['superadmin'])`, replacing its previous "Accessories Executive"
  role (clearly accidental — `SUP001`'s `Person` record is literally named "Super Admin", not a real
  employee, so the job-title role made no sense there).
- Verified live (real login, not a rolled-back transaction): `200` on `org/branch`, `iam/module`,
  `pricing/tcs`, `sales/booking`, `utils/key-value` with zero explicit permissions granted to the
  account; `org/user` correctly still returns the pre-existing BUG-014 `500`, confirming the gate
  passes correctly and an unrelated bug is reached, not a permission failure.
- Not fixed, flagged for a future pass: `SuperAdminSeeder`/`ProductionRBACSeeder` still reference a
  differently-named `super_admin` role on disk — reconcile so a future reseed doesn't recreate the gap.

## Role-assignment mechanism fix (BUG-071) — Designation IS the Spatie role

- **Files**: `app/Imports/Sheets/StandaloneUsersImport.php`, `app/Services/Importers/UserImporter.php`.
- **Context**: investigating BUG-070, the user pointed out that after switching Roles to be backed
  by Designations, "the proper mapping is not working... no new entries in model_has_roles." Found 3
  disconnected role-assignment mechanisms (Spatie's real `assignRole()`/`syncRoles()`, which is the
  only one `backpack_user()->can(...)` ever reads; `RBACService::assignRole()`, which writes to an
  unrelated, currently-nonexistent-class table instead; and the 2 user importers, one of which never
  attempted role assignment at all and the other of which used a stale pre-switch role-slug map that
  always threw `RoleDoesNotExist` and rolled back the entire row). Full detail in
  `known-bugs-report.md` BUG-071/072.
- **User's decision (asked before fixing)**: the Employee's Designation IS the Spatie role — matches
  `StandaloneUsersImport`'s own docblock intent.
- **`StandaloneUsersImport.php`**: `createOrUpdateEmployee()` now returns the resolved designation
  code; added a new `syncUserRole()` step (step 6) that resolves `App\Models\IAM\Role::where('code',
  $desigCode)->first()` and calls `$user->syncRoles([$role])` — landing correctly in
  `xlr8_iam_model_has_roles` this time. Missing/unresolved designations log a warning and skip,
  rather than failing. Permission cache cleared once at the end of the batch import.
- **`Services/Importers/UserImporter.php`**: `assignRolesAndPermissions()` rewritten to drop the
  stale slug map and resolve the role via the already-looked-up `Designation`'s `code` the same way.
  A missing role now logs and returns instead of throwing (no longer silently rolls back the whole
  Person/Employee/User row).
- **Found, not fixed (BUG-072)**: `UserImporter` (`use App\Models\Core\Employee;`) references a
  class that doesn't exist — real model is `App\Models\Admin\Employee`. Also found the same
  `App\Models\Core\*` dead-namespace pattern in `RBACService::assignRole()`
  (`App\Models\Core\UserRoleAssignment` — real model is `App\Models\IAM\UserRoleAssignment`).
  Neither fixed — outside this investigation's scope, flagged so they're not lost.
- **Tests run**: both fixes verified against real, existing data in rolled-back transactions —
  `bmpl-0018` (employee `BMPL-0018`, real designation `CEO`) previously held the wrong Spatie role
  `"Accessories Fitter"`; after either fixed method runs, their role correctly becomes `"CEO"`.
  `php -l` clean on both files; `vendor/bin/pint --dirty --format agent` applied (style-only).
- **Not done**: no backfill was run for the ~170 existing users whose roles are currently wrong or
  missing from before this fix — only new/re-imports going forward are corrected. A backfill is a
  separate, larger data operation left for the app owner to explicitly request.

## RBAC data population — Module/Process/Permission linking + Role/User assignment seeders

Follow-up to the demo/roles and demo/users UI mockup: populated the real underlying data these
screens will eventually read from, via 3 idempotent seeders, all run against local.

- **Files**: `database/seeders/IamModuleProcessSeeder.php`, `database/seeders/UserRoleBackfillSeeder.php`,
  `database/seeders/RolePermissionSeeder.php`, `database/seeders/DatabaseSeeder.php` (registered all 3,
  after the existing `MasterDataSeeder`).

### IamModuleProcessSeeder

- Removed the 2 placeholder rows (`DEMO_MODULE`, `DEMOMODULE2`, `DEMO_PROCESS`) — confirmed nothing
  referenced them.
- Created 13 real `xlr8_iam_module` rows: the 11 established modules (SLS, ACC, FIN, INS, RTO, SPR,
  VEH, ORG, IAM, PRC, UTL) plus 2 new ones needed to categorize everything honestly — `SYS` (System:
  `admin.dashboard`, `admin.manage`, `audit.view`) and `LEGACY` (Legacy/Ungrouped, for permissions
  with no real owner — see below).
- Created 36 `xlr8_iam_process` rows across those modules, matching the abbreviations used
  throughout this whole rollout (BKNG=Booking, ENQR=Enquiry, RCPT=Receipt, BRCH=Branch, WKFL=Pricing
  Workflow, etc.). Hit and fixed a real schema constraint along the way: `xlr8_iam_process.code` is
  **globally** unique (not scoped per module), so a naive `GENERAL` process code per module collided
  — renamed to per-module codes (`FIN_GEN`, `INS_GEN`, `RTO_GEN`, `VEH_GEN`, `SYS_GEN`).
- Backfilled `module_code`/`process_code` on all 225 `xlr8_iam_permissions` rows: 211 mapped
  cleanly (either the new `MODULE_PROCESS_ACTIVITY`/`MODULE_ACTIVITY` convention, or an old
  lowercase `resource.action` permission mapped to the same module/process as its already-renamed
  new-convention replacement, e.g. `branch.view` → `ORG`/`BRCH`, since it's the same real resource,
  just an orphaned pre-rename row). 14 landed in `LEGACY`/`OTHER` — confirmed genuinely dead/
  unmapped: `foundation.*`, `post.*`, `user_type.*` (all confirmed unreachable controllers, per
  known-bugs-report.md BUG-015/024), the wildcard `*`, and `performance.view`.

### UserRoleBackfillSeeder

- One-time backfill of `xlr8_iam_model_has_roles` for every existing user, applying the same
  "Employee's Designation IS the Spatie role" fix already applied going-forward in
  `StandaloneUsersImport`/`UserImporter` (BUG-071) — this seeder is the retroactive pass for users
  who existed before that fix, explicitly flagged as pending in that earlier work.
- Result: 164 users updated to the correct role (e.g. `bmpl-0018`, employee `BMPL-0018`/CEO, was
  incorrectly holding "Accessories Fitter" — now correctly `CEO`), 0 already correct, 36 skipped
  (their employee's `designation_code` — `GM`, `MAN`, `CNS`, `SWD`, `API`, `TST`, `RTO`, `DSA` — has
  no matching row in `xlr8_admin_designation`, logged individually rather than guessed).
- `SUP001` (the SuperAdmin account, no `employee_code`) correctly excluded and untouched — still
  holds `superadmin`.

### RolePermissionSeeder

- Assigned a starting permission set to 63 of the 75 real (non-superadmin) roles via
  `role_has_permissions`; the other 12 (Driver, Electrician, Hostess, Housekeeping Executive,
  Hygiene Supervisor, Office Boy, Receptionist, Security Guard, Technician - Trainee, Test Drive
  Executive, Tool Incharge, Washing Boy) deliberately got none — no plausible admin-panel use.
- **This is an explicitly-flagged inferred starting point, not a business-verified access policy**
  — no access matrix for these 75 roles has ever existed in this app. Built from job-title semantics:
  each role maps to a `[module code => tier]` profile, where tier is `full` (every permission in
  that module), `basic` (`*_VIEW` + `*_CREATE` only), or `view` (`*_VIEW` only) — e.g. `CEO`/
  `Director`/`General Manager` get `full` across most modules; `Sales Consultant` gets `basic` on
  `SLS` only; `Driver` gets nothing. Documented prominently in the seeder's own docblock as needing
  the app owner's review via the Role UI (or the demo/roles screen, once it persists) — not to be
  treated as final.
- `superadmin` deliberately excluded — bypasses every check via `Gate::before()`, independent of any
  assigned permission.

### Verification

- All 3 seeders tested first in rolled-back transactions against real data before running for real.
- End-to-end chain confirmed live: `bmpl-0018` (role `CEO`) → `can('SLS_BKNG_VIEW')` → `true`,
  `can('PRC_RESET_MANAGE')` → `true` (full `PRC` tier); a user holding the `Driver` role (`bmpl-0509`)
  → `can('SLS_BKNG_VIEW')` → `false`, confirming the whole Designation → Role → Permission → Gate
  chain now actually works for real users, not just the manually-fixed `SUP001`/`bmpl-0018` test cases
  from earlier in the session.
- `demo/roles` and `demo/users` (the UI mockup) re-verified still rendering `200` after these changes
  — they use their own spoofed assignment JSON independent of this real data, unaffected either way.
- `php -l` clean on all 4 touched files. `vendor/bin/pint --dirty --format agent` applied (only
  removed a pre-existing unused `use App\Models\User;` import in `DatabaseSeeder.php`, unrelated to
  this change).
- Registered all 3 in `DatabaseSeeder::run()` (after `MasterDataSeeder`) so a fresh `php artisan
  db:seed` reproduces this state; all 3 are idempotent (`updateOrCreate`/`syncRoles`/
  `syncPermissions`), safe to re-run.

## Users Listing + View pages, user-level permission overrides, suspend/revoke/activate

Full feature per user request: a real Users listing (AG Grid, matching this app's established
list-page pattern) with the requested columns, a multi-card user View page, and the subsystems both
needed that didn't exist yet.

### New subsystems

- **`xlr8_iam_user_permission_denials`** (new migration + `App\Models\IAM\UserPermissionDenial`):
  the "removed" half of user-level permission overrides. Spatie natively supports "added" (direct
  grants via `givePermissionTo()`, on top of role permissions) but has no concept of revoking one
  specific role-granted permission from a single user without touching the role — this table is
  that missing half.
- **`User::permissionDenials()`/`deniesPermission()`/`permissionOverrides()`**: new relation and
  helpers on the `User` model, the latter returning `{added: [...], removed: [...]}` by diffing
  direct grants and denials against the role's own permissions.
- **`AppServiceProvider`**: extended the existing SuperAdmin `Gate::before()` (from BUG-070) to also
  deny access outright when a `UserPermissionDenial` exists for that permission. Found and fixed a
  real ordering bug while wiring this up (see BUG entry below) — Spatie's own `PermissionRegistrar`
  registers its own `Gate::before()` that returns `true` immediately whenever a user has a
  permission via role, and since Laravel's Gate stops at the first non-null "before" result, our
  callback (previously registered in `boot()`, same phase as Spatie's) never got a chance to deny
  anything the role already granted. Fixed by registering ours in `register()` via
  `$this->app->afterResolving(GateContract::class, ...)` instead of the `Gate` facade in `boot()` —
  since Laravel runs every provider's `register()` before any provider's `boot()`, this guarantees
  our callback attaches to the Gate first. Verified live: a `UserPermissionDenial` for
  `SLS_BKNG_VIEW` on a CEO-role user now correctly makes `can('SLS_BKNG_VIEW')` return `false`
  (previously stayed `true` despite the denial); an unrelated permission (`SLS_ENQR_VIEW`) is
  unaffected; the SuperAdmin bypass and a made-up permission string both still work correctly.
- **Employment History**: discovered `xlr8_admin_employee_history` + `App\Models\Admin\
  EmployeeHistory` already existed (migration batch 22, well before this session) with a solid
  snapshot-plus-effective-date-range design, but had 0 rows and had never been queried (see BUG-073
  — fixed a real bug in the model to make it usable at all). Added `database/seeders/
  EmployeeHistorySeeder.php`, run for real: seeded one initial "joining" record per employee (200
  total) from their current org info, so the Employment History card has real content instead of
  always being empty. The write-side workflow for recording *future* changes (transfer, promotion,
  scope change, etc.) is not built — flagged as a separate future task, not attempted here.

### `UserCrudController` — real `index()` and `show()`, new suspend/revoke/activate actions

- **`index()`**: replaces reliance on Backpack's default table (which can't easily join
  Designation/Branch/Location/Department/Division/Vertical/Segment names — they live on the related
  Employee/UserScope rows, not on `users` itself) with a hand-rolled AG Grid view, matching the
  established pattern used by every other custom-grid entity in this app (Branch, Employee, Booking,
  ...). Columns: S.No., User Type, Username, Mobile, Email, Designation, Branch, Location,
  Department, Division, Vertical, Segment, Role, Status, Actions (View/Edit/Suspend or
  Activate/Revoke, matching current state).
- **`show($id)`**: 5 cards — Personal Info (name, username, user type, all mobiles/emails with
  Primary vs. addon badges), Org Info & Scoping (Designation, then Department/Division/Branch/
  Location/Segment/Sub Segment/Vehicle Models/Vehicle Variants/Vertical, each showing Primary +
  Additional — Primary comes from the Employee's own `primary_*_code` columns where one exists,
  Additional from `xlr8_admin_user_scopes` excluding the primary value; Vehicle Model/Variant have no
  primary column on Employee at all, so those two are additional-only), Permissions (role name,
  effective permission list, added/removed override badges, struck-through for removed), Banking
  Details (masked account numbers), Employment History (from the new seeder above).
- **`suspend($id)`**: sets `is_active = false`, keeps the role — a reversible hold.
- **`revoke($id)`**: sets `is_active = false`, strips the role (`syncRoles([])`), clears all direct
  permission grants and denials — a hard cutoff for someone who has left; reactivating afterwards
  does not restore the role automatically (must be reassigned via Edit).
- **`activate($id)`**: sets `is_active = true` only.
- Both `suspend`/`revoke` refuse to act on a SuperAdmin account (`$user->isSuperAdmin()` check),
  verified live.
- **`setupListOperation()` simplified**: Backpack's `CrudController` dispatches this hook
  automatically for any route tagged `'operation' => 'list'` (both `org.user.index` and
  `org.user.search` carry that tag from the earlier Org batch 2 work) — regardless of which method
  actually ends up handling the request. The old hook body (Backpack-table column/filter setup,
  entirely unused now that `index()` builds its own grid) included a PRO-only `dropdown` filter type
  (BUG-014) that threw `BackpackProRequiredException` on every single page load once `index()` had
  its own implementation — the hook still ran first and crashed before `index()`'s body was ever
  reached. Stripped the hook down to just the permission check.
- **Routes**: added `POST org/user/{id}/suspend`, `/revoke`, `/activate` in `routes/backpack/core.php`
  (each with its own inline permission check, `'operation'` key included for consistency though not
  strictly required since none rely on hooks).

### Bugs found and fixed along the way (not part of this feature's direct scope, but blocking it)

- **BUG-073**: `EmployeeHistory` extended `BaseModel` (forces `SoftDeletes`) but its table has no
  `deleted_at` column — every query fatal. Fixed by extending plain `Model` instead.
- **BUG-074**: `Employee::getDesignationCodeAttribute()`/`getDesignationNameAttribute()` had their
  entire intended logic written as a literal array-index string instead of real PHP — always
  returned `null`. Fixed both accessors. This is a **critical, high-blast-radius fix** — every other
  part of the app reading `$employee->designation_code`/`designation_name` via Eloquent (not raw
  `DB::table()` queries) was silently getting `null` or falling through to the legacy `desig_code`
  column before this fix.

### Verification

- All 3 new/fixed model-level pieces tested in rolled-back transactions against real data before
  anything ran for real: permission denial ordering fix, `EmployeeHistory` querying, `Employee`
  designation accessors.
- `EmployeeHistorySeeder` run for real: 200 records seeded.
- Full HTTP-kernel round trip (real login, not rolled back, since these are read-only page loads):
  `admin/org/user` (list) → `200`, content includes real usernames, real `"designation":"CEO"` JSON
  data, and the new action buttons; `admin/org/user/{id}/show` for `bmpl-0018` → `200`, content
  includes "CEO", "Employment History", "Personal Info", "Org Info", "Banking Details",
  "Permissions", zero PHP warnings/errors in the response body.
- Suspend/revoke/activate tested end-to-end in a rolled-back transaction against a real user
  (`bmpl-0011`): suspend → `is_active=0`, role kept; revoke → `is_active=0`, role cleared; activate →
  `is_active=1`, role still cleared (as designed — not auto-restored). SuperAdmin protection
  confirmed: attempting to suspend `SUP001` leaves `is_active` unchanged.
- Permission gate re-confirmed: a user with `ORG_USER_VIEW` stripped gets `403` on the list page.
- `php -l` clean on all 11 touched/created PHP files. `vendor/bin/pint --dirty --format agent`
  applied (import ordering only, re-verified pages still `200` after).

### Explicitly not done, flagged for later

- **Create and Edit are still broken** (BUG-040/BUG-014, pre-existing, unrelated to this feature) —
  the "New User" button and each row's "Edit" button link to the real `org.user.create`/
  `org.user.edit` routes, but submitting either form will not currently work. Matches the user's own
  framing ("we will add the logic of this later" for Create); Edit was not explicitly called out the
  same way but is in the identical broken state and was not rebuilt here, since a full Create/Edit
  rebuild (real column mapping — `users` has no `code`/`name`/`person_id`/`employee_id` columns the
  existing form logic assumes) is a separate, large task of its own.
- **No write-side workflow exists yet for recording new Employment History entries** going forward
  (transfer, promotion, demotion, additional charge, scope change) — only the initial backfill was
  seeded. Building that (closing the current open record's `effective_to` and inserting a new one
  whenever org info changes) is a separate future task.
- **`RBACService::assignRole()`'s wrong class path** (BUG-071's note) and **`UserImporter`'s wrong
  `Employee` namespace** (BUG-072) remain open — neither blocks this feature, both already flagged
  separately.

## "Continue and complete": real Create/Edit forms (BUG-040/BUG-014), RBACService/UserImporter cleanup

Direct follow-up closing the gaps flagged at the end of the previous entry.

### `RBACService::assignRole()` (BUG-071) — now fully fixed, not just the class path

- Fixed the wrong class reference (`App\Models\Core\UserRoleAssignment` → `App\Models\IAM\
  UserRoleAssignment`) noted but not yet fixed last entry. Went further: the method previously only
  wrote to that temporal history table, which nothing in the permission-checking path reads — a real
  no-op for actual access control even once the class path was fixed. Now also calls
  `$user->assignRole($role)` (Spatie's real, additive grant) before writing the history record, so
  calling this actually grants access. Chose additive (not `syncRoles`) since this method's shape —
  explicit `$role` + optional date range — reads as "temporary additional charge" (a second role
  layered on top of the person's primary one), not a full designation change (which goes through the
  new Edit form's single-role `syncRoles()` path instead). Verified live in a rolled-back
  transaction: an Accounts Executive granted "Web Developer" via this method gains `IAM_RBAC_VIEW`
  while keeping their original role.

### `UserImporter` cleanup (BUG-072, BUG-075 found)

- Deleted `app/Imports/UserImporter.php` outright — confirmed byte-identical (structurally) to the
  live `app/Services/Importers/UserImporter.php`, unreferenced anywhere, and per BUG-072 already
  broken with the pre-fix imports.
- Fixed the real, live `Services/Importers/UserImporter.php`'s remaining wrong `App\Models\Core\*`
  imports (`Department`, `Location`, `Vertical`, `UserType` → their real `App\Models\Admin\*`
  locations). `Post` has no real backing model or `Employee::posts()` relation anywhere in the
  codebase (confirmed — ties to the already-documented dead Post/EmpPostAssignment cluster,
  BUG-015/024) — rather than inventing a replacement, that one assignment step now logs and skips.
  Also fixed `lookupUserType()`'s field names (`where('name', ...)`/`create(['name' => ...])` — real
  `xlr8_iam_user_type` columns are `code`/`display_name`, not `name`).
- **Found, NOT fixed (BUG-075)**: `createOrUpdateUser()` builds its `$userData` array entirely out of
  nonexistent `users` columns (`personid`, `employeeid`, `usertypeid`, `code`, `name`, `email`,
  `isactive`) — none of these exist on the real table (`person_code`, `employee_code`,
  `user_type_id`, `username`, `is_active`; there's no `email`/`name`/`code` column on `users` at
  all). This means the importer has never successfully created or updated a real user record,
  independent of every other fix in this and the prior entry. Full rewrite of
  `createOrUpdatePerson()`/`createOrUpdateEmployee()`/`createOrUpdateUser()` against the real schema
  is a separate, large task — the feature is also already unreachable via the UI regardless (BUG-043,
  missing views) — documented, not attempted here.
- **Found, NOT fixed**: `RBACService::getAccessibleResources()`/`getModelClassForResourceType()`
  reference more nonexistent `App\Models\Core\*` classes and nonexistent relations
  (`$user->employee->posts`, `$user->userRoleAssignments`) — confirmed unreachable from any
  controller (BUG-076, documented only).

### Real Create/Edit forms for Users (BUG-040, BUG-014)

- **`UserRequest`** rewritten with real validation against the actual schema (previously empty rules
  entirely) — `username` (unique), `password` (required on create, optional on update),
  `user_type` (`Emp`/`Associate`, the only 2 real values in the data), `role_id`, and on update only:
  the 8 Employee org-info fields plus `change_reason`/`effective_date`/`remarks`.
- **`UserCrudController::create()`/`store()`**: hand-rolled form (`resources/views/admin/user/
  create.blade.php`) — link an existing Employee (auto-fills `person_code`) or a non-employee
  Person, or go standalone; username/password/user_type/role/is_active. No PRO fields.
- **`UserCrudController::edit()`/`update()`**: hand-rolled form (`resources/views/admin/user/
  edit.blade.php`) — account fields + single-role select, plus (if linked to an Employee) org info
  editing for Designation/Branch/Location/Department/Division/Vertical/Segment/Sub Segment. A JS
  guard shows a "reason for change" block only once any org field actually differs from its loaded
  value; the server independently re-validates this (changing org info with no `change_reason`/
  `effective_date` redirects back with a validation error and touches nothing). When a change is
  submitted, `recordEmployeeHistory()` closes the Employee's current open `EmployeeHistory` record
  (`effective_to` = day before the new `effective_date`) and inserts a new one — the write-side
  counterpart to `EmployeeHistorySeeder`'s one-time backfill from the previous entry.
- **`setupCreateOperation()`/`setupUpdateOperation()`** gutted to just their permission checks —
  same reasoning as the `setupListOperation()` fix from the previous entry: Backpack still dispatches
  these hooks automatically based on the route's `'operation'` tag, so the old PRO-dependent
  field-setup code was still running (and would still crash) even though `create()`/`store()`/
  `edit()`/`update()` no longer use it.
- **Not changed**: `destroy()` still calls the broken `parent::deleteCrud()` — Delete wasn't part of
  the Users feature request, and the real "remove access" need is already covered by Suspend/Revoke
  from the previous entry (which don't go through this code path).

### Verification

- All 3 fixes (RBACService, UserImporter imports, Create/Edit) checked for syntax and tested in
  rolled-back transactions against real data before anything ran for real.
- HTTP-kernel round trip: `admin/org/user/create` → `200` (correctly shows a warning that every
  employee already has a login, since all 200 do in this dataset — confirmed this is accurate, not a
  bug); `admin/org/user/{id}/edit` → `200`, contains real data (`CEO`, the org-change JS block).
- End-to-end `store()` test: freed up one employee (`BMPL-0018`) by deleting their existing user,
  submitted a full create payload — new user created with the correct `employee_code`/`person_code`
  auto-filled from the employee, correct role assigned, password correctly hashed and verifiable.
- End-to-end `update()` test: submitting an org-info change (`primary_branch_code`) with no
  `change_reason` correctly redirects back with a validation error and leaves the Employee row
  unchanged; submitting the same change with a reason correctly applies it AND writes exactly one new
  `EmployeeHistory` row while closing the previously-open one (confirmed exactly 1 row remains with
  `effective_to = null` afterward).
- `php -l` clean on all 4 touched PHP files. `vendor/bin/pint --dirty --format agent` applied
  (import ordering only); re-verified all 4 pages (`list`, `show`, `create`, `edit`) still return
  `200` after.

## Designation CRUD: code immutability, dependency guard, media, role-permission management (Org CRUD phase, entity 1/N)

Per the user's spec: `code` must never change after creation (every relation points at org
entities by code, not id); disabling an entity must be blocked while it has active dependents;
every entity should support image/document upload via its inherited BaseModel media collections;
and — added mid-task — the Designation edit page (Designation doubles as a Spatie role) must expose
the same Module → Process → Permission tree UI built earlier for `demo/roles`, wired to real
persistence this time. A further mid-task instruction ("add all business logic in services... for
SSOT and DRY") drove moving all of this out of the controller into a service layer, which is now
the template for the remaining 5 entities (Department, Division, Vertical, Branch, Location).

- **New: `app/Services/IAM/PermissionTreeService.php`** — the Module/Process/Permission tree builder
  extracted verbatim from `DemoRbacController`'s private methods (`buildTree()`/`flattenCodes()`/
  `parsePermissionName()`), so the real Designation permissions panel and the `demo/roles` mockup
  share one source of truth instead of two parsers of `xlr8_iam_permissions.name`.
- **New: `app/Services/IAM/RolePermissionService.php`** — thin wrapper around Spatie's
  `syncPermissions()`/`permissions()` for any `Spatie\Permission\Contracts\Role` model, so
  controllers/blades never call Spatie's API directly.
- **New: `app/Services/Org/DesignationService.php`** — single source of truth for Designation
  business logic: `create()`/`update()` (code stripped from the update payload before it ever
  reaches the model; `rank`/`guard_name` defaults), `syncPermissions()`/`currentPermissionCodes()`,
  and private `validateReportsTo()`/`syncMedia()`. `update()` returns `{ok:false, blockers:[...]}`
  instead of saving when disabling would orphan active dependents.
- **New: `app/Services/Org/OrgEntityGuard.php`** — reusable "does this code have active dependent
  rows" check, shared across all 6 planned org entities. Each dependent entry is
  `[ModelClass, foreignCodeColumn, label?, activeColumn?, activeValue?]` — the last two exist
  because not every dependent uses an `is_active` boolean (Employee has no such column; see
  BUG-077 below).
- **New: `app/Http/Controllers/Admin/Org/Traits/ManagesOrgEntityCrud.php`** — shared controller
  helpers (`stripImmutableCode()`, `handleImageUpload()`, `handleDocumentUploads()`,
  `handleImageRemoval()`) for the remaining 5 entities' controllers, which won't get their own
  dedicated service class unless they need entity-specific logic beyond this.
- **New: `resources/views/admin/org/partials/media-fields.blade.php`** — shared image + documents
  upload/remove UI block (`$imageCollection`, `$model` nullable on create), used by Designation's
  create/edit views and intended for the remaining 5 entities.
- **`app/Models/Admin/Designation.php`**: added a `'documents'` media collection to
  `registerMediaCollections()` — Designation extends Spatie's `Role`, not `BaseModel`, so unlike the
  other 5 org entities it did not inherit `BaseModel`'s `documents`/`photos`/`attachments`
  collections for free; only `designation_image` existed before.
- **`app/Http/Controllers/Admin/Org/Designation/DesignationCrudController.php`**: rewritten to
  delegate to `DesignationService`/`PermissionTreeService` via constructor injection. All permission
  checks changed from the old `ORG_DESG_VIEW/CREATE/EDIT/DELETE` tiers to the new blanket
  `ORG_ENTITY_MANAGE` permission (minted this session, id 233), per the user's explicit spec. Added
  `updatePermissions(Request $request, $id)` — validates `permissions: array<string>`, calls
  `DesignationService::syncPermissions()`, returns JSON. `index()`'s grid now includes an `image`
  column (thumbnail or em-dash).
- **`routes/backpack/core.php`**: added `PUT org/designation/{id}/permissions` →
  `DesignationCrudController@updatePermissions` (route name `org.designation.permissions`),
  outside the generic per-entity route loop since it's Designation-specific for now.
- **`resources/views/admin/designation/edit.blade.php`**: code field is now read-only/disabled with
  an explanatory `form-text` (was a live-editable input with JS uppercasing — removed); replaced the
  ad-hoc image-only upload block with `@include('admin.org.partials.media-fields', ...)`; added a
  full "Permissions" card reusing `demo.partials.tree` + `public/js/demo-rbac.js`'s `RbacTree`
  controller, with its own Expand/Collapse/Select-all/Clear-all controls and a `fetch()`-based save
  button posting to the new `org.designation.permissions` route; "Reports To" options now show
  `— Rank {{ $desig->rank_label }}` and carry `data-rank`, with a form-text hint explaining the new
  same-or-higher-rank rule.
- **`resources/views/admin/designation/create.blade.php`**: same media-partial swap; same "Reports
  To" rank-label/hint addition; removed the now-dead image-preview JS (the shared partial has no
  JS-driven preview, by design — simpler and consistent across entities).
- **New business rule — reporting-to rank hierarchy**: `DesignationService::validateReportsTo()`
  throws a `ValidationException` (→ redirect back with `parent_desig_code` error, same as any other
  form validation failure) when the selected parent's `rank` is numerically greater (i.e. actually
  lower-ranked — rank 1/A is highest, 5/E is lowest) than the designation's own rank. Runs in both
  `create()` and `update()`, before the code-immutability/dependency-guard logic.
- **`phpunit.xml`**: fixed 3 latent, pre-existing breakages that made `php artisan test` unusable for
  anyone (see BUG-078): `DB_DATABASE` was `xlrn` (typo for the real local DB `xlrm`), no `APP_URL`
  override (so every `backpack_url()`/`url()` call in a test baked in the dev box's `/xlrm/public`
  subpath, which doesn't exist in the test client's routing), and `APP_KEY` was still the literal
  placeholder string `base64:REPLACE_WITH_YOUR_APP_KEY` (fails any code path touching the encrypter,
  e.g. session cookie signing during `actingAs`). Generated a real throwaway key for the `testing`
  environment only.
- **New: `tests/Feature/Admin/Org/DesignationCrudTest.php`** — 7 feature tests covering every new
  decision: code immutability on update, dependency-guard block/pass (with a real Employee+Person
  fixture), permission-gate denial, the new rank-hierarchy validation (block + pass), and permission
  sync via the new endpoint. All pass. Required a `actingAsBackpackUser()` test helper instead of the
  framework's own `actingAs($user, 'backpack')` — see BUG-079.

### Verification

- `php -l` clean on all new/changed PHP files. `vendor/bin/pint --dirty --format agent` applied
  (Designation.php, OrgEntityGuard.php — import ordering/brace-position only).
- Direct `DesignationService` exercise in a rolled-back tinker transaction confirmed: permission sync
  round-trips through real `syncPermissions()`/`currentPermissionCodes()`; disabling a designation
  with zero active-employee dependents succeeds; the `title_case` column transformation (pre-existing,
  unrelated to this change) explains an initial apparent name-mismatch in manual testing — not a bug.
- `php artisan test --filter=DesignationCrudTest` → 7 passed, 20 assertions.
- `php artisan test` (full suite) → pre-existing failures unrelated to this work surfaced now that
  the suite can actually connect to a database for the first time; see findings doc and BUG-078/080.
