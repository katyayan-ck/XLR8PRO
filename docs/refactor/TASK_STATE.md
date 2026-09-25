# Task State — Per-Controller RBAC Permission Rollout

_Last updated: 21-09-2026 — the entire Module/Process/Activity structural migration phase is now
COMPLETE (batch 35 Iam, BUG-066 remediation, Pricing module BUG-068, Org module batches 1-2). See
section 8 for what's left (all out of this phase's scope). This file exists so work can resume
cleanly after a context reset. Read this file first before starting any new work on this app._

## 1. The task and current goal

**Background**: An emergency coarse-grained gate (`CheckIfAdmin` middleware, `user_type === 'Emp'
&& is_active`) was applied earlier this session to stop any authenticated user reaching `/admin`.
That fixed the "anyone logged in can reach anything" hole, but individual Backpack CrudControllers
still had zero per-resource permission enforcement.

**Current goal**: Go through all ~58 CrudControllers under `app/Http/Controllers/Admin/`, one
controller (or a small related group) per batch, and for each one:
1. Verify/establish its real route registration(s) — some are split across
   `routes/backpack/core.php`, `routes/backpack/booking.php`, `routes/backpack/pricing*.php`.
2. Add `backpack_user()->can('resource.action')` checks gating every operation (list/create/edit/
   delete), using the app's real `xlr8_iam_permissions` naming convention: lowercase
   `resource.action` (e.g. `branch.view`, `lead_source.create`), **not** the documented but unused
   `MODULE_PROCESS_ACTIVITY` format. `module_code`/`process_code` columns stay `NULL` on any newly
   minted permission, matching all 76 original rows.
3. Where no matching permission exists yet for a domain, **mint new `resource.action` permissions**
   via `Permission::firstOrCreate(...)` (persisted, not rolled back) — this was an explicit user
   decision at an earlier checkpoint, not something to re-ask.
4. Move the controller into a module-based namespace: `App\Http\Controllers\Admin\{Module}\{Entity}\
   {Entity}CrudController`, where `{Module}` is chosen by the resource's **own business domain**
   (e.g. `Sales`, `Accounts`, `Finance`, `Insurance`, `Rto`, `Spares`, `Org`, `Iam`, `Utils`,
   `Vehicle`), matching the precedent set by the pre-existing `Admin\Pricing\*` — **not** by which
   top-level menu dropdown happens to link to it (Pricing's controllers are `Admin\Pricing\*` even
   though "Price List" is linked under the Sales menu section).
5. Convert to a FormRequest (`app/Http/Requests/{Entity}Request.php`) where the original had inline
   `$request->validate()`/`CRUD::setValidation()` calls — **unless** the validation is dynamically
   conditional on other field values in a way that's risky to blindly port (e.g. financial-record
   controllers like Receipt/JournalVoucher — left untouched there, permission checks added directly
   around the existing validation code instead).
6. Preserve the original controller's exact operation set (don't add `destroy()` if it didn't exist)
   **except** when a newly-minted `.delete` permission would otherwise be pointless (see batches
   14-15 deviation, explicitly flagged each time).
7. Test every gate via HTTP-kernel requests in rolled-back DB transactions (see Testing pattern
   below) — confirm `403` without the permission, `200`(or appropriate success code) with it.
8. Run `vendor/bin/pint --dirty --format=agent` after every batch.
9. Log **every single batch** in three places, per a standing rule from the user (applies to all AI
   tools, not just Claude):
   - `docs/refactor/ai-changelogs-DD-MM-YYYY.md` — one entry per change, previous/new values, why.
   - `docs/refactor/ai-findings-DD-MM-YYYY.md` — findings/decisions, plus the rollout tracker table.
   - `docs/refactor/known-bugs-report.md` — a **permanent, non-rotating** bug tracker (BUG-001
     upward), Status/Severity/Found/Modified/Fixed timestamps, index table at the top kept in sync.
     Check it before starting each batch; add entries the moment a new independent bug is found,
     don't wait until the end of a batch.
10. `ApprovalHierarchyCrudController` is **permanently locked** — never touch, per `CLAUDE.md`.
11. The ~23 confirmed-unreachable controllers (see BUG-024) are **documented only, no action** —
    an explicit user decision; don't try to wire routes for them.

## 2. What has been completed

**34 of 58 CrudControllers migrated** (batches 1-23), all moved, permission-gated, and tested:

| # | Controller | Namespace |
|---|---|---|
| 1 | BranchCrudController | Admin\Org\Branch |
| 2 | DepartmentCrudController | Admin\Org\Department |
| 3 | DesignationCrudController | Admin\Org\Designation |
| 4 | DivisionCrudController | Admin\Org\Division |
| 5 | LocationCrudController | Admin\Org\Location |
| 6 | EmployeeCrudController | Admin\Org\Employee |
| 7 | PersonCrudController | Admin\Org\Person |
| 8 | BrandCrudController | Admin\Vehicle\Brand |
| 9 | ColorCrudController | Admin\Vehicle\Color |
| 10 | SegmentCrudController | Admin\Vehicle\Segment |
| 11 | SubSegmentCrudController | Admin\Vehicle\SubSegment |
| 12 | VehicleModelCrudController | Admin\Vehicle\Model |
| 13 | VariantCrudController | Admin\Vehicle\Variant |
| 14 | RoleCrudController | Admin\Iam\Role |
| 15 | SystemSettingCrudController | Admin\Utils\SystemSetting |
| 16 | PermissionCrudController | Admin\Iam\Permission |
| 17 | ModulesCrudController | Admin\Iam\Modules |
| 18 | ProcessCrudController | Admin\Iam\Process |
| 19 | VerticalCrudController | Admin\Org\Vertical |
| 20 | PersonAddressCrudController | Admin\Org\PersonAddress |
| 21 | PersonContactCrudController | Admin\Org\PersonContact |
| 22 | PersonBankingDetailCrudController | Admin\Org\PersonBankingDetail |
| 23 | KeyValueCrudController | Admin\Utils\KeyValue |
| 24 | KeywordMasterCrudController | Admin\Utils\KeywordMaster |
| 25 | LeadSourceCrudController | Admin\Sales\LeadSource |
| 26 | LeadCrudController | Admin\Sales\Lead |
| 27 | CampaignCrudController | Admin\Sales\Campaign |
| 28 | FinanceCrudController | Admin\Finance |
| 29 | InsuranceCrudController | Admin\Insurance |
| 30 | RtoCrudController | Admin\Rto |
| 31 | SpareRequestCrudController | Admin\Spares\SpareRequest |
| 32 | ReceiptCrudController | Admin\Accounts\Receipt |
| 33 | JournalVoucherCrudController | Admin\Accounts\JournalVoucher |
| 34 | UserCrudController | Admin\Org\User |

**Batches 17-20 pattern**: Finance/Insurance/Rto are single-`import()`-action Google-Sheets
reconciliation controllers with 100% dead CRUD scaffolding (BUG-028) — gated with one permission
each (`{resource}.import`), not the usual 4.

**Batch 23 (UserCrudController) was the deepest investigation of the session** — see BUG-038/039/040
below; the permission gate is correct and verified, but the underlying screen is still only
partially usable due to two unfixed pre-existing bugs (BUG-014, BUG-040).

**24 controllers remain** (of the ~58 total, minus ~23 permanently-unreachable ones documented in
BUG-024, minus `ApprovalHierarchyCrudController` locked). See section 6 for what's left and where to
start.

**Batch 24 (not one of the 58 CrudControllers, a plain `Controller` found via a related-controller
route search)**: `UserImportExportController` — moved to `Admin\Org\User` alongside
`UserCrudController`. Fixed a **live security gap** (BUG-041): its routes lived in `routes/web.php`
using `['auth','verified']` middleware instead of the `admin`/`CheckIfAdmin` group every other admin
route uses, meaning any authenticated+verified user of any type (not just staff) could reach bulk
user import/export with zero gate. Also fixed BUG-042 (missing `use` import, `new UserExporter`
resolved to a nonexistent class). Found BUG-043 (all 4 of its views are missing — not fixed, a
feature-completion gap not a permission-gating one). Minted `users.import`/`users.export`
permissions. **Key lesson for future batches**: check `routes/web.php` too, not just
`routes/backpack/*.php` — a controller can be reachable outside the `admin` middleware group
entirely without that being obvious from the standard route-file search.

**Unplanned fix immediately after (same session, not a numbered batch)**: swept the rest of
`routes/web.php` given how serious BUG-041 was, and found **BUG-044** — `ExportController` (3
vehicle-data export routes) and `PerformanceController::report()` had **zero authentication at
all**, fully public to anonymous visitors (worse than BUG-041, which at least required a real
login). Fixed with the same middleware construct; reused `vehicles.view` for the 3 exports, minted
`performance.view` for the report. Also found (not fixed) that `app/Exports/VehicleDataExport`
doesn't exist anywhere — the exports still fatal once triggered by an authorized user, a separate
pre-existing bug.

**Full route-file sweep completed**: checked `routes/backpack/pricing.php` and
`routes/backpack/pricing_routes.php` — both correctly use the standard `web`+`admin` middleware
construct at the route-group level (no BUG-041/044-style gap). Checked `routes/api.php` —
different subsystem entirely (customer/mobile API, not the admin panel), uses `auth:api`/
`auth:sanctum` middleware appropriately; out of scope for this rollout, not audited further.
**`routes/web.php` and all of `routes/backpack/*.php` are now confirmed clean of the "missing
admin gate" pattern** — no need to re-check this again in future batches unless a *new* route file
appears.

**Batch 25**: checked `oldEnquiryCrudController.php` reachability per the standing flag — confirmed
entirely dead (no route anywhere references it, real `EnquiryCrudController` handles all
`admin/enquir*` routes). Logged as BUG-045, documented only, no action. Both files share the exact
class name `EnquiryCrudController` in the same namespace — a latent hazard worth a human decision
on deletion, not fixed here.

**Batch 26 (`EnquiryCrudController`, dedicated batch)**: completed. **Convention change, explicit
user decision**: switched to `.ai/rules`' `MODULE_PROCESS_ACTIVITY` permission format (`SLS_ENQR_*`)
for this controller only — the first live use of that convention anywhere in the app (all 104
pre-existing permissions, and all 25 prior batches, use lowercase `resource.action`). **This is not
yet resolved app-wide** — check with the user again before picking a convention on the next
controller. Minted `SLS_ENQR_VIEW/CREATE/EDIT/DELETE/EXPORT/IMPORT` (ids 106-111, `guard_name =
'web'` — see findings doc for why `'backpack'` would have silently failed). Gated all 43 action
methods plus overrode `destroy()`/`search()`/`showDetailsRow()` (previously unguarded trait
defaults — BUG-047, fixed). Found BUG-046 (5 routes to nonexistent methods, documented only). Moved
to `Admin\Sales\Enquiry\EnquiryCrudController`. Full HTTP-kernel test round trip passed (see
changelog). Kept the proven inline `backpack_user()->can()` enforcement mechanism rather than
`.ai/rules`' `$this->middleware('permission:...')` example — confirmed that pattern doesn't
actually work in this app today (no `permission` middleware alias registered, and even if it were,
Backpack's own constructor-middleware timing means `setup()`-registered middleware is gathered by
the router too late to take effect — same class of trap as BUG-038).

**Batch 27 (`QuotationCrudController`, dedicated batch)**: completed. Continued the `SLS_QUOT_*`
convention (same user decision as batch 26 — `.ai/rules`' `MODULE_PROCESS_ACTIVITY` format).
Minted `SLS_QUOT_VIEW/CREATE/EDIT` (ids 118-120, `guard_name = 'web'`). Gated all 8 real action
methods (`revise()` inherits `edit()`'s gate by delegation). Found BUG-048 (dead route,
`pendingQuotations` doesn't exist — documented only). Confirmed `destroy`/`search`/`showDetailsRow`
are unreachable here (no routes registered at all for them, unlike Enquiry) so left as trait
defaults, not a live gap. Moved to `Admin\Sales\Quotation\QuotationCrudController`. Full
HTTP-kernel test round trip passed. Chased down and ruled out a suspected Pint regression
(`\Alert::`/`\Log::` fully-qualified calls) as a false alarm — see changelog batch 27 for detail.

**Batch 28 (`DashboardController` + `OrgDemoController`)**: completed. Swept remaining flat
`app/Http/Controllers/Admin/*.php` files against BUG-015/022/024's dead list — only these 2 were
live and unprocessed (`UserImportExportController` already fully gated in batch 24, no new work).
Checked in with the user before gating `DashboardController::index()` (the post-login landing page,
not a CRUD resource — risk of locking staff out entirely) — user chose to gate it. Reused 2
pre-existing, previously-orphaned permissions (`admin.dashboard`, `admin.manage`) rather than
minting new ones — no convention decision needed, both predate this rollout. Found BUG-049
(`OrgDemoController` 500s past the gate on an unrelated pre-existing bug in `OrgService::
usersByPost()` — undefined `User::posts()` relation). HTTP-kernel tests passed (403 confirmed on
both; note a first test attempt was invalidated by forgetting `syncRoles([])`, corrected before
drawing conclusions).

**Batch 29 (`BookingCrudController`, dedicated batch, largest so far)**: completed. 14,203 lines,
100 methods, 117 routes. Checked in with the user twice (proceed despite BUG-019's unresolved
external-modification flag; permission granularity — chose fine-grained per-sub-process over broad
buckets). Minted 20 `SLS_BKNG_*` permissions (ids 121-140) covering every business sub-process
(KYC, DMS, RTO, insurance, finance, refunds, delivery, OTF, reports, etc.). Gated all 99 real
methods + fixed BUG-051 (destroy/edit/search/showDetailsRow unguarded trait defaults, same as
BUG-047). Found BUG-050 (15 routes to nonexistent methods — largest instance of this pattern) and
BUG-052 (unrelated `CommonHelper` null-to-`trim()` deprecation warnings), both documented only.
Did this rollout's largest route-file refactor: converted ~101 bare-string route registrations to
`::class` array syntax to enable the namespace move — caught and fixed a self-introduced regression
(`'uses' => [Class, 'method']` isn't valid Laravel syntax, silently dropped 4 routes) via a
before/after route-count diff before it could ship. Moved to
`Admin\Sales\Booking\BookingCrudController`. Full HTTP-kernel tests passed; one test-methodology
false alarm on `edit()` resolved (Backpack's own framework-level entry lookup 404s a fake ID before
the controller runs for `operation`-tagged routes — unrelated to permissions, confirmed by retesting
with a real ID).

**This clears every live controller in the rollout's original scope.** Remaining unprocessed items
are all previously-documented dead code (BUG-015/022/024/045). Convention question (old
`resource.action` vs. new `SLS_*` `MODULE_PROCESS_ACTIVITY`) remains formally unresolved app-wide —
batches 26/27/29 used the new convention per-batch user decisions; batches 1-25 and 28 still use the
old one or reused pre-existing permissions.

## 3. Files changed (cumulative, all batches)

**Moved (`git mv`) + rewritten** — see table in section 2 for old→new paths (all were
`app/Http/Controllers/Admin/{Name}.php` → `app/Http/Controllers/Admin/{Module}/{Entity}/{Name}.php`).

**New FormRequest files**: `app/Http/Requests/{Entity}Request.php` for most of the 34 above (not
created for `SpareRequestCrudController` — no original validation to convert from; not created for
`ReceiptCrudController`/`JournalVoucherCrudController` — dynamically-conditional validation left
untouched in place).

**Modified repeatedly (every batch touches these)**:
- `routes/backpack/core.php` — `use` imports + `Route::crud(...)` registrations updated to
  `::class` syntax and new namespaces, for every migrated controller.
- `routes/backpack/booking.php` — same, for the controllers registered there instead/also
  (`ReceiptCrudController`, `JournalVoucherCrudController` partially, `UserCrudController`).

**Modified once, standalone fixes**:
- `app/Http/Middleware/CheckIfAdmin.php` — the original emergency gate (pre-existing this session,
  unchanged since); briefly had diagnostic logging added and removed during batch 23's
  investigation — confirmed clean now.
- `app/Models/User.php` — `isSuperAdmin()` method added (earlier in session, pre-batch-1).
- `app/Http/Controllers/Admin/Org/User/UserCrudController.php` — see BUG-038/039 fixes below.

**Standing docs, updated every batch**:
- `docs/refactor/ai-changelogs-20-09-2026.md` — full batch-by-batch log, batches 1-23.
- `docs/refactor/ai-findings-20-09-2026.md` — decisions + rollout tracker table (search for "| #
  | Controller | Status |" to find it).
- `docs/refactor/known-bugs-report.md` — BUG-001 through BUG-040 (see section 5).
- `docs/refactor/TASK_STATE.md` — this file (new).

**Also present from earlier in the session** (pre-batch-1, not part of this rollout's batches but
still live): `docs/refactor/ai-changelogs-19-09-2026.md`, `ai-findings-19-09-2026.md`.

## 4. Tests run and their results

**Standard test pattern** (used for every batch): `DB::beginTransaction()` in a `php artisan
tinker --execute '...'` script → strip a real active `Emp` user's roles/permissions
(`syncRoles([])`/`syncPermissions([])`) → grant only the specific permission under test →
`PermissionRegistrar::forgetCachedPermissions()` → `Auth::guard('backpack')->logout()` +
`->login($staff)` (fresh guard state) → drive the request through
`app(Illuminate\Contracts\Http\Kernel::class)->handle($request)` (not direct method calls — Backpack
CRUD requires full HTTP-kernel context) → assert status code → `throw new Exception('rollback-marker')`
→ `catch` → `DB::rollBack()`. CSRF handled by priming a GET request first and extracting
`csrf_token()`.

**Result across all 34 controllers**: every permission gate correctly returns `403` without the
permission and passes through (200/302, or the operation's real behavior) with it — **except** where
a controller has an independent pre-existing bug that 500s regardless of permission (see section 5;
in every such case the `403`-without-permission case was still confirmed to work correctly, proving
the gate itself is sound even when the feature behind it isn't).

**Notable non-standard verifications**:
- Batch 16 (Campaign): live-reproduced a mass-assignment authorship-spoofing attempt (`created_by`
  injected in POST body), confirmed the FormRequest conversion closes it (BUG-027, fixed).
- Batch 20 (SpareRequest): confirmed 3 independent bugs (BUG-030/031/032) each block a different
  operation; gate still verified correct via 403 in every case.
- Batch 23 (User): extensive bisection via temporary `Log::error()` markers (since since removed) to
  find BUG-038; JSON-Accept-header trick used to bypass this app's custom HTML error views and see
  real exception class/message/trace for 403s and 500s.

**`vendor/bin/pint --dirty --format=agent`**: run after every batch, always ended `{"result":"passed"}`
or with only cosmetic auto-fixes (import ordering, blank lines) — re-verified with `php -l` +
`route:list` after every Pint auto-fix.

**`composer dump-autoload`**: worked fine (a few seconds) for batches 14-20. **Started hanging
indefinitely at batch 21** (BUG-034) — extensively diagnosed as an environmental/tooling issue (not
caused by any code change; ruled out DB connectivity, the `package:discover` post-script, Composer
plugins, and confirmed `optimize-autoloader: true`'s classmap-scan phase is specifically where it
hangs). **Skipped entirely for batches 22-23** — PSR-4 fallback class resolution, `route:list`, and
full HTTP-kernel tests all confirmed working correctly without a fresh dump. **Do not keep retrying
this command** — if it's needed again, try `--no-cache` first, or ask the user whether to
investigate the environment (Windows Defender exclusions, etc.) or temporarily flip
`optimize-autoloader` in `composer.json` (needs explicit approval per `CLAUDE.md`).

## 5. Remaining issues (known-bugs-report.md — BUG-001 through BUG-040)

Full detail lives in `docs/refactor/known-bugs-report.md`. Highlights of what's still **OPEN**
(i.e., not fixed, needs a decision or follow-up — do not silently fix these while continuing the
rollout unless a batch's own routine conversion step inevitably closes one, as happened with
BUG-023/BUG-027):

- **BUG-040 (Critical, HIGHEST PRIORITY IN THE WHOLE BACKLOG)**: `UserCrudController::store()`/
  `update()`/`destroy()` call nonexistent `parent::storeCrud()`/`updateCrud()`/`deleteCrud()` —
  silently swallowed by their own `catch(\Exception $e)`, producing a fake-success redirect while
  doing **nothing**. No user has ever been created/updated/deleted through this screen. Needs a real
  fix preserving existing password-hashing/audit-logging/last-superadmin-guard logic — out of scope
  for permission-gating, flagged for dedicated follow-up.
- **BUG-014 (Medium, blocks 2 controllers)**: PRO-only Backpack features (`dropdown` filter,
  `select2` field) used without `backpack/pro` installed — blocks `SystemSettingCrudController`'s
  list and `UserCrudController`'s list/create/edit, even with correct permissions. Needs a
  purchase-PRO-or-rewrite-to-FREE-equivalent decision.
- **BUG-024 (Medium, scale)**: 23 of 58 controllers (~40%) are entirely unreachable — no route
  anywhere. Includes the entire "HR Operations" menu section (`HRTransferController`,
  `HRRelievingController`, `EmployeeJourneyController`, plus `EmpPostAssignmentCrudController`), 5
  Employee `*AssignmentCrudController`s, `SpareOrderingreportController`/`SparePartwiseController`
  (real, complete controllers with real views and menu links — just missing route registrations),
  `TestDriveCrudController` (route deliberately commented out), and more. **Documented only, no
  action — explicit user decision.**
- **BUG-034 (Low, environmental)**: `composer dump-autoload` hangs. See section 4.
- **BUG-036 (Low)**: `Route::crud('user', ...)` registered identically in two route files.
- **BUG-037 (Low)**: `DashboardController`'s richer stats views are dead code (never called).
- Several Low/Medium column-mismatch and dead-code bugs from earlier batches (BUG-008, 009, 010,
  011, 013, 016, 017, 018, 019, 020, 021, 025, 029, 030, 031, 032, 033, 035, 039-adjacent) — all
  documented, none blocking further rollout progress.
- **BUG-038, BUG-039**: FIXED this session (see section 2/3).
- **BUG-023, BUG-027**: FIXED as inevitable side effects of routine conversion steps (case-mismatch
  fix, mass-assignment fix).

## 6. Status as of end of batch 29 — core rollout scope is complete

**Every live, reachable CrudController under `app/Http/Controllers/Admin/**` now has per-action
Spatie permission enforcement.** Batches 1-29 covered all of them; batch 29 (`BookingCrudController`)
was the last one in scope. Everything else still flat/unconverted in that directory is
previously-documented dead code (BUG-015/022/024/045) — no route reaches it, so no action needed
per the user's standing decision to document-not-fix that category.

**Post-rollout quality-gate check (new, after batch 29)**: ran the 2 `CLAUDE.md` gates not yet run
this session. `phpstan analyse` — not installed in this project at all (absent from `composer.json`),
can't run, not something to install without approval. `php artisan test` — 39/40 tests fail, traced
to `phpunit.xml` hardcoding a nonexistent test database name (BUG-053, `xlrn` vs. the real `xlrm`),
pre-existing and unrelated to this rollout. Documented only, per explicit user instruction not to
touch it without owner confirmation. Practical consequence: this rollout's changes have no PHPUnit
coverage — every batch was instead verified via the manual HTTP-kernel round-trip methodology
(logged per-batch in `ai-changelogs-20-09-2026.md`).

### Open items, roughly in priority order

1. **BUG-019** (`BookingCrudController`'s unexplained external modification) and **BUG-053**
   (`phpunit.xml`'s wrong test DB name) both explicitly need the repo owner's input — not something
   further AI-session work can resolve; both documented, both left alone per instruction.
2. **Permission-naming convention is still unresolved app-wide.** Batches 26/27/29
   (`Enquiry`/`Quotation`/`Booking`) use the new `MODULE_PROCESS_ACTIVITY` format (`SLS_ENQR_*`/
   `SLS_QUOT_*`/`SLS_BKNG_*`) per per-batch user decisions; batches 1-25 and 28 use the old
   lowercase `resource.action` format (or reused pre-existing permissions of that style). Nothing
   has decided whether the whole app should standardize on one — that's a real decision for the
   user to make, likely as its own dedicated follow-up pass if the answer is "yes, convert
   everything," given it would touch ~25 already-shipped controllers.
3. **BUG-040** (`UserCrudController::store()`/`update()`/`destroy()` call nonexistent Backpack API
   methods, silently swallowed — no user has ever actually been created/updated/deleted through that
   screen) remains the single highest-severity *unfixed* bug from the whole session — deferred
   repeatedly as "requires preserving business logic while fixing API calls," genuinely out of scope
   for a permission-gating pass, but worth flagging as the top candidate for a dedicated follow-up.
4. **BUG-046/048/050** (broken routes pointing at nonexistent methods, 3 separate instances across
   Enquiry/Quotation/Booking) and **BUG-030** (`XCommonHelper` depends on 8+ nonexistent model
   classes) are the next-most-impactful *documented, unfixed* bugs — real, live 500s for any user
   who reaches them, just outside this rollout's stated scope of "permissions only."
5. Every newly-minted permission in this rollout (all ~60+ of them, across every batch) still needs
   to actually be **assigned to the relevant roles** via Backpack's Role UI before real staff can use
   the now-gated screens — this rollout only created the permissions and verified the gates work,
   consistent with how every batch handled this; role assignment was never in scope and needs the
   repo owner/an admin to configure via the UI, not something an AI session should do unprompted.
6. No user-specified deadline. The rollout's original goal (RBAC enforcement on every live admin
   controller) is met; what comes next — closing BUG-040, the convention decision, BUG-053, or
   something else entirely — is the user's call, not something to assume.

## 7. New phase: Module/Process/Activity structural migration (started, batch 30)

**Superseded the "convention decision" open item above** — the user explicitly answered it and
gave a much larger, permanent, whole-app directive: routes, route names, PHP namespaces,
permission codes, and menu visibility must all follow a Module → Process → Activity hierarchy
everywhere in the admin panel, including retroactively renaming already-shipped live URLs (the
higher-risk option, explicitly chosen over "new work only"). Recorded as a permanent standing rule
at `.ai/rules/module-structure.md` (registered in `.ai/rules/index.md`) after 2 rounds of
clarifying questions to lock down exact format/casing/scope before touching anything — **read that
file before starting the next batch**, it has the full spec (module code table, permission format,
route/namespace patterns, the trait-alias override pattern, everything).

**Batch 30 (first execution batch) — Campaign, LeadSource, Lead, Quotation**: done. 37 routes moved
from flat `/admin/{entity}/*` URLs to `/admin/sales/{process}/*`, route names to `sales.{process}.
{activity}` dot notation, 12 new `SLS_CMPN_*`/`SLS_LEAD_*`/`SLS_LDSR_*` permissions minted
(`SLS_QUOT_*` already existed). Found and fixed BUG-054 (2 more unguarded `search`/
`showDetailsRow` trait defaults, same as BUG-047/051). Found and documented BUG-055 (significant —
`@can`/`Auth::user()` never works anywhere in this app because Backpack's guard-switching
middleware is disabled; needs an owner decision, see the bug entry), BUG-056 (dead menu link,
pre-existing), BUG-057 (pre-existing view bug surfaced by testing, unrelated to this batch). Audited
and fixed 17 hardcoded old URLs/route names across 16 Blade files (14 in the 4 entities' own view
folders, 2 in Booking's views that happened to reference Quotation's old URLs) — this audit step is
**mandatory every batch** per the new rule, since a missed hardcoded URL breaks silently at runtime,
no build-time error. Gated Campaign/Quotation's menu entries with `backpack_user()->can(...)` (not
`@can`, because of BUG-055). Full HTTP-kernel test round trip passed; old URLs confirmed fully gone
via `route:list`.

**Batch 31 (Enquiry, dedicated batch)**: done. 54 routes (many unnamed, 2 literal duplicate
registrations across `core.php`/`booking.php`) consolidated to 51 explicit `sales/enquiry/*` routes
with `sales.enquiry.*` names. Resolved a same-two-methods URI collision (`data()`/`gridData()` and
`export()`/`exportData()` — the real ones now keep their expected names, BUG-046's 2 confirmed-broken
ones moved to an explicit `-legacy` suffix so the route table itself signals which is which). Fixed
BUG-058 (literal doubled `admin/admin/` URL segment) as an incidental fix of the exact line being
migrated. Audited and fixed 53 hardcoded URL/route-name references across 17 Blade files — including
a second pass needed for 3 string-concatenation patterns the first fixed-string sweep missed, and 1
more caught only by the IDE's live route-name validation, not grep. Menu's Enquiries dropdown now
gates by `SLS_ENQR_CREATE`/`SLS_ENQR_VIEW`, carefully preserving the independent `SLS_CMPN_VIEW`
Campaign link that happens to sit inside the same dropdown. Full HTTP-kernel tests passed.

**Batch 32 (Booking, dedicated batch)**: done. 116 routes (1 genuine duplicate collapsed) migrated
to `sales/booking/*`, organized by business sub-domain matching batch 29's `SLS_BKNG_*` permission
groups. Found and fixed BUG-059 (a route name that never existed, fatally crashing a page on every
render), BUG-060 (3 dead breadcrumb links), documented BUG-061 (4 pre-existing 500s surfaced by
testing, structurally confirmed unrelated) and BUG-062 (5 dead menu placeholder links). Audited 68
Blade files in Booking's own folder plus cross-module references (including 2 Quotation views that
were deliberately left pointing at Booking's old URLs in batch 31, now fixed). Menu gated at
`SLS_BKNG_VIEW`/`REPORT` broadly, with precise `SLS_BKNG_EXCHANGE`/`FINANCE`/`REFUND` gating where
the menu structure happened to map cleanly onto existing permission groups. Full HTTP-kernel tests
passed (permission gates all correct; 4 pre-existing 500s past the gate are BUG-061, not a
regression). **This closes out the entire Sales module.**

**Batch 33 (9 small entities: Accounts, Finance, Insurance, Rto, Spares, Utils)**: done. 48 routes
across JournalVoucher, Receipt, Finance, Insurance, Rto, SpareRequest, KeyValue, KeywordMaster,
SystemSetting. Minted 15 permissions (ids 153-167); Finance/Insurance/Rto needed zero route changes
(already conformed). Found and fixed BUG-063 (`SystemSettingCrudController::destroy()` fully
unguarded) and **BUG-064 — a critical, previously-unknown process risk**: converting
`Route::crud()` to explicit routes silently disables any permission check living only in a
`setupXOperation()` hook (no inline duplicate), because the plain registration form drops the
`'operation'` action-array key Backpack's hook dispatch depends on. Affected 2 controllers
(SystemSetting, SpareRequest); re-audited all of batches 25-32 and confirmed none of them were
exposed (all have redundant inline checks). **Added a permanent warning to
`.ai/rules/module-structure.md` §3 — read it before converting any future controller's
`Route::crud()` call.**

**Batch 34 (Vehicle module: Brand, Color, Model, Segment, SubSegment, Variant)**: done. 55 routes
moved to `admin/vehicle/{process}/*`. Applied BUG-064's `'operation'`-key fix consistently to every
route on the 5 trait-based controllers (not just where strictly required) given how silent that
failure mode is. Minted 19 permissions (ids 168-186); `SubSegmentCrudController` deliberately reuses
`VEH_SEG_*` rather than getting its own set, preserving its pre-existing shared-permission behavior
with Segment. Found BUG-065 (dead route, `getSegmentsByBrand()` doesn't exist). Cross-referenced but
did not re-log BUG-017 (`brand.import` route name was never registered, matches already-documented
dead `BrandCrudController::import()`). Full HTTP-kernel tests passed, including explicit
re-verification of the BUG-064 fix pattern on a second batch of controllers (Brand's
`search`/`showDetailsRow`).

**Batch 35 (Iam module: Modules, Permission, Process, Role)**: done. 32 routes moved to
`admin/iam/{entity}/*`. Renamed `rbac.view`/`rbac.manage` → `IAM_RBAC_VIEW`/`IAM_RBAC_MANAGE`
(shared across all 4 entities, matching the pre-existing shared-permission pattern). Applied
BUG-064's `'operation'`-key fix on all 32 routes. Menu's RBAC dropdown gated by
`backpack_user()->can('IAM_RBAC_VIEW')` around its 4 IAM links only (dropdown wrapper + unrelated
"Post Permission"/"Approval Hierarchy" items stay outside the `@if`, to avoid a Blade `@if`/closing
tag mismatch). Full HTTP-kernel test round trip passed.

**BUG-066 found and fixed (cross-batch, discovered during batch 35's cleanup)**: the hardcoded-URL
audit process used since batch 30 only ever checked Blade views, never controllers' own PHP source
— controllers build `backpack_url()` calls internally for AJAX grid edit/view buttons and post-save
redirects. This left stale pre-rename URLs live inside 20 already-migrated controllers spanning
Sales, Accounts, Utils, Spares, Vehicle, and Iam. A first-pass bulk-fix script also missed
multi-line-formatted `backpack_url()` calls, requiring a second manual sweep. All 20 files fixed and
verified (see `docs/refactor/ai-changelogs-21-09-2026.md` and known-bugs-report.md BUG-066).
**`.ai/rules/module-structure.md` §6 permanently updated** to require this check (controller PHP
source + multi-line form) in every future batch — read it before starting Org or Pricing.

**Pricing module (6 controllers: Hold, Insurance, PricingReset, PricingWorkflow, RtoRule,
TcsConfig)**: done. Routes/namespaces already conformed (pre-existing) — no rename needed. Found
**BUG-068 (Critical)**: zero Spatie-permission enforcement existed on any of the 6 controllers
(these are plain `Controller`s, not `CrudController`s, so they fell outside the original
58-controller rollout's scope). `PricingResetController` in particular is a destructive
single-action endpoint (deletes vehicle variant/model rows, flushes session/price-history/hold data)
reachable via plain `GET ...?confirm=1` with zero authorization before this fix — the most severe
gap found since the original BUG-001. Minted 10 `PRC_*` permissions (ids 189-198) and gated all 6
controllers with the standard inline `backpack_user()->can(...)` pattern. Fixed the "Price List"
menu link's broken `auth()->user()->...` gate condition (BUG-055 pattern) to use
`backpack_user()->...`; its dead target URL remains unfixed (BUG-069, feature/UX decision, out of
scope). Found and documented (not fixed) BUG-067 (stale duplicate `pricing_routes.php`, harmless,
same as BUG-036). Full HTTP-kernel test round trip passed across all 6 entities.

**Org module, batch 1 of 2 (11 entities: Branch, Department, Designation, Division, Employee,
Location, Person, PersonAddress, PersonBankingDetail, PersonContact, Vertical)**: done. Per explicit
user decision, renamed all existing `resource.action` permissions to the `ORG_{PROCESS}_{ACTIVITY}`
convention (28 new permissions, ids 199-226) rather than leaving them as-is — preserved the 2
existing deliberate shared-permission relationships (Vertical reuses `ORG_DIVN_*`; PersonAddress/
PersonBankingDetail/PersonContact all reuse `ORG_PRSN_*`). Converted 11 `Route::crud()`
registrations to explicit routes under `admin/org/{slug}` with `org.{entity}.{activity}` names.
Added the missing `search()`/`showDetailsRow()` trait-default permission override to all 11 (same
gap as BUG-047/051/054/063). Fixed a self-introduced regression mid-batch (a too-broad `sed` for the
permission rename briefly corrupted 7 files' Blade view-path strings — caught via IDE diagnostics,
fixed immediately, verified against the real view files on disk). Full hardcoded-URL audit per
BUG-066 (controller PHP source + Blade views + multi-line form) — found and fixed 6 previously
ungated cross-references in `dashboard.blade.php` and `menu_items.blade.php`'s Foundation/Users Info
sections (neither section had any permission gating before this batch). Full HTTP-kernel test round
trip: all 11 correctly `403` without permission; with permission, 8 return `200` and 3 return `500`
traced to already-documented pre-existing bugs (Employee → BUG-008, PersonAddress → BUG-020,
PersonBankingDetail → BUG-021) — not caused by this migration.

**Org module, batch 2 of 2 (User, UserImportExportController)**: done. Renamed `users.*` permissions
to `ORG_USER_*` (6 permissions, ids 227-232). Converted `UserCrudController`'s `Route::crud()` to
explicit routes under `admin/org/user` — this controller relies purely on hooks for list/create/edit
(no inline overrides), so BUG-064's `'operation'`-key requirement applied directly here (unlike the
other 11 Org entities, which had redundant inline checks). Added the missing `search()`/
`showDetailsRow()`/`show()` trait-default permission overrides. Moved `UserImportExportController`'s
`routes/web.php` group from `admin/users` to `admin/org/user` for URL consistency. Fixed 3 ungated
menu references (2 duplicate "Users" links + 1 dashboard card link). Left all 3 known pre-existing
bugs (BUG-040, BUG-014, BUG-043) completely untouched, as instructed — confirmed via HTTP-kernel
testing that the permission gates now correctly return `403`/`500`(pre-existing bug, not a
regression) exactly as expected, with no functional change to the underlying (broken) behavior.

## 8. Phase complete — Module/Process/Activity structural migration finished (21-09-2026)

**All 43 entities across every module (Sales, Accounts, Finance, Insurance, Rto, Spares, Utils,
Vehicle, Iam, Pricing, Org) now conform to `.ai/rules/module-structure.md`**: `{module}/{process}/
{activity}` route URIs, `{module}.{process}.{activity}` route names, `{MODULE}_{PROCESS}_{ACTIVITY}`
permission codes, `Admin\{Module}\{Entity}\{Entity}CrudController` namespaces, and
`backpack_user()->can(...)`-gated menu entries. This closes the phase started in section 7 above.

### What's left, for a future session (not part of this phase, do not assume in scope)

1. **Known, documented, unfixed functional bugs** remain exactly as found — this phase was
   route/permission/namespace/menu structure only, never a bug-fixing pass. Highest-priority
   candidates for a dedicated follow-up, per `known-bugs-report.md`: BUG-040 (User create/update/
   delete silently no-ops), BUG-014 (PRO-feature gaps blocking User's list/create/edit and
   SystemSetting's list), BUG-043 (UserImportExportController's 4 missing views), BUG-030/031/032
   (SpareRequest's 3 stacked fatal bugs), BUG-008/020/021 (Employee/PersonAddress/
   PersonBankingDetail column mismatches), BUG-061 (4 pre-existing Booking 500s).
2. **BUG-055** (Backpack's guard-switching middleware disabled, breaking `@can`/`Auth::user()`
   app-wide) still needs an owner decision before `@can` can ever be used safely in this app.
3. **BUG-053** (`phpunit.xml` wrong test DB name, blocking the entire test suite) still needs owner
   confirmation before touching.
4. **Permission-role assignment**: every permission minted across this whole rollout (200+ across
   both the original RBAC phase and this structural-migration phase) still needs to be assigned to
   the relevant roles via Backpack's Role UI before real staff can use the now-gated screens — this
   was never in scope for either phase.
5. **BUG-067** (stale duplicate `routes/backpack/pricing_routes.php`) and **BUG-036** (duplicate
   `user` route registration in `core.php`/`booking.php`) are both harmless (deduped identically) but
   worth a cleanup pass.
6. No user-specified deadline for any of the above — next steps are the user's call.
2. **Before converting any controller's routes, grep it for `setupListOperation`/
   `setupCreateOperation`/`setupUpdateOperation` vs. inline `public function index/create/edit/
   update` overrides (BUG-064)** — if a hook has no inline duplicate, the new route registration
   needs the `'operation'` key preserved via the options-array form (see `.ai/rules/
   module-structure.md` §3 for the exact syntax, including the `Controller@method` string-vs-array
   gotcha inside that form).
2. Given 3 batches now, a consistent pattern has emerged for every batch: (a) map old routes to new
   under `sales/{module}/{process}/*` or `{module}/{process}/*` as appropriate, watching for
   same-method-different-URI collisions when normalizing (seen on Enquiry's `data`/`export` pairs)
   and cross-controller URI collisions on generic-looking bare routes (checked but not found on
   Booking — still worth checking every time); (b) remove/consolidate any duplicate `Route::crud()`
   or duplicate explicit registrations found along the way (BUG-036-pattern, found on `user`,
   `campaign`, `enquiry`, and `refundView`/`receiptEdit`/`checkFieldPayment` so far); (c) audit
   *every* Blade view referencing the entity's old URLs/route names — both fixed-string
   `backpack_url()`/`route()` calls AND string-concatenation patterns AND bare/unprefixed URL
   variants — a plain grep for the entity's own name prefix is not sufficient, confirmed missing
   real references on all 3 batches so far; (d) gate menu entries with `backpack_user()->can(...)`
   (never `@can`, per BUG-055) at whatever granularity the menu's own structure supports; (e) full
   HTTP-kernel test round trip; (f) log in all 3 standing files.
3. **BUG-055 still needs the user's decision** before `@can` can ever be used safely in this app.
4. **BUG-061** (4 pre-existing 500s in Booking, found this batch) is a good candidate for a small,
   focused follow-up pass once the module-structure migration itself is further along or complete.
2. After Sales is fully done, sweep the other 10 modules not yet touched by this phase: Accounts,
   Finance, Insurance, Rto, Spares, Vehicle, Org, Iam, Pricing (pre-existing, may already roughly
   conform — check first), Utils. Each needs the same treatment: route rename + hardcoded-URL audit
   + (where not already `SLS_*`-style) permission rename + menu gating + HTTP-kernel testing.
3. **BUG-055 needs the user's decision** before it can be closed — enable Backpack's disabled
   guard-switching middleware (global behavior change, needs sign-off) vs. leave it disabled and fix
   the one existing broken `@can(...)` call to use `backpack_user()->can(...)` instead. Worth raising
   proactively rather than waiting — it affects whether future batches can ever safely use `@can`.
4. Continue logging every batch in the three standing files, exactly as done for batch 30.
5. `record-rule` (Laravel Boost MCP) was unavailable when `.ai/rules/module-structure.md` was
   written (connection timeout) — if it becomes available in a future session, no action needed,
   the rule file is already in place and correctly registered in `.ai/rules/index.md`.
