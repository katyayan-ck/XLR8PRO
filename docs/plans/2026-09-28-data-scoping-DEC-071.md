# Plan: automatic user data scoping, with opt-out (DEC-071)

## Context
You want every screen, function and API to show a user only the data inside their assigned scope. It should apply automatically, with an explicit way to switch it off in code.

**Rules you gave:**
- **No definition means full access.** A user with no scope rows for a dimension sees everything on that dimension.
- **A parent covers all its children, unless restricted lower down.** For example:
  - Segment PV, with "all" (or nothing) for sub-segment, model and variant, covers every variant of every PV model.
  - Segment PV plus model Thar covers only Thar variants.
  - The same holds for Branch → Location and Department → Division. Vertical stands alone.
- **Coverage:** booking, quotation, enquiry and everything else.

**What exists today (read-only triage, 28-09):**
- **Built but switched off.**
  - Pieces: `DataScopeService` (`app/Services/IAM/DataScopeService.php`), the `ScopedQuery` trait, the `DataScopeFilter` global scope, the `ScopedCrud` controller trait, and `UserScopeService` (the only writer of `xlr8_admin_user_scopes`, 1,501 active rows).
  - In use: only `XlSpareRequest` (a hidden module) and `BranchCrudController` (shadowed, BUG-083) use any of it.
- **Wrong in ways that block switch-on:**
  - It filters by **id**, while the data and masters use **codes**.
  - It fails **closed**: no scope rows means no access, the opposite of your rule.
  - It has **no hierarchy expansion**, and its `ScopedCrud` parent columns don't exist.
  - It knows only 7 of the 9 types (no `model` or `variant`).
  - It ignores the `from_date` / `to_date` columns.
  - It reads the default guard instead of Backpack's.
  - It doesn't handle table aliases (Booking's `getBaseQuery()` uses `… as bookings`).
- **Hierarchies are code-linked:**
  - `location.branch_code`, `division.dept_code`;
  - `subsegment.segment_code`;
  - `model.segment_code` / `sub_segment_code`;
  - `variant.model_code` / `sub_segment_code` / `segment_code`.
  - The helpers that exist (`OrgService::locations($branch)`, `divisions()`, `subSegments()`, `models()`, `variants()`, `VehicleService::descendantsOf()`) each expand only one level.
- **Data gaps:**
  - Enquiries (60,923): the branch, location and segment codes are empty on every row; model and variant are free-text names on about 60%.
  - Bookings have no branch or vehicle columns.
  - Quotation, Lead and the booking satellites reach their dimensions only through a parent: `enquiry_no` = enquiry id, `bid` = booking id, refunds via `entity_id`.
  - Insurance and RTO have no link at all (`bid` empty).
  - Only campaigns carry clean codes.
  - No business table has a department, division or vertical column.
  - The vehicle masters are empty in local `xlrm`; `xlrm_testing` has them.
- **Reach of a model-level scope:** about half the list screens. Enquiry, Lead, Campaign, Quotation, Receipt/JV and the 36 booking grids are Eloquent. The booking reports (27 raw queries, already broken — BUG-122), the enquiry OTF list and `OrgService::getCustomerByTransactionIds` are raw SQL.
- **Leak risk:** the enquiry counts are cached under global keys (`menu_enquiry_counts`, `enquiry_highlight_counts`).

**Your decisions (28-09):**
1. Rows with an empty dimension stay **visible** (setting `scope.unassigned_rows = visible`) until backfilled, then flip to `hidden`.
2. **Pickers stay unscoped**; only business data rows are filtered.
3. Department, division and vertical apply **only where a column exists** (people-centric data plus any table that carries one), declared per entity in config.
4. Bookings get their own codes: **add the columns, set them on save, backfill**. This also covers D22 / BUG-161 / BUG-092.

## Design

### 1. Scope engine: one resolver, code-based, hierarchical, cached
- **`App\Services\IAM\DataScope\ScopeResolver`** (new, extends the existing `DataScopeService` namespace):
  - `for(User $user): ScopeSet`.
  - Reads the user's active scope rows. `from_date` / `to_date` count, fixing the BUG-136 open item.
  - Rows are grouped by type and normalised to upper case. `ALL` / `ANY` / empty mean "no restriction at this level".
- **Trees** come from `config/data_scope.php`:
  - `org_branch`: branch → location (`xlr8_admin_location.branch_code`);
  - `org_dept`: department → division (`xlr8_admin_division.dept_code`);
  - `vehicle`: segment → sub_segment → model → variant (linking columns listed above);
  - `vertical`: flat.
- **Effective allowed set per level** (your rule, applied top-down):
  - A level with no rows **and** no restricted ancestor is unrestricted (`null`).
  - A level with rows keeps those codes, but only the ones under the allowed parents.
  - A parent with no child rows expands to **all** its children (read live from the masters, so new masters are included automatically).
  - A parent that does have rows at a child level uses only those children.
  - Example: PV + Thar → segments {PV}; sub-segments = all under PV; models {THAR}; variants = all under THAR.
- **`ScopeSet` (value object):**
  - `allowed(string $level): ?array` — codes, or `null` = unrestricted.
  - `isUnrestricted()`.
  - `hash()` — used for cache keys.
- **Bypass:** superadmin or `bypass_data_scoping`, so `ScopeSet::all()`.
- **Caching:**
  - Per request (memoised).
  - `Cache` keyed by user id plus the max `updated_at` of the user's scope rows and a masters version.
  - `UserScopeService` grant / revoke / sync and the master entity services bump a version key.
- **Legacy "ALL" lists.** Imports used to expand "ALL" into every current code, so new masters are missed. A one-off command `scopes:collapse --dry-run` rewrites a user who holds every active child of a parent to hold only the parent, through `UserScopeService::sync`, after a backup. The importer and export already fold ALL, and the importer will stop expanding.
- **Kept for compatibility:** `DataScopeService::getAccessibleIds()` becomes a thin wrapper over the resolver.

### 2. Automatic application with opt-out
- **Trait `App\Models\Traits\HasDataScope`** (replaces `ScopedQuery`, which is deleted):
  - Adds the rewritten global scope `App\Http\Scopes\DataScopeFilter`.
  - Each model declares its dimension map in `config/data_scope.php` `entities`, e.g.
    - `Enquiry => ['branch' => 'dealer_branch', 'location' => 'dealer_location', 'segment' => 'segment_code', 'model' => 'model_code', 'variant' => 'variant_code']`;
    - satellites through a parent: `XFinance => ['via' => ['bid' => Booking::class]]`.
- **Filter semantics per dimension:**
  - The filter uses the **most specific column the table has** (variant > model > sub-segment > segment; location > branch; division > department).
  - The level above is a fallback when the specific column is empty on a row.
  - Empty on all levels: pass or fail per `scope.unassigned_rows` (setting, default `visible`).
  - Dimensions combine with **AND**; codes within a dimension with **OR**.
- **Alias-safe:** the column is qualified with the query's actual `from` alias.
- **Through a parent:** `whereIn(fk, <parent query with the same scope applied>)` as a subquery; nothing is loaded into PHP.
- **When it applies:** only when a user is resolved. The resolver checks the Backpack guard, then Sanctum, then the default guard, so it covers admin **and** the mobile API. Jobs, console and imports have no user and are never scoped.
- **Opt-out (your "switch it off manually"), three levels:**
  1. **A query:** `Booking::withoutDataScope()->…`.
  2. **A function or block:** `DataScope::off(fn () => …)`. A facade over a request-scoped `DataScopeContext` suspends scoping for everything inside the closure (numbering, duplicate checks, name lookups).
  3. **A whole screen or route:** route middleware `data-scope:off` (or the controller property `protected bool $dataScope = false`, read by the middleware).
  - All three are logged at debug level with the reason string you pass, so an audit can find every exemption: `grep -rn "withoutDataScope\|DataScope::off\|data-scope:off"`.
- **Raw SQL:** `DataScope::apply(Query\Builder $q, string $entity, string $alias)` gives raw queries the same filter. Every raw read site of a scoped entity gets it, or an explicit `DataScope::off` with a reason.

### 3. Coverage (where it switches on)
- **Direct columns:** Enquiry, Lead, Campaign, Booking (after §4), Stock (`location_id`; the id → code map is verified first), employee / user lists (branch, location, department, division, vertical, segment from `xlr8_admin_employee`), tasks / tickets / approvals where they carry org codes.
- **Via parent:** Quotation → Enquiry (`enquiry_no` = id); Bookingamount / XFinance / XExchange / XlDelivery → Booking (`bid`); Xl_Refunds → Booking (`entity_id`); Receipt / JV (`Bookingamount`) → Booking.
- **No link → unscoped, reported:** XlInsurance and XlRto, whose `bid` is empty; they become scoped once `bid` is filled. The master tables themselves (your decision 2: pickers and name lookups stay unscoped).
- **Code sites to adjust:**
  - `BookingCrudController::getBaseQuery()` alias; replace the three `withoutGlobalScopes()` calls with explicit, reasoned opt-outs.
  - Enquiry OTF list (raw `xlr8_crm_booking`): scope by `oem_code` → variant.
  - `QuotationCrudController::index()` raw booking map.
  - Receipt / JV `index()` raw enquiry lookups and `OrgService::getCustomerByTransactionIds`.
  - The booking report family, together with its BUG-122 rewrite (still hidden).
- **Must opt out** (duplicates or wrong numbers otherwise):
  - `BookingOtfService::bookingHoldingVotf()` / `generateVotfNumber()`;
  - `EnquiryCrudController::checkDuplicateEnquiry`, `OrgService::checkReceiptX`, the booking `create()` duplicate checks;
  - receipt / JV numbering (`lockForUpdate` sequences);
  - `VehicleService::findOrCreate*`;
  - importers (no user anyway).
- **Cache keys:** `menu_enquiry_counts` and `enquiry_highlight_counts` become `…:{scopeHash}`.
- **Single records:** `findOrFail` on a scoped model returns 404 for an out-of-scope id. That is intended: users can no longer open other branches' records by URL.

### 4. Booking codes (your decision 4, D22)
- **Migration** (guarded, reversible, local only, backup first): add `branch_code`, `location_code`, `sub_segment_code`, `model_code`, `variant_code` to `xlr8_booking_master`. `segment_code` already exists. Add indexes on `branch_code`, `location_code`, `model_code`.
- **Fill on write:**
  - `BookingCoreService::store()` takes them from the linked enquiry / quotation (vehicle) and the form (branch / location).
  - `BookingOtfService::apply()` sets branch from the OTF branch the user picks, and vehicle codes from the chosen variant.
  - `Booking::branch()` / `location()` relations are fixed to use the new columns.
- **Enquiry codes going forward:** enquiry create / update stamps `dealer_branch` / `dealer_location` from the form, or the creating employee's primary branch / location, and vehicle codes from the chosen variant. `ImportEnquiriesJob` maps them from the sheet through `SynonymService` / `OrgScopeService::resolveCode`.
- **Backfill command** `data-scope:backfill {--entity=} {--dry-run}`:
  - **Bookings:** from the enquiry, the quotation snapshot, or stock via `chassis_no` / variant.
  - **Enquiries:**
    - branch / location from follow-up `dealer_location` names (about 40%, via synonyms);
    - from the `dealer_code` suffix;
    - from the consultant's employee (`sc_mile_id` → employee), once `mile_id` is populated;
    - model / variant names → codes once the vehicle masters are loaded.
  - The dry run reports coverage per entity and dimension before anything is written.
- **Flip `scope.unassigned_rows` to `hidden`** only after you review that report.

### 5. Admin visibility
- The User edit screen gets an **"Effective access" preview**: the resolved tree (e.g. PV → all sub-segments → THAR → 6 variants; BKN → all 5 locations), read-only.
- **Settings:**
  - `scope.enabled` — master switch, default on after UAT sign-off; off gives the old behaviour instantly.
  - `scope.unassigned_rows` — `visible` / `hidden`.

## Rollout, in commits (log DEC-071 first)
1. Engine: resolver, `ScopeSet`, config, caching, tests. No behaviour change.
2. Filter, opt-out API, middleware, `HasDataScope`; delete `ScopedQuery` / `ScopedCrud` (replaced; their only users are migrated).
3. Booking columns: migration, fill on write, relations. Enquiry fill on write.
4. Switch on per entity group: Enquiry / Lead / Campaign / Quotation → Booking and satellites → Receipt / JV → employees / users / tasks / tickets. Includes the raw-site fixes, the opt-outs and the cache keys.
5. Backfill command (dry run, review, then run on local) and `scopes:collapse`.
6. Effective-access preview; guides (`docs/domains/iam-auth.md`, `core.md`, `sales-booking.md`, `crm-enquiry-quotation.md`, `.ai/rules/modules/iam-rbac.md`); changelog; tracker (BUG-083, BUG-136, BUG-161, BUG-092 closed or updated).

## Critical files
- **New:** `config/data_scope.php`, `app/Services/IAM/DataScope/{ScopeResolver,ScopeSet,DataScopeContext}.php`, `app/Support/Facades/DataScope.php`, `app/Models/Traits/HasDataScope.php`, `app/Http/Middleware/DataScopeMiddleware.php`, `app/Console/Commands/{DataScopeBackfill,CollapseUserScopes}.php`, a booking columns migration.
- **Changed:**
  - `app/Http/Scopes/DataScopeFilter.php`, `app/Services/IAM/DataScopeService.php`, `app/Models/User.php` (date-aware `activeScopes`), `app/Services/IAM/UserScopeService.php` (cache version bump);
  - models `CRM/{Enquiry,Lead,Campaign,Quotation}`, `Module/Booking/*`, `Module/Finance/XFinance`, `Admin/Employee`;
  - `BookingCrudController` (`getBaseQuery` + opt-outs), `EnquiryCrudController`, `QuotationCrudController`, the Receipt / JV controllers;
  - `BookingCoreService`, `BookingOtfService`, `ImportEnquiriesJob`;
  - `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` (count cache key), `BranchCrudController` (BUG-083).
- **Reuse:** `UserScopeService` (writes), `OrgScopeService::resolveCode()`, `SynonymService`, `OrgService` / `VehicleService` master reads, `SettingsService` for the two settings, `IdentifierService`.

## Verification
- **Unit (`xlrm_testing`, which has vehicle masters):** the resolver against your examples.
  - No rows → unrestricted.
  - PV only → every PV variant.
  - PV + THAR → only THAR variants.
  - BKN → all BKN locations; BKN + one location → that location only.
  - Department → divisions.
  - Expired `to_date` ignored.
  - Superadmin / bypass → unrestricted.
- **Feature:**
  - A scoped user lists enquiries / bookings / quotations / receipts and sees only in-scope rows, plus unassigned rows while `unassigned_rows = visible` (and not after flipping).
  - An out-of-scope id → 404.
  - `withoutDataScope()`, `DataScope::off()` and the `data-scope:off` route all see everything.
  - VOTF numbering and duplicate checks are unaffected by scope.
  - The API with a Sanctum token is scoped the same way.
- **Regression:** full suite (≥ 353 passed), `--group=smoke` sweep as superadmin, HTTP smoke of the Sales screens as user 40 and one scoped test user.
- **Backfill:** dry-run coverage report per entity and dimension, reviewed before running.
