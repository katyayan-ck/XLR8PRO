# Convention Inference — First Inspection Report

Generated via the `infer-conventions` skill. Read-only research; no application code was
modified. Scope: controllers, services, RBAC, models, database, jobs/imports, Backpack CRUD
usage, Media Library usage. Five parallel research passes were run against the live codebase
and cross-checked against the rules already recorded in `.ai/rules/`.

**Status as of the second pass: 26 of the 26 pattern candidates in §1 have been recorded via
`record-rule` (approved in full, including the mass-assignment conflict with its proposed
boundary). See §4 "Recording log" for the complete list of what landed where. Everything in §0
(documentation-vs-reality conflicts, dead code, possible auth/enforcement gaps) is still
unrecorded and awaiting your decision — recording those would just add a second, contradictory
document on top of the existing `.ai/rules` files that already describe (incorrectly) how those
areas work.**

---

## 0. Critical findings — documentation vs. reality

These are not style questions; they're places where an already-recorded `.ai/rules` file
asserts something the code doesn't actually do. Recording a new rule over these would just add
a second, equally wrong document, so they're called out separately for a decision.

### 0.1 `checkPermission` middleware is registered but never used
`bootstrap/app.php` registers a `checkPermission` alias, and `.ai/rules/rbac-scopes.md` documents
`permission:MODULE_PROCESS_ACTIVITY` as the route-level enforcement mechanism. A repo-wide grep
for `checkPermission:` and `permission:` in route files returns **zero matches**. As far as this
sweep can tell, no route is actually gated by this middleware.

### 0.2 Backpack Admin panel has no uniform access-control layer
Across all 58 `CrudController`s under `app/Http/Controllers/Admin/`:
- No shared base class (`App\Http\Controllers\Admin\BaseCrudController` or similar) exists — every
  controller extends Backpack's `CrudController` directly.
- 0 files call `$this->middleware('permission:...')` in `setup()`.
- 0 files call `CRUD::denyAccess()`.
- Only `UserCrudController.php` does manual `backpack_user()->can('user.view'|'user.create'|'user.edit')` checks (lines 90, 165, 274).
- `BookingCrudController.php` / `PostCrudController.php` sprinkle `$this->crud->hasAccessOrFail(...)` inside custom action methods — generic Backpack API usage, not a designed convention.
- Route-group middleware (`routes/backpack/core.php:18-20`) is only generic `web`/`admin`, not permission-specific.

Combined with 0.1, this reads as a genuine authorization gap in the admin panel rather than an
inconsistent style choice — worth engineering attention, not just documentation.

### 0.3 `SheetHeaderService` + `SynonymService` are not used by any importer
`.ai/rules/services.md` and `.ai/rules/known-pitfalls.md` (P-17) both state header/synonym
resolution through these services is mandatory for every importer ("never hardcode header
labels"). Actual code: **zero** matches for either service under `app/Imports/**`. Instead:
- `app/Imports/Sheets/BranchSheet.php:11-19`, `DepartmentSheet.php`, `DesignationSheet.php`,
  `ModelSheet.php`, `SegmentSheet.php`, `SubSegmentSheet.php`, `VerticalSheet.php`,
  `StandaloneUsersImport.php` — all hardcode a `protected array $columnMap = [...]`.
- `app/Imports/Sheets/EmployeeSheetImport.php:33-40` hardcodes a `private const COL = [...]`.
- `app/Jobs/ImportEnquiriesJob.php:1003-1016` matches headers by exact string via its own
  `getSheetHeaderMap()`, with no synonym resolution at all.

11+ files deviate from the documented rule; 0 files follow it.

### 0.4 `DocService` is not the sole Media Library consumer
`.ai/rules/services.md` states `DocService` is SSOT for document management and that ad-hoc file
columns/collections should never be created outside it. In reality, **13 live models** register
and manage their own media collections directly, independent of `DocService`/`DocGroup`:
`BaseModel.php:117` (base `documents`/`photos`/`attachments` collections inherited by ~113
models), `app/Models/Admin/{Branch,Department,Designation,Division,Location,Vertical,Person}.php`,
`app/Models/Module/Booking/{XlInsurer,XlRto,XlDelivery,Xl_Refunds,Xl_DSA_Master,Bookingamount}.php`,
`app/Models/Module/{Insurance/XlInsurer,Rto/XlRto,Finance/XFinance}.php`, `app/Models/CRM/Quotation.php`.
These are driven ad hoc from Backpack CrudControllers (e.g. `BookingCrudController.php` calls
`clearMediaCollection()` directly ~10 times).

### 0.5 Documented validation convention doesn't match reality
`.ai/rules/conventions.md` §6 shows `FormRequest` classes as the mandatory validation path for
controllers. In practice:
- API controllers validate inline: `$request->validate([...])` inside a try/catch (e.g.
  `AuthController.php:115,195`, `NotificationController.php:97,203,1164`,
  `PricingApiController.php:20`, `UserImportExportController.php:23,74`).
- Backpack CrudControllers validate inline too: `$request->validate([...])` inside overridden
  `store()`/`update()` methods (49/58 controllers, e.g. `BrandCrudController.php:93-97`,
  `ColorCrudController.php:112`, `DepartmentCrudController.php:87`).
- The 34 `FormRequest` classes under `app/Http/Requests` are referenced only 4 times total, and
  only from `app/Http/Controllers/Admin/**`.
- `CRUD::setValidation(SomeRequest::class)` appears in only 4 files as apparent leftover
  scaffolding (Booking, DashboardControllerCrudController, SystemSetting, UserCrudController),
  not as the dominant path.

### 0.6 Documented API response envelope doesn't match reality
`.ai/rules/conventions.md` §7 specifies `{success, message, data}`. Actual behavior:
- `BaseController.php:52-99,112-136` provides `successResponse()`/`errorResponse()` producing a
  richer `{http_status, success, code, message, timestamp, data}` shape, used consistently by
  `AuthController` and `NotificationController`.
- `ExportController.php` (extends plain `Controller`, not `BaseController`) returns ad hoc
  `{error: ...}` on failure and raw file downloads on success — no envelope at all.
- `PricingApiController.php:32` returns a bare `{'success'=>true, 'difference'=>$gap}`.
- `JsonResource` usage is partial: some list/collection endpoints use `AlertResource`/
  `MessageResource`/`NotificationResource`, others return raw Eloquent models directly
  (`NotificationController.php:137,233,371`).

### 0.7 Documented Admin CrudController directory layout doesn't match reality
`.ai/rules/architecture.md` §4 mandates `Admin/{Module}/{Process}/` nesting for CrudControllers.
Reality: all 58 CrudControllers sit **flat** directly in `app/Http/Controllers/Admin/` (e.g.
`BranchCrudController.php`, `LeadCrudController.php`, `BookingCrudController.php`). The only
subdirectories are `Admin/Pricing/` (6 plain, non-CRUD controllers) and `Admin/Traits/`
(`ScopedCrud.php`).

### 0.8 Dead/unused code found during the sweep
- `app/Models/Traits/HasAuditFields.php` — duplicates `BaseModel`'s actor-stamping
  create/update/delete logic exactly, but is never `use`d by any live model. Superseded, vestigial.
- `app/Models/Traits/ScopedQuery.php` — defines `bootScopedQuery`/`withoutDataScope` for
  multi-tenant filtering, but is never `use`d anywhere in `app/`. `.ai/rules/rbac-scopes.md` and
  `services.md` describe `withoutDataScope()` as a real, load-bearing pattern for jobs — worth
  checking whether jobs are actually unscoped some other way, since this trait isn't wired in.
- `app/Imports/Listeners/AfterImportListener.php` — 0 bytes, unreferenced. All "after import"
  handling actually happens inline via each importer's own `registerEvents()`.
- `app/Models_backup/**` — a full stale mirror of `app/Models/**` (140 files), last touched in an
  old merge commit ("Rady to merge with AnuragRR"). Appears abandoned; noise for any future sweep.

**Next-step options for section 0:** (a) update the relevant `.ai/rules` files to describe actual
practice, (b) leave those docs as an aspirational target and record nothing that contradicts them,
(c) investigate 0.1/0.2/0.3 as potential real defects (permission bypass, header-matching
fragility) independent of documentation, (d) delete the dead code in 0.8 after confirming it's
truly unreachable, (e) do nothing yet.

---

## 1. Recordable pattern candidates (evidence-backed, ready for `record-rule` if approved)

### Controllers & validation — `app/Http/Controllers/Api/**`
- **Typed input retrieval.** Raw `$request->input()`, dynamic properties, or the `validate()`
  return array — never `$request->string()/integer()/enum()`. Evidence:
  `NotificationController.php:557-573`, `PricingApiController.php:27-29`,
  `UserImportExportController.php:82-93`.
- **Route model binding.** Explicit `Model::findOrFail()` / `firstOrBuilder()->firstOrFail()`
  inside the method body, not implicit type-hinted route-model binding. Evidence:
  `NotificationController.php:1171,1253,1287,1402,1488,1575`.
- **Authorization call site.** Manual ownership checks (`auth()->id()` vs. resource owner field)
  in the method body, or route middleware — not `$this->authorize()`/`Gate::`/`->can()`/`@can`,
  despite `BaseController` exposing an unused `authorize()`/`canPerform()` helper
  (`BaseController.php:316-341`, never called). Evidence: `NotificationController.php:1255,1404,1490,1577`.
- **Route handler style.** `[Controller::class, 'method']` array syntax in `routes/api.php`;
  closures are reserved for one-off `web.php`/backpack routes only.

### Models & Eloquent — `app/Models/**`
- **Extend `BaseModel`, not `Model`, for all domain models.** 113/~127 concrete models do this
  already; the 14 exceptions are pivot/assignment/audit-log tables
  (`EmployeeBranchAssignment.php`, `ExportLog.php`, `Person.php`). `BaseModel` centralizes:
  `SoftDeletes`, `InteractsWithMedia` (base `documents`/`photos`/`attachments` collections on
  `public` disk), `CrudTrait`, `HasColumnTransformations`, `HasFactory`; `$guarded=['id']`
  default; `created_at/updated_at/deleted_at` + `extra_data`/`is_active` casts; ISO8601 date
  serialization; actor-stamped soft delete (`deleted_by`) in a single `booted()`. This is the
  single highest-value finding of the sweep.
- **Accessors/mutators.** Legacy `getXxxAttribute()`/`setXxxAttribute()`, not the `Attribute`
  class — a deliberate hold against the modern form (no Rector installed to force migration).
  20 files use legacy getters, 5 legacy setters; only 1 file (`CRM/Enquiry.php:141-183`) uses
  `Attribute::make()`.
- **Custom casts.** Override `protected function casts(): array` and merge with
  `parent::casts()` — never add a competing `$casts` property, which would silently fail to
  merge with `BaseModel`'s casts. Evidence: `Admin/Branch.php:67-75`, `IAM/Module.php:25`,
  `Vehicle/Pricing/Addon.php:40` (19 files total).
- **Query scopes.** Plain local `scope*` methods on the model itself; don't duplicate
  `BaseModel`'s existing generic scopes (`scopeActive`, `scopeInactive`, `scopeDateRange`,
  `scopeCreatedBy`, `scopeUpdatedBy`, `scopeDeletedBy`, `scopeNewest`, `scopeOldest` —
  `BaseModel.php:142-195`). No `#[Scope]` attribute or dedicated query-builder classes in use.
- **Model events.** No observers, no `#[ObservedBy]` — actor-stamping is centralized in
  `BaseModel::booted()`. Don't add per-model `booted()` logic or a second soft-delete-actor trait.
- **Reusable traits (legitimate).** `HasColumnTransformations` (used app-wide via `BaseModel`),
  `HasCommunications` (5 models, matches `services.md`), `HasTreeStructure` (2 models, hierarchy
  views) are real, safe-to-reuse abstractions. (`HasAuditFields` and `ScopedQuery` are dead — see 0.8.)

### Mass assignment — conflict with an identified boundary (present for confirmation, not yet recorded)
`$fillable` allow-lists are the norm across 126 files. A cluster of ~28 files under
`app/Models/Module/{Booking,Exchange,Finance,Insurance,Rto,Spare}/**` instead leaves
`$fillable = []` and re-declares `$guarded = ['id']` (e.g. `Module/Booking/Booking.php:27-28`).
Proposed framing if confirmed: new domain models use explicit `$fillable`; the
`app/Models/Module/**` family's `$guarded` pattern is legacy and shouldn't be copied for new code.

### Jobs — `app/Jobs/**`
- **Explicit `timeout`/`tries` sized to workload**, never left at queue defaults. Evidence:
  `ImportEnquiriesJob.php:18-19` (1800/1), `Vehicle/Pricing/ImportPriceListsJob.php:28-29`,
  `DetectPricingWorkbookJob.php:29-30`, `CalculatePricingSessionJob.php:33-34` (all 1800/1),
  `RecalculateVehiclePricingJob.php:26-27` (120/2, shorter timeout + more retries for the
  per-vehicle batch job).
- **`Bus::batch()` fan-out shape** for "N sub-jobs from a session" pipelines:
  `CalculatePricingSessionJob` builds a batch of `RecalculateVehiclePricingJob` instances with
  `->allowFailures()->then()->catch()->dispatch()`
  (`Vehicle/Pricing/CalculatePricingSessionJob.php:108-139`); the child job uses `Batchable` and
  checks `$this->batch()?->cancelled()`. Only one site, but a deliberate, well-formed pattern
  worth reusing rather than reinventing.
- **`failed()` handling for long-running/session-tracked jobs** — flip cache/session status to
  failed rather than letting failures pass silently. 3 of 5 pricing jobs implement `failed()`
  (`ImportPriceListsJob.php:110-119`, `DetectPricingWorkbookJob.php:128-137`,
  `RecalculateVehiclePricingJob.php:75-78`); the other two catch `\Throwable` inline instead.
- **Dispatch style.** Plain `Job::dispatch()` from controllers; `dispatchSync()` only when a job
  needs to synchronously invoke another job from inside its own `handle()` (e.g.
  `ProcessPricingWorkbookJob.php:49,60`, a compatibility wrapper).

### Imports — `app/Imports/**`
- **No single Maatwebsite interface shape — four families coexist by domain.** Check which
  family a target domain already uses before extending: `ToCollection + WithHeadingRow +
  WithChunkReading + WithEvents` (`VehicleInfoImport.php`), `OnEachRow + WithHeadingRow +
  WithEvents + SkipsEmptyRows + SkipsOnError` (`VehicleAccessoriesImport.php`),
  `WithMultipleSheets + WithEvents` delegating to per-sheet classes (`EmployeeMasterImport.php`,
  `RbacMasterImport.php`), and plain non-Maatwebsite `BaseSheetImport` subclasses fed raw arrays
  (`Imports/Sheets/*.php`). Don't introduce a fifth shape; match the nearest existing family.
- **`Imports/Listeners` is dead** (see 0.8) — put `BeforeImport`/`AfterImport` handling directly
  in the importer's own `registerEvents()`.
- **Error handling.** Two internally-consistent idioms, both plain-array + `Log` facade (no
  custom exception types, no error DTOs): the `BaseSheetImport` family accumulates
  `$inserted/$updated/$skipped/$errors` counters plus `$errorMessages` via `recordError()`
  (`Imports/Sheets/BaseSheetImport.php:13-19,109-115`); `ImportEnquiriesJob`'s per-sheet handlers
  use a local `$stats` array with `catch (\Throwable $e)` incrementing `skipped` and logging per
  row (repeated identically across 9 sheet handlers).

### Backpack CRUD — `app/Http/Controllers/Admin/**`
- **Operations.** `List + Create + Update + Delete` is the default quartet (49/58 controllers,
  e.g. `BrandCrudController.php:13-16`). `Show` operation is never used anywhere (0 matches) —
  don't add `setupShowOperation` unless a specific need arises. Reorder/Clone/BulkDelete/Fetch/
  InlineCreate appear only once, in `ReportingHierarchyCrudController.php`, for a tree view.
- **Field/column DSL is the exception, not the rule.** Only 2/58 controllers use `CRUD::field()`/
  `CRUD::column()` at all. The other 56 bypass the DSL entirely: 53 override `index()`, 42
  override `store()`, 46 call `setListView()/setCreateView()/setEditView()` to point at custom
  Blade views (e.g. `BrandCrudController.php:27,79,108` → `admin.brand.list/edit`), building
  grid/form manually via `$gridConfig` arrays and manual `Model::select()->get()->map()`. When
  the DSL *is* used, it's fluent syntax (`CRUD::field('x')->type(...)`), not array syntax.
- **`ScopedCrud` trait for row-level data scoping.** `app/Http/Controllers/Admin/Traits/ScopedCrud.php`
  wraps `setupListOperation` to add branch/location/department query clauses via
  `$this->crud->addClause(...)`; used by 4 controllers (Branch, Finance, Insurance, Rto). Reuse
  this trait rather than duplicating scoping logic — implement `getScopeType()`.

### Media Library — `app/Models/**`, `app/Services/**`
- **Collection naming (two established shapes, keep using them, don't add a third casing
  variant).** `singleFile()` entity images: `{entity}_image` (`designation_image`,
  `department_image`, `division_image`, `vertical_image`, `branch_image`, `location_image`, all
  in `Admin/*.php`). Proof/attachment collections: kebab-case `{noun}-proof`
  (`amount-proof`, `acc-proof`, `pay-proof`, `dsa-acc-proof`) — though some existing collections
  already deviate into snake_case (`instrument_proof`, `policy_copy`, `trc_copy`,
  `tax_receipt_copy`); don't add further variants.
- **Conversions.** Proof/copy collections register a `->width(250)` "preview" +
  `->width(100)` thumbnail pair, queued by default (no `nonQueued()`), original format kept.
  Evidence: `XFinance`, `Bookingamount`, `XlDelivery`, `XlInsurer` (x2), `XlRto` (x2),
  `Xl_Refunds` (x2), `Xl_DSA_Master`.
- **Disk.** Always explicit `->useDisk('public')` on every collection, never left at the package
  default. Evidence: `BaseModel.php:127/136/139`, `Designation.php:161`, `CommMaster.php:24`.
  (`Document.php`'s `documents` collection is the one exception found.)
- **URL retrieval.** `getFirstMediaUrl('collection')`, never `getUrl()`. Assigned into plain
  arrays at call sites (`OrgService.php:619,709`, `TaskHelper.php:127,524`, `DocsHelper.php`,
  `ChatHelper.php:28,37`) — there's no Resource-layer abstraction for this; that's a gap, not a
  pattern to imitate for new API surfaces.
- **AI tagging.** Wired synchronously inside `DocService::upload()`
  (`DocService.php:70-116`), gated by a `SystemSettingService` flag (`ai_tagging_enabled`) and
  mime type — not a Media Library event/observer, not queued. Results are written to the owning
  model's own `tags` column, not Media's `custom_properties`. If extended, keep it in this shape.
- **Deletion.** No custom media-cleanup observers exist. `clearMediaCollection()` is called only
  to *replace* a singleFile/limited collection on update (10+ call sites in
  `BookingCrudController.php`, `EnquiryCrudController.php`), never as deletion cleanup — real
  deletion cascade is left to Media Library itself.

---

## 2. No-signal / too-thin-to-record (mentioned for completeness, not proposed as rules)

- Primary keys: 100% auto-increment integers, no `HasUuids`/`HasUlids` — plain framework default.
- Eager-load posture: no model sets `protected $with`, no `preventLazyLoading()` anywhere.
- Enums: only one enum exists app-wide (`ErrorCodeEnum`), not used as a model cast.
- Custom validation rule objects (`app/Rules`), `Validator::extend`: neither exists in the app.
- DTOs as controller input/output: `app/Dtos` isn't referenced by controllers at all.
- `Imports/Concerns` traits (`PivotWriter`, `MasterDataSeeder`, `CodeGenerator`,
  `EmployeeBuilder`/`PersonBuilder`): purpose-built for the Employee-import family only, not
  reused elsewhere — not a general "split importers into concerns" convention.
- `Imports/ValueObjects`: only one example exists (`EmployeeRowDTO` — readonly properties,
  array-based constructor, static cleaning helpers). A reasonable template, but one file is
  below the evidence bar for a recorded convention.
- `app/Exports`: only one file (`VehicleAccessoriesExport.php`, service-backed
  `FromCollection + WithHeadings + ShouldAutoSize`) — too small a sample to generalize.
- Relationship fields (`select2`/`entity`) and field-level label/tab/wrapper conventions in
  Backpack: too few DSL-based field definitions (2 files) to establish an app-specific pattern
  beyond generic Backpack usage.

---

## 3. Summary of options going forward

~~Nothing has been written to `.ai/rules` yet.~~ **Update: all 25 §1 pattern candidates plus the
mass-assignment conflict (26 rules total) were recorded via `record-rule` on your approval — see
§4 below for the full log.** Remaining next steps, independently choosable:

1. ~~Record the section 1 patterns via `record-rule`~~ — **done, see §4.**
2. ~~Decide on the mass-assignment conflict~~ — **done: recorded with the proposed boundary
   (`$fillable` is the convention; `Module/{Booking,Exchange,Finance,Insurance,Rto,Spare}/**`'s
   `$guarded` pattern is flagged as legacy, not to be copied).**
3. **Decide on the section 0 doc-vs-reality conflicts** (0.1–0.2 possible real auth gaps; 0.3–0.4
   possible real defects; 0.5–0.7 stale/aspirational docs) — update `.ai/rules`, leave as-is, or
   investigate as bugs. **Still open — this is the one thing left from the original sweep.**
4. **Clean up dead code** noted in 0.8, once confirmed unreachable. **Still open.**

---

## 4. Recording log — what was written to `.ai/rules` and where

All 26 rules below were recorded via `mcp__laravel-boost__record-rule` after batch approval.
`record-rule` routes each rule into a shared area file by glob and auto-updates
`.ai/rules/index.md`'s glob table — no manual index edit was needed. Three new area files were
created in the process: `.ai/rules/api.md`, `.ai/rules/admin.md`, `.ai/rules/imports.md`.

**Operational note:** the first attempt fired all 26 `record-rule` calls in parallel. 20 of them
failed with a Windows `VirtualAlloc()`/paging-file-too-small out-of-memory error (each
`record-rule` call spawns its own PHP process via Composer's autoloader, and this machine's
paging file can't sustain 20+ concurrent PHP processes). The 6 that happened to win the race
succeeded; retrying the other 20 **sequentially, one call at a time**, cleared the memory
pressure and all 20 succeeded on retry. If a future session needs to fire a large batch of
`record-rule` (or any other PHP-spawning Boost tool) calls on this machine, prefer sequential
calls over parallel ones, or raise the Windows paging file size.

| # | Title | Glob | Landed in |
|---|---|---|---|
| 1 | Typed input retrieval | `app/Http/Controllers/Api/**` | `.ai/rules/api.md` |
| 2 | Route model binding | `app/Http/Controllers/Api/**` | `.ai/rules/api.md` |
| 3 | Authorization call site | `app/Http/Controllers/Api/**` | `.ai/rules/api.md` |
| 4 | API route handler style | `routes/api.php` | `.ai/rules/architecture.md` |
| 5 | Base model inheritance | `app/Models/**` | `.ai/rules/database.md` |
| 6 | Accessors and mutators | `app/Models/**` | `.ai/rules/database.md` |
| 7 | Custom casts merge with parent | `app/Models/**` | `.ai/rules/database.md` |
| 8 | Query scopes | `app/Models/**` | `.ai/rules/database.md` |
| 9 | Model events centralized in BaseModel | `app/Models/**` | `.ai/rules/database.md` |
| 10 | Job timeout and tries | `app/Jobs/**` | `.ai/rules/services.md` |
| 11 | Job batching for session fan-out | `app/Jobs/**` | `.ai/rules/services.md` |
| 12 | Job failure handling | `app/Jobs/**` | `.ai/rules/services.md` |
| 13 | Job dispatch style | `app/Jobs/**` | `.ai/rules/services.md` |
| 14 | Import interface family | `app/Imports/**` | `.ai/rules/imports.md` (new) |
| 15 | Import event handling | `app/Imports/**` | `.ai/rules/imports.md` (new) |
| 16 | Import error handling | `app/Imports/**` | `.ai/rules/imports.md` (new) |
| 17 | Backpack operations used | `app/Http/Controllers/Admin/**` | `.ai/rules/admin.md` (new) |
| 18 | Backpack field/column style | `app/Http/Controllers/Admin/**` | `.ai/rules/admin.md` (new) |
| 19 | Row-level data scoping in Backpack | `app/Http/Controllers/Admin/**` | `.ai/rules/admin.md` (new) |
| 20 | Media collection naming | `app/Models/**` | `.ai/rules/database.md` |
| 21 | Media conversions | `app/Models/**` | `.ai/rules/database.md` |
| 22 | Media disk | `app/Models/**` | `.ai/rules/database.md` |
| 23 | Media URL retrieval | `app/Models/**` | `.ai/rules/database.md` |
| 24 | Media AI tagging stays synchronous | `app/Services/**` | `.ai/rules/database.md`* |
| 25 | Media deletion | `app/Models/**` | `.ai/rules/database.md` |
| 26 | Mass assignment (conflict, recorded with boundary) | `app/Models/**` | `.ai/rules/database.md` |

\* Rule 24 was filed under `app/Services/**` glob but `record-rule` routed the note itself into
`.ai/rules/database.md` alongside the rest of the Media Library rules rather than creating a
separate services-scoped entry — worth a quick look next time `services.md` or `database.md` is
opened, to confirm the glob-to-file routing landed where you'd expect for a `DocService`-specific
rule.

`.ai/rules/index.md` now lists 9 rule files total (up from 6): `admin.md`, `api.md`,
`architecture.md`, `conventions.md`, `database.md`, `imports.md`, `person-user.md`,
`rbac-scopes.md`, `services.md`, `vehicle-pricing.md`, `known-pitfalls.md`.

**What's still NOT recorded, on purpose:** everything in §0 above. Those are documentation-vs-
reality conflicts (validation style, response envelope, CrudController directory layout,
`SheetHeaderService`/`SynonymService` non-use, `DocService` not being the sole media consumer)
and possible real defects/gaps (dead `checkPermission` middleware, inconsistent Backpack Admin
access control, dead code in `HasAuditFields`/`ScopedQuery`/`AfterImportListener`). Recording
rules over these would just add a second, contradictory statement next to the existing
`.ai/rules` files that already describe (incorrectly) how those areas work — they're waiting on
your call from §3 item 3.

**Suggested immediate next action, if you want one:** commit `.ai/rules/` (per the project's own
golden rule #16 — "offer `php artisan changelog:add` and reminders to commit rule changes") so
the team and future agent sessions share these 26 newly-recorded conventions.
