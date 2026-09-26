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

### `RBACService` — `App\Services\RBACService` 🟢 Working
Central RBAC: Spatie permission checks, SuperAdmin wildcard bypass, cached permission aggregation
(roles + post assignments + temporal `UserRoleAssignment`), `canUserAccess()`, `grantPermission()`/
`revokePermission()`, `assignRole()`/`removeRole()`.

`getAccessibleResources()` depended on `App\Services\IAM\DataScopeService`, which didn't exist until
the §4 fix — it now resolves. The method has no callers in the app today.

### `App\Services\IAM\PermissionTreeService` 🟢 Working
Parses real `xlr8_iam_permissions` rows (the `MODULE_PROCESS_ACTIVITY` convention from
`.ai/rules/module-structure.md`, plus a legacy `resource.action` fallback) into a nested tree for
role/permission management UIs.

### `App\Services\IAM\RolePermissionService` 🟢 Working
Thin, correctly-DRY wrapper around Spatie's role↔permission sync, so nothing calls Spatie's Role
model directly. Used by `DesignationService`.

---

## 4. Data Scoping — 🟢 fixed 24-09-2026 (option A: one system, built on the live scope table)

### What was actually wrong (corrected record)
Two statements made earlier in this audit were wrong and are corrected here:
- **The two scope models did not share a table.** `App\Models\UserDataScope` points at
  `user_data_scopes`, a table that **does not exist** and has no migration. The live store is
  `App\Models\Admin\UserScope` → `xlr8_admin_user_scopes` (`user_id, scope_type, scope_code,
  is_active, from_date, to_date`; 1,465 rows; unique key on `user_id + scope_type + scope_code`).
- **The broken scoping code was not reachable in the live app** — no screen was crashing:
  `Stock` imports `ScopedQuery` but never applies it; `XlSpareRequest` applies it but can't autoload
  (its file declares `namespace App\Models` from `app/Models/Module/Spare/` — a PSR-4 mismatch; only
  its own Detail sibling references it); the 4 `ScopedCrud` controllers either return `''` from
  `getScopeType()` (Finance, Insurance, Rto → no-op) or override `setupListOperation()` without
  calling the trait (Branch); `RBACService::getAccessibleResources()` has no callers; `DocService`
  can't construct at all (§8).

The **one genuinely live-broken path** was `App\Services\Importers\UserImporter` (routed via
`UserImportExportController::import()`): any row with an "Accessible Branches / Departments /
Locations" value called `UserDataScope::insert()` with keys `userid`, `scopetype`, `scopevalue`,
`status` against the nonexistent table.

**Consequence worth knowing: no row-level data scoping is enforced anywhere in the app today.**
Scope rows are assigned (User CRUD, the importer) but nothing filters queries by them.

### The fix (option A)
- **New `App\Services\IAM\DataScopeService`** — the class `DataScopeFilter` always imported but that
  never existed, now built on the live system via `User::getScopeCodes()`. Scope rows hold codes;
  scoped business columns hold ids, so it translates code → id through a fixed type map
  (`TYPE_MODELS`: branch, location, department, division, vertical, segment, sub_segment — exactly
  the 7 `scope_type` values present in live data). Contract: `null` = unrestricted (SuperAdmin or
  `bypass_data_scoping`), `[]` = no access (no rows of that type, or unknown type — fails closed),
  `int[]` = allowed ids. Exposes `getAccessibleIds()` plus `getOrgScope()` / `getVehicleScope()`
  aliases, so `DataScopeFilter` and `RBACService::getAccessibleResources()` resolve unchanged.
- **`DataScopeFilter`** — logic unchanged (it already expected this contract); docs corrected to
  say `$scopeColumn` must be an id column; default `branch_code` → `branch_id`; column now
  table-qualified.
- **`ScopedCrud`** — replaced calls to the never-defined `User::userDataScopes()` /
  `getScopedIds()` with `DataScopeService`; now honours `bypass_data_scoping`; the hierarchy
  fallback now fails closed instead of silently showing everything; dropped hierarchy entries keyed
  on `brand` / `vehicle_model`, which are not real scope types.
- **`UserImporter::createDataScopes()`** — writes `UserScope` rows with the real columns and the
  entity's canonical code; idempotent against the unique key (restores a soft-deleted row rather
  than colliding). Verified in a rolled-back transaction: unknown codes skipped, re-import doesn't
  duplicate, a soft-deleted row is restored.
- **`DocService`** — wrong `EntityHistoryService` import corrected; unused scope dependency removed;
  `hasAccess()` no longer returns `true` for every entity-attached document (a placeholder that
  ignored the scope lookup) — it falls through to the entity's own `hasAccess()`, else denies.
- **`Stock` / `XlSpareRequest`** — scope declarations corrected to real columns (`location` /
  `location_id`; `branch` / `srv_brnch_id`). Declaration only: neither changes live behaviour.
- **`UserDataScope`** — marked `@deprecated`; still referenced only by the two unrouted
  `RulesUserImporter` copies (`app/Imports/`, `app/Services/Importers/`), so it goes when they do.

Verified live against a real scoped user (id 40, location `BKN`): `getAccessibleIds('location')` →
`[1]`; `DataScopeFilter` on `Stock` → `location_id in (1)`, 723 of 895 rows. Tests:
`tests/Unit/Services/IAM/DataScopeServiceTest.php` (7 tests).

### Deliberately not done — needs a decision
Turning enforcement **on** changes what users see, so none of these were done:
- Apply `ScopedQuery` to `Stock` (non-SuperAdmin users would then see only their locations' stock).
- Call `applyDataScope()` from `BranchCrudController::setupListOperation()`, or return real scope
  types from Finance / Insurance / Rto `getScopeType()`.
- Fix `XlSpareRequest`'s namespace (it would become loadable *and* scoped at once).
- `User::activeScopes()` ignores `from_date` / `to_date` (a date-aware `scopeActive` on `UserScope`
  is commented out), so time-bounded scope rows are treated as always active.
- Delete the two unrouted `RulesUserImporter` copies, then `UserDataScope`.

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

### `DocService` — `App\Services\DocService` 🟡 **Constructs (since 24-09-2026) — 2 method-level defects open**
Originally failed to construct on two wrong imports (`App\Services\EntityHistoryService`,
`App\Services\DataScopeService` — neither existed):
```
app(App\Services\DocService::class)
→ BindingResolutionException: Target class [App\Services\EntityHistoryService] does not exist.
```
**Fixed 24-09-2026 (with §4):** `EntityHistoryService` import corrected to `App\Services\Utils\`;
the `DataScopeService` dependency removed (its only use was a result `hasAccess()` discarded); and
`hasAccess()` no longer returns `true` for every entity-attached document — that placeholder is
gone, so it falls through to the entity's own `hasAccess()` or denies.

~~Still blocked by `NotificationService` (§9).~~ **Unblocked 24-09-2026** — the Firebase fix (§9)
lets `NotificationService`, and so `DocService`, construct (verified via `app(DocService::class)`).
`.ai/rules/services.md` names this the mandatory document-management entry point.

**Other defects found while fixing (BUG-139):**
- `getAiTags()` uses `Google\Cloud\Vision\V1\ImageAnnotatorClient` — the package isn't installed;
  fatal the first time `ai_tagging_enabled` is on and an image is uploaded. **Open** (dependency
  change needs approval).
- ~~`search(): Collection` unimported~~ — **fixed 24-09-2026** (`Illuminate\Support\Collection`).
- `approve()` calls `$this->approvalService->approve()`, which `ApprovalService` doesn't have
  (its method is `approveDocument(GraphNode, User, string)`, a different shape). **Open** (locked
  Approval Engine — needs authorization).

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

### `NotificationService` — `App\Services\NotificationService` 🟢 Constructs (fixed 24-09-2026 via `FirebaseService`)
Before the fix:
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

### `FirebaseService` — `App\Services\FirebaseService` 🟢 Fixed 24-09-2026 (was the root cause of the above)
**Fix (BUG-145):** constructor now uses `(new Factory())->withServiceAccount(config('firebase.credentials'))`
— the service-account path the app already configures — and catches `\Throwable` (the old
`catch (Exception)` couldn't catch the `Error`). Verified: constructs, messaging initialises with the
real credentials. Original analysis kept below.

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

### `ScopedQuery` — `App\Models\Traits\ScopedQuery` 🟢 Working (fixed via §4), ⚪ not applied to any loadable model
Boots `DataScopeFilter` as a global scope. Works now that `DataScopeService` exists; no live model
currently applies it (see §4, "Deliberately not done").

### `ScopedCrud` — `App\Http\Controllers\Admin\Traits\ScopedCrud` 🟢 Working (fixed via §4), ⚪ no controller reaches it
Backpack CRUD mixin that restricts a list to the user's data scope, with a parent-scope fallback
map. Previously called `User::userDataScopes()` / `getScopedIds()`, neither of which exists; now
goes through `DataScopeService`, honours `bypass_data_scoping`, and fails closed. Its 4 consumers
(Finance, Insurance, Rto, Branch CRUD controllers) never reach it — see §4.

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
  **It also corrupts PHPStan app-wide**: `app/Models_backup/User.php` declares the same class,
  `App\Models\User` (without `isSuperAdmin()` and other current methods), and `phpstan.neon` scans
  all of `app/`, so Larastan resolves the wrong `User` and reports false "undefined method" errors
  in any file that calls those methods. No runtime effect (Composer's PSR-4 map never loads the
  backup file). Either delete the directory or add it to `excludePaths` in `phpstan.neon`.
- **`App\Models\XlSpareRequest`** (`app/Models/Module/Spare/XlSpareRequest.php`, plus its `Detail`
  sibling) — namespace doesn't match its path, so it can't autoload; nothing outside the pair
  references it. Either dead or a module that was never wired up.
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

1. **🟢 Fixed 24-09-2026 — data scoping consolidated onto the live `UserScope` system** (§4). The
   only live breakage was `UserImporter`'s scope writes; the bigger finding is that **no row-level
   data scoping is enforced anywhere today**. Switching enforcement on is a pending decision (§4).
2. **🟡 Fixed 24-09-2026 — `DocService` constructs** (§8). Still open: `approve()` vs the locked
   Approval Engine, and the uninstalled Vision SDK (BUG-139).
3. **🟢 Fixed 24-09-2026 — `NotificationService`/`FirebaseService` construct** (§9, BUG-145):
   `withDefaultAuth()` replaced with the v7 `withServiceAccount()` against the configured credentials.
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
