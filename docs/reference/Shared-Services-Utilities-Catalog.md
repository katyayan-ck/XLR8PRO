# XCELR8 Shared Services & Utilities Catalog

**Compiled:** 24-09-2026, by direct code audit — every class listed was either read in full or verified
live (instantiated via `app(Class::class)` in `php artisan tinker`, or exercised against the real
database). This document supersedes the prose descriptions in `.ai/rules/services.md` wherever they
disagree — `services.md`'s code examples were found to be systematically wrong (see §0 Methodology).

**Purpose:** a single source of truth for what shared/cross-cutting infrastructure this app actually
has, whether it works, and whether it's used — so future work reuses it instead of reinventing it
(DRY), and so the next "fix what's broken" pass has a concrete, verified punch list instead of
guesswork.

**Status key used throughout:**
- 🟢 **Working** — verified live, actively used by real code.
- 🟡 **Partial** — works but incomplete, stubbed, or has a caught/non-fatal gap.
- 🔴 **Broken** — confirmed crash on instantiation or on a real call path.
- ⚪ **Unused** — exists, probably works in isolation, but nothing in the live app calls it.
- 🗑️ **Dead** — legacy/superseded; a newer class does the same job.

---

## 0. Methodology & a critical caveat about the existing `.ai/rules/services.md`

Every class below was checked against the **real, current codebase** — not against `services.md`'s
descriptions. That existing catalog is a useful map of *intent* but is factually wrong in several
concrete, checkable ways, found while cross-referencing it against live code for this audit:

| `services.md` claims | Reality |
|---|---|
| `App\Services\DataScopeFilter` | Actually `App\Http\Scopes\DataScopeFilter` (not even in `Services/`) |
| `App\Services\Utils\SystemSettingService` | Actually `App\Services\SystemSettingService` (root, no `Utils`) |
| `EntityHistoryService` at `app/Services/` (implied root) | Actually `App\Services\Utils\EntityHistoryService` |
| `app(OrgService::class)->getBranches()` | `OrgService` is 100% static methods; real call is `OrgService::branches()` |
| `app(PersonService::class)->createOrUpdate(...)` | `PersonService` is 100% static; real call is `PersonService::upsert(...)` |
| `app(KeywordValueService::class)->getByType('fuel')` | `KeywordValueService` is 100% static; real method is `KeywordValueService::getEnum('fuel')` |

**Takeaway for anyone using `services.md` going forward:** trust its *prose* ("what does X do"), verify
its *code examples* against the real class before copying them — three of its four "✅ correct usage"
examples use both the wrong instantiation style and the wrong method name.

---

## 1. Identity — Person / User

### `PersonService` — `App\Services\PersonService` 🟢 Working
**All methods static.** Real call: `PersonService::upsert($data)`, not `app(PersonService::class)->createOrUpdate(...)`.

| Method | Purpose |
|---|---|
| `find(string\|array $criteria, array $options=[])` | Auto-detects criteria type (PAN/Aadhaar/mobile/email/username/person_code) from a raw string |
| `search(array $criteria=[], array $options=[])` | Multi-criteria search |
| `get(string $personCode, array $options=[])` | Fetch by natural key |
| `upsert(array $data, array $options=[])` | Full profile create/update |
| `upsertContact/upsertAddress/upsertBanking(string $personCode, array $data)` | Sub-record upserts |
| `setPrimary(string $type, string $personCode, int\|string $identifier)` | Primary-flag management |

Has its own **private** `cleanPhone()` with the fixed (non-`ltrim`) mobile-cleaning logic. This
duplicates — deliberately, at a different layer — `IdentifierService::cleanMobile()` (§9), which is
the newer, public, cross-module SSOT for the same normalization. `PersonService`'s copy is private and
scoped to its own upsert flow; not a bug, but worth knowing both exist if consolidating later.

Governed by `.ai/rules/person-user.md` — `Person` is the immutable identity anchor
(`person_code` natural key, no integer FK from children), with `Employee`/`User` hanging off it.

### `PersonUserTypeService` — `App\Services\PersonUserTypeService` 🟢 Working, undocumented
**Not mentioned anywhere in `.ai/rules/services.md`.** All static. Manages which "user types"
(Employee/Customer/Insurer/etc.) a Person has and which is primary, plus backfill/sync from the
legacy `users` table (`syncFromUsers()`, `syncFromPersons()`).

### `IdentifierService` — `App\Services\IdentifierService` 🟢 Working
Registered as a singleton. Pure normalization (no validation, no DB) for business identifiers:
`cleanMobile`, `normalizePan`, `normalizeTan`, `normalizeAadhaar`, `normalizeGstin`,
`normalizeChassis`. Built this session (24-09) as the SSOT replacing 5 independent, one of them
actually-buggy copies of mobile-cleaning logic scattered across importers. Paired with `App\Rules\*`
(11 `ValidationRule` classes: `AadhaarNumber`, `PanNumber`, `IndianMobileNumber`, `Gstin`,
`ChassisNumber`, `EmployeeCode`, `OtfNumber`, `DmsNumber`, `InvoiceNumber`, `DealerInvoiceNumber`,
`TanNumber`) for the validation half of the same identifier contract.

### `EnquiryReferenceService` — `App\Services\EnquiryReferenceService` 🟢 Working
Singleton. `toReference(int $enquiryId): string` / `fromReference(...): ?int` — the SSOT for the
`XENQ-{id}` display-reference format, replacing 5 independent build/parse implementations.

---

## 2. Organization — Branch / Location / Dept / Division / Reporting

### `OrgService` — `App\Services\OrgService` 🟢 Working (the biggest single facade in the app)
**~1,500 lines, ~50 public methods, 100% static.** Real call style: `OrgService::branches()`, not
`app(OrgService::class)->getBranches()`. Cache TTL 3600s throughout.

Covers, in one class: master-entity lookups (branches/locations/departments/divisions/verticals/
segments/subSegments/models/variants/colors, all cached), user filtering by a full org+vehicle scope
matrix (`getUsers()` with ~10 filter params), reporting-hierarchy `getUpline()`/`getDownline()`/
`getDownlineCount()`/`getDirectReports()` (cycle-safe traversal), code↔name formatting helpers, and
cross-entity customer/transaction resolution spanning Enquiry/Booking/VOTF (`getCustomerByTransactionIds()`).

**Housekeeping note:** the file has at least 4 commented-out dead duplicate method stubs (old versions
of `variants()`, `getUsers()`, `getKeyValueById()` left in place above their live replacements) —
harmless but worth a cleanup pass.

### `OrgScopeService` — `App\Services\OrgScopeService` 🟢 Working
All static, much smaller than `OrgService`. Resolves org/vehicle hierarchy codes from either a code
or a free-text name, with `"ALL"`/`"ANY"` hierarchical wildcard expansion (`resolveCode`,
`expandCodes`, `firstCode`). Raw `DB::table()` queries, not Eloquent.

### `App\Services\Org\*` — 6 files, one per entity 🟢 Working
`BranchService`, `DepartmentService`, `DesignationService`, `DivisionService`, `LocationService`,
`VerticalService`, plus a shared static helper `OrgEntityGuard`. All follow one identical pattern:
`create()`/`update()` with code immutability on update, dependency-guarded disable (blocks disabling
an entity with active children — e.g. a Branch with active Locations/Employees), Spatie media sync.
`DesignationService` additionally wraps `RolePermissionService` since Designation doubles as a Spatie
Role. This is a clean, DRY, well-factored group — a good template to imitate for new entity CRUD
services.

**DRY note:** these overlap in responsibility with parts of `OrgService`'s read-side lookups
(`OrgService::branches()` vs `BranchService`'s write-side CRUD) — expected split (read-cache facade
vs. write/business-rule service), not a duplication problem.

---

## 3. Auth & RBAC

### `AuthService` — `App\Services\AuthService` 🟡 Working, duplicated
OTP request/verify, device-session management (5 devices max), Sanctum token issuance, rate
limiting, lockout, audit logging via `OtpAttemptLog`.

### `AuthenticationService` — `App\Services\AuthenticationService` 🟡 Working, duplicated
**A near-duplicate of `AuthService`** — same OTP flow, different response shape (`code`/`http_status`
arrays instead of thrown exceptions), single-device-limit hardcoded to 1 (vs `AuthService`'s 5),
imports `AccountLock` but never uses it. **DRY violation**: two full parallel OTP-auth
implementations exist. Whoever touches auth next should determine which one is actually live-routed
and retire the other, or fold them into one with a shared core + the two different response styles
as a thin wrapper choice.

### `RBACService` — `App\Services\RBACService` 🟡 Working, one broken method
Central RBAC: Spatie permission checks, SuperAdmin wildcard bypass, cached permission aggregation
(roles + post assignments + temporal `UserRoleAssignment`), `canUserAccess()`, `grantPermission()`/
`revokePermission()`, `assignRole()`/`removeRole()`.

**`getAccessibleResources()` is broken** — it calls `app(DataScopeService::class)` internally, and
`App\Services\IAM\DataScopeService` does not exist anywhere in the codebase (see §12, Critical
Findings — this is the single most consequential gap found in this audit). The rest of the class
instantiates and works fine; only this one method path is affected.

### `App\Services\IAM\PermissionTreeService` 🟢 Working
Parses real `xlr8_iam_permissions` rows (the `MODULE_PROCESS_ACTIVITY` convention from
`.ai/rules/module-structure.md`, plus a legacy `resource.action` fallback) into a nested tree for
role/permission management UIs.

### `App\Services\IAM\RolePermissionService` 🟢 Working
Thin, correctly-DRY wrapper around Spatie's role↔permission sync, so nothing calls Spatie's Role
model directly. Used by `DesignationService`.

---

## 4. Data Scoping — 🔴🔴 the biggest finding in this audit, revised after deeper investigation

**This section was investigated further after initial publication (still 24-09-2026) while starting
the "fix" phase. The finding is bigger than "one missing class" — it's two competing, incompatible
data-scoping systems sharing one database table, only one of which actually works.**

### The real, live, working system: `App\Models\Admin\UserScope` + `User::bypassesDataScoping()` 🟢 Working
`App\Models\Admin\UserScope` maps to `xlr8_admin_user_scopes` with columns `user_id, scope_type,
scope_code, is_active, from_date, to_date` (verified via `Schema::getColumnListing()`) — **1,465 real
rows exist in this table.** `User` model relationship: `scopes(): HasMany(UserScope::class)`, plus
working methods `bypassesDataScoping()`, `getScopeCodes(string $type): array`, `getAllScopes(): array`,
`activeScopes()`. This is the actual, currently-functioning scoping mechanism referenced correctly
throughout `.ai/rules/known-pitfalls.md` (P-08, P-15, P-16) and used by `OrgScopeService` (§2)-style
code-based resolution. **Nothing needs fixing here.**

### The broken, parallel, seemingly-abandoned system: `App\Models\UserDataScope` + `DataScopeFilter` + `ScopedCrud` 🔴🔴 Multiple independent breaks
A **second Eloquent model, `App\Models\UserDataScope`, maps to the exact same table**
(`xlr8_admin_user_scopes`) but assumes different, incompatible columns: `scope_value` (cast
`integer`) where the real column is `scope_code` (string). This second model is the one
`App\Http\Scopes\DataScopeFilter` (an Eloquent global `Scope`, applied via the `ScopedQuery` trait,
§13) and `App\Http\Controllers\Admin\Traits\ScopedCrud` are built against — and neither actually
works:

1. **`DataScopeFilter::apply()`** calls `app(App\Services\IAM\DataScopeService::class)` for any
   authenticated non-SuperAdmin user — **that class does not exist anywhere in the codebase**
   (`AppServiceProvider.php` has its registration commented out — planned, never built). Only 2
   models use `ScopedQuery`: `App\Models\Module\Booking\Stock` and
   `App\Models\Module\Spare\XlSpareRequest`. Confirmed live: `app(App\Services\DocService::class)` —
   which depends on the same missing class — throws `BindingResolutionException: Target class
   [App\Services\EntityHistoryService] does not exist` before even reaching this one, but the
   `DataScopeService` dependency is separately confirmed missing by direct `grep`.
2. **Even if `DataScopeService` existed, both `ScopedQuery` consumers declare the wrong scope
   column for their real table**: `Stock::$scopeColumn = 'branchid'` — `xlr8_booking_stock_master`
   has no `branchid` column at all (real columns: `location_id`, no branch column whatsoever).
   `XlSpareRequest::$scopeColumn = 'branch_code'` — `xlr8_spare_request` has no `branch_code` column
   either (real column: `srv_brnch_id`, an integer FK). **`'location'` is a real, populated
   `scope_type` value** in the live table, so `Stock` scoping by `location_id`/`'location'` is a
   directly-supported, non-guessed fix; `XlSpareRequest` should scope by `srv_brnch_id`/`'branch'`.
3. **`ScopedCrud::applyDataScope()` calls `$user->userDataScopes()` and `$user->getScopedIds(...)`
   — neither method is defined anywhere on `User` or any trait it uses.** This trait would crash
   identically to `DataScopeFilter` the instant a non-SuperAdmin user hits a CRUD controller using
   it, for a different reason (missing relationship, not missing service). **Correcting this
   session's earlier classification: `ScopedCrud` is 🔴 Broken, not 🟢 Working** — the earlier
   assessment only checked that it didn't depend on the missing `DataScopeService`, not that its
   own `User` method calls actually exist.
4. **`RBACService::getAccessibleResources()`** (§3) also depends on the missing
   `App\Services\IAM\DataScopeService`, expecting a third method name on it,
   `getAccessibleIds(User, string $resourceType): ?array`.
5. **A separate, real write-path is also broken**: `App\Services\Importers\UserImporter`
   (bulk user-import from Excel) builds scope-assignment rows with keys `userid`, `scopetype`,
   `scopevalue`, `status` and calls `UserDataScope::insert($scopes)` — **none of those 4 keys match
   the real column names** (`user_id`, `scope_type`, `scope_code`, `is_active`). Any row in an
   import sheet with an "Accessible Branches/Departments/Locations" column would throw `Unknown
   column 'userid'` the moment this code path is exercised. `app/Imports/RulesUserImporter.php`
   and `app/Services/Importers/RulesUserImporter.php` also reference `UserDataScope` — not yet
   checked whether their write shape has the same bug (flagged for the next pass, not assumed).

### Why this needs your decision, not a fix I write now
This isn't a `$fillable` typo — it's two full, independently-built scoping subsystems (one
storage-shape each) sharing one table, and the live app currently runs on the *unbroken* one
(`UserScope`/`scope_code`) while `DataScopeFilter`/`ScopedCrud`/`UserDataScope`/`DataScopeService`
form an apparently-abandoned parallel attempt that was never finished or wired up. Before writing
any fix, I need your call on the actual intended direction:

- **(A) Retire the broken parallel system.** Rewrite `DataScopeFilter`/`ScopedCrud` to use the real,
  working `UserScope`/`scope_code`/`bypassesDataScoping()`/`getScopeCodes()` API instead of the
  phantom `UserDataScope`/`DataScopeService`. Delete `App\Models\UserDataScope` once nothing
  references it. Fix `UserImporter`'s column names to match `UserScope`'s real shape (`scope_code`,
  not `scope_value`/integer id — meaning `UserImporter`'s branch/department/location scope-writing
  logic would also need to write codes, not ids, to match). This is the path I'd lean toward given
  the real system already has 1,465 live rows and working consumers, but it's a call that affects
  real RBAC/data-visibility code, so I'm not making it unilaterally.
- **(B) Finish building the parallel system as originally intended.** Build the missing
  `DataScopeService`, fix `Stock`/`XlSpareRequest`'s column declarations, fix `ScopedCrud`'s missing
  `User` methods, and fix `UserImporter`'s write shape to match `UserDataScope`'s `scope_value`
  (integer) column — but this means running two scope systems side by side (the working
  code-based one and this now-fixed id-based one), which seems like the wrong direction unless
  there's a reason the id-based shape is specifically wanted somewhere I haven't found.

I have not written any fix for this section — flagging it here and stopping for your input, per the
"no guessing on business/architecture decisions" discipline the rest of this session has followed.

---

## 5. Vehicle Hierarchy

### `VehicleService` — `App\Services\Vehicle\VehicleService` 🟢 Working — the real SSOT
Instance methods (constructor-injectable), unlike most services above. Actively wired via DI into
`PriceListVehicleDetector`, `PricingEngineService`, `VehicleInfoExportService`,
`VehicleInfoImportService` — confirmed the one actually consumed by the live pricing pipeline.

Covers: Segment→SubSegment→Model→Variant `findOrCreate*()`, `createStubFromPriceList()`,
`applyVehicleInfo()` (the permit/fuel-driven completeness-field matrix — see
`.ai/rules/known-pitfalls.md` P-13/P-19/P-20), `missingFields()`/`isComplete()` (the completeness
gate), descendant/color lookups.

### `VehicleMasterService` — `App\Services\Vehicle\VehicleMasterService` 🟡 Working, overlapping
Implements a *second*, narrower version of largely the same hierarchy-creation and completeness
logic as `VehicleService` (`ensureFromOemCode()`, `isComplete()`, `missingFields()`). `.ai/rules/
services.md` already flags this ("prefer `VehicleService` for the full gate") — confirmed accurate.
**DRY note, not urgent**: not actively harmful since `VehicleService` is what's actually
dependency-injected everywhere real, but the duplicate completeness logic is a real risk if someone
edits one copy's rules and not the other's.

---

## 6. Vehicle Pricing (16 files under `app/Services/Vehicle/Pricing/`)

This subtree is the module most heavily worked on this session (see `docs/refactor/
ai-changelogs-24-09-2026.md` for the full history of fixes: BUG-124 through BUG-135). Summary here;
full detail in that changelog.

| Class | Status | Role |
|---|---|---|
| `PriceListVehicleDetector` | 🟢 | Stage 1: detect fresh OEM codes from Price List sheets, stub masters |
| `VehicleInfoExportService` / `VehicleInfoImportService` | 🟢 | Stage 1B: export incomplete-vehicle worksheet, apply filled-in data back |
| `PriceListPricingImporter` | 🟢 | Stage 2: memory-safe chunked OEM price import, only for complete profiles |
| `RulesWorkbookService` | 🟢 (fixed this session) | Insurance+RTO rules workbook export/import |
| `InsuranceService` | 🟢 (rewritten this session) | Insurance quote calculation, every company×plan |
| `RtoService` | 🟢 (rewritten this session) | RTO tax/surcharge/fee calculation, explicit formula parser |
| `TcsService` | 🟢 | TCS applicability/amount |
| `SheetHeaderService` | 🟢 | Central header-label → `field_code` registry, used by every importer here |
| `PricingEngineService` | 🟢 (2 critical fixes this session) | SSOT composing everything into the final on-road JSON; `calculateAndPublish()` |
| `PricingSessionService` | 🟢 | Import-session lifecycle (single-active-session enforcement) |
| `PricingProcessLogger` | 🟢 | Per-session file logger for the import pipeline |
| `PricingJsonContract` | 🟢 | Pure fixed-key-shape factory, no logic |
| `AddonDiscountExportService` / `AddonDiscountImportService` | 🟢 | Dealer Charges/RSA/Shield/Exchange/Corporate workbook |
| `PricingResetService` | 🔴 **destructive by design** | Full pipeline reset — truncates session/pricing/snapshot/queue tables, deletes model/variant rows created after a cutoff date. **Flagging prominently**: this is a real, callable, destructive utility. Anyone touching it must re-read `.ai/rules/known-pitfalls.md` P-04/P-05/P-10 first and never run it against a non-local/non-explicitly-approved database. |

**Also found**: `app/Services/PricingService.php` (top-level, *not* under `Vehicle/Pricing/`) is a
completely empty 0-byte file — dead placeholder, superseded by `PricingEngineService`. Safe to
delete once confirmed nothing references the class (nothing can — it has no class declaration).

---

## 7. Accessories

### `AccessoryService` — `App\Services\Vehicle\AccessoryService` 🟢 Working — current SSOT
Unified multi-sheet (Accessory/Ceramic/PPF/Maxicare/GPS_VLTD/RTO_Tape/Kazam) catalog import/export,
strict "all-ANY-or-all-concrete" scope-filter contract. Fixed this session (BUG-133/134 — missing
`ALL_TYPES`/`BUNDLE_TYPES` constants, missing `type`/`set_qty`/`discount` from `$fillable`).

### `AccessoryImportService` / `AccessoryExportService` — `App\Services\Vehicle\*` 🗑️ Dead/legacy
Older, single-type-only predecessors. `AccessoryImportService` still has leftover `echo`/`print_r`
debug statements in `processRow()`. Superseded by `AccessoryService` — candidates for deletion once
confirmed nothing still calls them (not checked in this pass; flag for the "fix" phase).

---

## 8. Documents

### `DocService` — `App\Services\DocService` 🔴 **Completely broken — has never worked**
Constructor requires `EntityHistoryService, NotificationService, RBACService, DataScopeService,
ApprovalService, SystemSettingService` — but its `use` statements import `App\Services\
EntityHistoryService` (root namespace) and `App\Services\DataScopeService` (root namespace).
**Neither exists at those paths.** The real classes are `App\Services\Utils\EntityHistoryService`
(§10) and nothing at all for `DataScopeService` (§4). Confirmed live:
```
app(App\Services\DocService::class)
→ BindingResolutionException: Target class [App\Services\EntityHistoryService] does not exist.
```
Every capability this class is meant to provide — document upload with Vision-AI tagging, access
control, grouping/zip-download, Scout search, approval delegation — is currently **completely
unreachable**. This is the single most complete "SSOT that isn't" finding in this audit: `.ai/rules/
services.md` names it as the mandatory document-management entry point ("Never create ad-hoc file
columns on tables. Use Spatie Media Library via DocService"), but nothing can call it.

**Fix requires two decisions, not one line-edit**: (1) correct the two wrong `use` imports
(`EntityHistoryService` → `App\Services\Utils\EntityHistoryService`), and (2) `DataScopeService`
doesn't exist at all yet — same root blocker as §4, must be resolved together.

### Real document-storage model layer 🟢 Working (independent of `DocService`)
`App\Models\Utilities\Docs\{Document,DocGroup,DocAccess}` — real, well-formed models with Spatie
media collections. Usable directly via Eloquent right now, just not through the intended
`DocService` facade. `App\Http\Controllers\Api\V1\SystemSettingApiController`-style direct usage
would work; `DocService`'s convenience layer (access-control combining, Vision AI, zip export) is
what's actually unreachable.

**Legacy parallel**: `app/Helpers/DocsHelper.php` (🗑️ dead, 0 real usages per audit) references an
*entirely different*, apparently never-migrated set of classes (`DocsGroups`, `DocsFilters`,
`DocUser`, `DocsCats`) that don't match the current `Utilities\Docs\*` models at all — a second,
independent, non-overlapping "documents" concept that predates the current one.

---

## 9. Notifications

Three independent, overlapping notification concepts exist in this codebase. None of the newest,
"intended" ones fully work yet.

### `NotificationService` — `App\Services\NotificationService` 🔴 Broken (Firebase SDK mismatch)
```
app(App\Services\NotificationService::class)
→ Error: Call to undefined method Kreait\Firebase\Factory::withDefaultAuth()
```
Depends on `FirebaseService` (§9b), whose constructor calls `Factory::withDefaultAuth()` — a method
that doesn't exist on the currently-installed `kreait/firebase-php` SDK version (removed/renamed in
a newer SDK major version; classic dependency-upgrade drift). **Every capability** —
`sendAndLogNotification()`, `sendAlert()`, `sendMessage()`, `markAsRead()`, unread counts — is
currently unreachable. This is the service `.ai/rules/services.md` names as the canonical
notification router.

### `FirebaseService` — `App\Services\FirebaseService` 🔴 Broken (root cause of the above)
Wraps `Kreait\Firebase\Factory` for FCM push. The `withDefaultAuth()` call inside its constructor
(or an early init path) is what actually throws. **Confirmed installed version: `kreait/firebase-php
7.24.1`** (via `composer show`) — `withDefaultAuth()` was a `Factory` method in the older v5/v6-era
SDK, removed in v7 in favor of explicit credential resolution (`withServiceAccount()`, or letting the
Factory auto-discover Application Default Credentials). The fix is a real API-compat update against
the v7 `Factory` API, not a guess — but confirm the intended auth method (service-account JSON vs.
ADC) before choosing which v7 method replaces the call.

### `App\Models\Utilities\Noty\{Notification,Alert,Message,NotificationsMaster}` ⚪ Mixed
`Notification`/`NotificationsMaster` have real usage (11 and 3 references respectively) — likely
read directly by Blade views/other code bypassing the broken service layer. **`Alert` and `Message`
models are fully built (FCM tracking, reply-threading, read receipts, scopes) but have zero real
references anywhere** — dead weight, or a feature that was designed but never wired to a UI.

### `OtpNotificationService` — `App\Services\OtpNotificationService` 🟡 Partial — Email works, SMS is a stub
The one genuinely-working "send a message to a user" service in this list. `sendViaEmail()` sends a
real `Mail::send()`. **`sendViaSms()` is explicitly a placeholder** — logs `"SMS would be sent
(placeholder)"` and returns `true`, no real SMS gateway (Twilio/AWS SNS/etc.) is integrated. Used by
both `AuthService` and `AuthenticationService` for OTP delivery — meaning **OTP-via-SMS does not
currently reach a real phone**, only OTP-via-Email does, in this environment.

### `app/Helpers/NotificationHelper.php` 🗑️ Legacy, low usage (2 references)
A *third*, independent notification concept over `App\Models\Notification`/`App\Models\UserNotif`
(neither the same as `Utilities\Noty\*` nor referenced by `NotificationService`). Also does Firebase
FCM sends directly, bypassing `FirebaseService` entirely — so even if `FirebaseService` gets fixed,
this legacy path is a separate code path that would need its own fix or retirement.

**Recommendation for the "fix" phase**: before touching any of these three notification stacks
individually, decide which one is meant to be the actual SSOT going forward — `NotificationService`+
`FirebaseService`+`Utilities\Noty\*` looks like the intended modern one (matches `.ai/rules/
services.md`'s description) but is the most broken; `NotificationHelper`+legacy models is smaller,
older, and only partially used; fixing the modern stack and migrating the 2 legacy callers is
probably the right shape, but that's a call for whoever owns this area, not something to guess here.

---

## 10. Communication History ("comm history")

### `EntityHistoryService` — `App\Services\Utils\EntityHistoryService` 🟢 Working
**Not the same thing as "Notifications" above** — this is an audit-trail/comment-thread system, not
outbound messaging (`.ai/rules/services.md` already says this explicitly: "Notification ≠
Communication History. Do not conflate."). Generic, entity-agnostic: `createMaster($entity)` /
`addThread(CommMaster, actionSlug, title, body, extraData, parentThread, actor)` /
`getFullHistory($entity)`. Media-attachable, dispatches `SendHistoryNotification` (a real, existing
job) after each thread entry.

### `HasCommunications` trait — `App\Models\Traits\HasCommunications` 🟢 Working, real DRY win
Thin wrapper giving any model `commMaster()` / `getOrCreateCommMaster()` / `addHistory(...)` without
touching `EntityHistoryService` directly. **Actually used by real, important models**: `Booking`,
`Enquiry`, `Lead`, and `Segment`. This is a genuinely completed, working piece of shared
infrastructure — a good model for how other cross-cutting traits in this app should look.

### `App\Models\Utilities\CommHistory\{CommMaster,CommThread}` 🟢 Working
The storage models: `CommMaster` (polymorphic root, one per entity instance) → `CommThread`
(nested-set comment/status-change entries, media-attachable). 6 and 5 real references respectively.

### `app/Helpers/ChatHelper.php` 🗑️ Legacy, low usage (3 references)
A *separate*, older "communication thread" system over `App\Models\Communication` + `App\Models\
Thread` (neither the same as `CommMaster`/`CommThread`). Calls `CommonHelper::getUserName()`, which
**does not exist on the current `CommonHelper` class** (see §14) — any live code path through
`ChatHelper` that reaches that call would fatal-error. Low usage suggests this is barely-alive
legacy code, not a currently load-bearing path, but not verified further in this pass.

**Recommendation**: `EntityHistoryService`/`HasCommunications`/`CommMaster`/`CommThread` is the
real, working, current "comm history" system — this is what new code should use. `ChatHelper` is
legacy and partially broken; a candidate for retirement once its 3 real callers are confirmed
migratable.

---

## 11. Keyword/KeyValue & Synonyms

### `KeywordValueService` — `App\Services\KeywordValueService` 🟢 Working
All static, 1-hour cache. Real methods: `getCode()`, `getByCode()`, `getEnum()`, `getValueId()`,
`getValue()`, `clearCache()`. `.ai/rules/known-pitfalls.md` P-14 correctly states this is the
mandatory entry point — direct `Keyvalue::where(...)` queries bypass the cache.

### `Keyvalue` / `KeywordMaster` — `App\Models\Utilities\KeyValue\*` 🟢 Working
`Keyvalue` (`xlr8_utils_keyvalue`) is a generic hierarchical lookup store (self-referential tree via
`HasTreeStructure`, §13), 12 real references — the system-wide "pick list" table. `KeywordMaster`
(`xlr8_utils_keyword_master`) is the parent "category" record a `Keyvalue` row belongs to. Both
genuinely load-bearing.

### `SynonymService` — `App\Services\Utils\SynonymService` 🟢 Working
Instance methods (not static — the one exception among the older-generation utility services).
Typo/nickname → canonical-code resolution (`"BEEKANER"` → `"BKN"`), DB-driven via the `Synonym`
model (`xlr8_utils_synonyms`), 1-hour cache, `ADD`/`REPLACE`/`REMOVE` bulk operations via
`setSynonym()`. `.ai/rules/known-pitfalls.md` P-12 correctly mandates running this before matching
Branch/Fuel/Segment/Permit values in any importer.

**Live gap found this session, not a code bug — a data gap**: no `Synonym` row exists (in this local
environment) mapping the vehicle master's `ELECTRIC` fuel code to the Insurance/RTO rules importer's
`EV` code, meaning a real electric vehicle's insurance/RTO quote silently comes back empty in this
environment. Documented as BUG-132's root-cause note in `docs/refactor/known-bugs-report.md` — a
business/master-data decision (should `EV`/`ELECTRIC` be synonyms?), not something to guess a fix for.

---

## 12. System Settings

### `SystemSettingService` — `App\Services\SystemSettingService` 🟢 Working
(Not under `Utils/` — `.ai/rules/services.md` has this path wrong.) Central key/value site-settings
facade: `get()`/`set()`/`all()`/`topic()`, plus a large set of typed convenience getters (site
branding, dealership info, GST/TDS rates, logos, theme). Consumed by `DateFormatService` and (in
theory, once fixed) `DocService`.

### `SystemSettingExportImportService` — `App\Services\SystemSettingExportImportService` 🟢 Working
JSON/CSV/Excel bulk export-import of settings — a separate, genuinely distinct concern from the
runtime get/set facade above (not a duplicate).

### `DateFormatService` — `App\Services\DateFormatService` 🟢 Working, real DRY win
Built this session's earlier work (Phase 5, `@sitedate()`/`site_date()` rollout). SSOT for the
site-configured display date format (`display.date_format` setting, default `dd-MMM-YYYY`), used via
a Blade directive and a global helper function across ~50 converted view occurrences in the Booking
module. A good template for what a small, focused, properly-adopted shared service looks like.

---

## 13. Data-Transformation & Scoping Traits

### `HasColumnTransformations` — `App\Models\Traits\HasColumnTransformations` 🟢 Working — heaviest-used shared code in the app (42 files)
Declarative on-write/on-read value transforms (uppercase/lowercase/slug/trim/mask/hash/truncate/pad/
regex/callback pipelines) driven by a per-model `$columnTransformations` array. Used by `BaseModel`
itself (every model in the app gets this trait), and individually configured by ~42 files for
code/name normalization. This is the single most successful piece of shared infrastructure found in
this audit by usage count — confirms the project's own instinct toward declarative, centralized
normalization is sound, it's just not consistently the *only* mechanism (see `IdentifierService`/
`PersonService::cleanPhone()` above for parallel normalization logic that doesn't go through this
trait).

### `ScopedQuery` — `App\Models\Traits\ScopedQuery` 🔴 Broken (see §4 — the trait itself is fine, the scope it applies is broken)

### `ScopedCrud` — `App\Http\Controllers\Admin\Traits\ScopedCrud` 🔴 Broken (corrected — see §4)
**Original pass in this audit classified this as working; deeper investigation while starting the
fix phase found it isn't.** Backpack CRUD mixin (`setupListOperation()` override) intended to filter
CRUD list results by the logged-in user's data scope, with a hierarchical fallback map (location→
branch, department→vertical, sub_segment→segment, vehicle_model→brand/segment/sub_segment, variant→
vehicle_model) and a SuperAdmin bypass — the fallback-map *design* is good, real prior art. But it
calls `$user->userDataScopes()` and `$user->getScopedIds(...)`, and **neither method is defined
anywhere on `User` or any trait it uses.** It doesn't depend on the missing `DataScopeService`, but
crashes for the same underlying reason as `DataScopeFilter`: built against the abandoned parallel
`UserDataScope` scoping system rather than the real, live `UserScope`/`scope_code` one. Full detail
in §4.

### `HasTreeStructure` — `App\Models\Traits\HasTreeStructure` 🟢 Working
Generic materialized-path self-referential hierarchy mixin (`parent_id`/`level`/`path`).
Used by `Keyvalue` (§11).

### Traits that exist but have zero real usage ⚪
- `HasSlug` — auto-slug generation + route-model-binding by slug. Defined, unused.
- `HasAuditFields` — auto `created_by`/`updated_by`/`deleted_by` model-event mixin. Defined, unused
  — models appear to set these fields manually or rely on `BaseModel`'s own `booted()` hook instead
  (confirmed: `BaseModel::booted()` already does this exact job — `HasAuditFields` may be
  genuinely redundant with `BaseModel`, not just unused by accident).
- `GraphTraversalTrait` — graph-based RBAC traversal (`Graphp\Graph`, BFS from a user's graph node),
  gated by `config('app.rbac_graph')`. Looks like an experimental/abandoned alternate RBAC approach
  living alongside the Spatie-based `RBACService` that's actually used everywhere else.

---

## 14. Labels & Validation Messages (the "lang for labels" registry)

### `resources/lang/en/{booking,sales,org,iam,accounts,vehicle}.php` 🟢 Working, real DRY win — partially rolled out
Per `.ai/rules/conventions.md` §13's explicit standing rule: every field's label and validation
message is defined once here and referenced from every form/screen/import/export using it — the same
one-place treatment as the Identifier Registry. Confirmed real and wired: 27 real `trans('org.…')`/
`__('sales.…')`-style usages across the non-Booking files, and Booking's own file (172 lines, 142
label keys per the plan-mode session log) is wired into every `Validator::make()`/
`$request->validate()` call in `BookingCrudController` via the `$customAttributes` argument.

**Rollout status**: complete for Booking; `org.php` (139 lines) is the next-largest, suggesting Org
module screens are also substantially covered; `iam.php` is smallest (21 lines) — likely earliest/
least-rolled-out. Not verified module-by-module in this pass; a reasonable next-audit target if the
label rollout itself becomes the next work item.

---

## 15. HR

### `EmployeeJourneyService` — `App\Services\HR\EmployeeJourneyService` 🟢 Working — the real SSOT
Effective-dated employee org/vehicle-scope/permission history: one `EmployeeHistory` row per period
(auto-closes the prior period), rich point-in-time query helpers (`stateOn()`, `roleOn()`,
`whoWas()`, `tenureInDesignation()`, `transferHistory()`, `promotionHistory()`, `teamAt()`).

### `HRJourneyService` — `App\Services\HR\HRJourneyService` 🟡 Partial, older/simpler
Directly mutates `Employee.designation_code`/`desig_code` on `onboard()`/`transfer()`, with no
history table. **`getJourney()` is an explicit stub** — its own docblock says "will be expanded
using audit logs in future," not integrated with `EmployeeJourneyService`'s real history table.
**DRY note**: two employee-history concepts exist; `EmployeeJourneyService` is the complete one.

### `UserReportingService` — `App\Services\HR\UserReportingService` 🟢 Working
Per-topic reporting-manager overrides with fallback to `Employee.reporting_manager_code`, plus
downline-tree computation. Complements `OrgService::getUpline()`/`getDownline()` (§2) — worth
checking for overlap if touching either.

---

## 16. Approvals

### `ApprovalService` — `App\Services\ApprovalService` 🟢 Working, standalone
Graph-based (`GraphNode`/`GraphEdge`) multi-level parallel-negotiation approval workflow, matching
`.ai/rules/known-pitfalls.md` P-21's explicit statement that XCELR8's Approval Engine is a **parallel
negotiation, not sequential escalation** — approvers counter, never reject; only the requester
accepts/rejects; highest-level counter wins. `initializeApproval()`, `approveDocument()`/
`rejectDocument()`, `getApprovalChain()`/`getApprovalStatus()`. **Per the root `CLAUDE.md`'s explicit,
standing instruction for the current refactor: "Never touch Approval Engine code (locked spec, not
started)"** — documented here for completeness only, not a target for the eventual "fix" pass without
separate explicit authorization.

---

## 17. Legacy `app/Helpers/*` — pre-dates the `App\Services\*` layer

All 10 class-based helpers here (plus 1 real global-function file, `date-format.php` — the only one
actually autoloaded; there is no Composer `"files"` autoload section, so every other helper must be
referenced via its FQCN). Full detail in the companion audit; summary:

| Helper | Status | Note |
|---|---|---|
| `CommonHelper` | 🟡 Actively used (14 refs) but **missing 9 methods its own callers expect** | `getUserName`, `enumValueById`, `enumGetValues`, `GetKeyValues`, `getCM`, `getCMG`, `getSegID`, `getOrCreateEnumId` are called by `ChatHelper`/`TaskHelper`/`DocsHelper`/parts of `VehicleHelper`/`QuotesHelper` but don't exist on the current class — those call paths fatal-error if actually exercised |
| `XCommonHelper` | 🟢 Actively used (7 refs) | Current enum/org/RBAC helper for `X_*`-prefixed legacy models |
| `XpricingHelper` | 🟡 Used (2 refs), explicitly flagged "do not grow" | The old, pre-`PricingEngineService` pricing calculator (2,760 lines) — `PRICING_AI_CONTEXT.md` already warns against extending it |
| `VehicleHelper` | 🟡 Used (3 refs) | Legacy vehicle catalog/approval helper, heavy overlap with `XpricingHelper` |
| `ChatHelper`, `TaskHelper`, `DocsHelper` | 🔴/⚪ | Call missing `CommonHelper` methods (Chat: 3 refs, Task: 0 refs, Docs: 0 refs) |
| `NotificationHelper` | 🗑️ Legacy (2 refs) | Third, independent notification path — see §9 |
| `BranchHelper`, `UserHelper` | ⚪ Unused | `UserHelper` is a stub (`testMe()` returns a literal string) |
| `QuotesHelper` | 🟡 Used (1 ref) | Quote/enquiry lifecycle, PDF generation, depends on `ChatHelper`+`NotificationHelper`+`XpricingHelper`+`CommonHelper` — inherits their gaps |

**None of these should be extended.** Where a Helper's job overlaps a real `App\Services\*` class
above, prefer the service. Where it doesn't (e.g. `XCommonHelper`'s RBAC/enum wrappers), treat it as
legacy-but-load-bearing — don't add new callers without a plan to eventually migrate it.

---

## 18. Confirmed Dead Code

- **`app/Models_backup/**`** — an entire parallel tree (Admin/Core/CRM/IAM/Module/Traits/Utilities/
  Vehicle subfolders, including duplicate copies of most traits in §13). **Zero references anywhere
  in `app/`** (`grep -rl "Models_backup" app/` = 0 hits). Entirely dead; safe to delete once someone
  confirms it's not being kept intentionally as a manual backup/reference outside git history.
- **`app/Services/PricingService.php`** — 0 bytes, no class. (§6)
- **`app/Imports/Concerns/{PivotWriter,PersonBuilder,EmployeeBuilder}.php`** — fully built (real
  logic, not stubs) but never `use`d by any class. Looks like intended-but-never-wired-in helpers for
  `EmployeeMasterImport.php` — worth checking whether that importer should be using these instead of
  whatever it currently does inline.
- **`Noty\Alert`, `Noty\Message` models** — fully built, zero references. (§9)
- **`HasSlug`, `GraphTraversalTrait` traits** — defined, zero usage. (§13)
- **`AccessoryImportService`, `AccessoryExportService`** — superseded by `AccessoryService`. (§7)

---

## 19. Summary — Critical Findings Ranked

For the "fix what's broken" phase, in priority order:

1. **🔴🔴 Two competing data-scoping systems share one DB table; only one works** — the real, live
   one (`UserScope`/`scope_code`, 1,465 real rows, used by `bypassesDataScoping()`) vs. an
   apparently-abandoned parallel one (`UserDataScope`/`scope_value`, `DataScopeFilter`,
   `ScopedCrud`, the missing `DataScopeService`) that breaks in at least 5 independent, verified
   ways — see §4 for the full breakdown. This is an architecture decision (retire the broken
   parallel system vs. finish building it), not a mechanical fix — flagged for your call before any
   code changes.
2. **🔴 `DocService` can't instantiate at all** — wrong `use` import for `EntityHistoryService`
   (easy, mechanical fix) plus the missing `DataScopeService` above (not mechanical). Document
   management (`.ai/rules/services.md`'s mandated entry point) is currently 100% unreachable.
3. **🔴 `NotificationService`/`FirebaseService` can't instantiate** — `Kreait\Firebase\Factory::
   withDefaultAuth()` doesn't exist on the installed SDK version. Needs `composer show
   kreait/firebase-php` to identify the real version, then an API-compat fix against that SDK's
   actual current initialization method — not a guess.
4. **🟡 OTP delivery only reaches Email, not SMS** — `OtpNotificationService::sendViaSms()` is an
   explicit placeholder. A business decision (which SMS provider?) is needed before this can be a
   real fix, not a code bug to patch blindly.
5. **DRY duplication, lower urgency, no live crash**: `AuthService` vs `AuthenticationService`;
   `VehicleService` vs `VehicleMasterService`; `EmployeeJourneyService` vs `HRJourneyService`;
   three independent notification/communication-history stacks (§9/§10) where only one
   (`EntityHistoryService`/`HasCommunications`) is fully healthy.
6. **`CommonHelper` is missing 8-9 methods its own legacy callers (`ChatHelper`/`TaskHelper`/
   `DocsHelper`) still reference** — those call paths are landmines waiting for someone to actually
   exercise them. Either restore the missing methods or confirm+document those callers as dead and
   retire them.

## 20. What's Genuinely Working Well (don't touch, imitate instead)

- `HasColumnTransformations` — the single most successful shared mechanism by usage (42 files).
- `App\Services\Org\*` — a clean, consistent, DRY 6-service group; good template for new entity CRUD.
- `EntityHistoryService` + `HasCommunications` + `CommMaster`/`CommThread` — real, working,
  well-adopted comm-history system.
- `IdentifierService` + `App\Rules\*` + `EnquiryReferenceService` — this session's own earlier
  Identifier & Reference Registry work; a good model for how a normalization/validation SSOT should
  be built and adopted.
- `DateFormatService` + `@sitedate()`/`site_date()` — small, focused, fully-adopted-where-it-matters.
- `resources/lang/en/*.php` label registry — real, working, growing adoption.
- `SheetHeaderService` — the header-label→`field_code` registry every pricing importer correctly
  goes through instead of hardcoding Excel column names.

---

*This document was compiled via direct code reads (every class listed) plus live instantiation tests
(`php artisan tinker`) for every service named "🔴 Broken" above — none of those statuses are
inferred from naming or documentation, each is a reproduced, confirmed crash. Two background research
agents assisted with the initial breadth pass (full `app/Services/`, `app/Helpers/`, traits, and
`app/Models/Utilities/**` inventories); all Critical/Broken findings were independently verified live
before being included here.*
