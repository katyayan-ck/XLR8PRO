---
description: SSOT service catalog. Load before writing any code that touches business logic, data access, or cross-module operations.
paths:
  - app/Services/**
  - app/Http/Controllers/**
  - app/Jobs/**
---

# XCELR8 Service Catalog — SSOT

> **Rule:** Never write standalone logic for any capability already listed here. Open the actual service file in the workspace, match its public contract (method names, return shapes, namespaces), then use or extend it.

---

## 1. Identity & Organization

### `OrgService` — `App\Services\OrgService`
SSOT for ALL org/branch/department/division/hierarchy data. Cache TTL = 3600.

Key responsibilities:
- Branch networks, locations, departments, divisions, verticals
- Reporting manager resolution (upline / downline traversal, loop-safe)
- Segment, SubSegment, VehicleModel, Variant masters (cached)
- **Never bypass with raw queries** — always use this service

### `PersonService` — `App\Services\PersonService`
SSOT for person identity management.

Key responsibilities:
- Create/update `Person`, `PersonContact`, `PersonAddress`, `PersonBankingDetail`
- Phone normalization (Indian 10-digit, country code resolution)
- PAN / Aadhaar / address sanitization — **never invent regex; use this service**
- Primary contact/address designation
- Natural key: `person_code` (immutable once assigned)

### `RBACService` — `App\Services\RBACService`
SSOT for role and permission resolution.

Key responsibilities:
- Resolve active roles/permissions for a user
- Check `manage_pricing` and other named permissions
- Temporal role assignments via `UserRoleAssignment`
- SuperAdmin wildcard bypass

---

## 2. Auth & IAM

### `AuthService` — `App\Services\IAM\AuthService` (canonical)
OTP lifecycle, device session management.

Key responsibilities:
- OTP generate/validate (6 min web / 10 min API)
- Rate limit: 5 requests per 15 min
- Failed attempts: 5 → 30 min lock
- Device limit: **5 devices per user max**
- Device session create/revoke

---

## 3. Vehicle Master

### `VehicleService` — `App\Services\Vehicle\VehicleService`
SSOT for vehicle completeness and status transitions.

Key responsibilities:
- `missingFields(variant)` — full completeness gate (all required fields)
- `isComplete(variant)` — full completeness check
- Status transitions: INCOMPLETE → ACTIVE / INACTIVE / DISCONTINUED
- `is_active = true` only when `status = ACTIVE`

### `VehicleMasterService` — `App\Services\Vehicle\VehicleMasterService`
Narrower completeness check. **Prefer `VehicleService` for the full gate.**

### `KeywordValueService` — `App\Services\KeywordValueService`
SSOT for all keyword/lookup values. Cache TTL = 3600.

**Rule:** Never query `KeyValue` model directly. Never use old `KeywordHelper`. All lookups via this service.

### `SynonymService` — `App\Services\Utils\SynonymService`
Maps typos / nicknames → canonical codes. Table: `xlr8_utils_synonyms`.

**Rule:** Always run `SynonymService` resolution BEFORE matching Branch, Fuel, Segment, Permit values in any importer.

### `SheetHeaderService` + `SynonymService` combo
For Excel importers: never hardcode header labels. Use `field_code` via `SheetHeaderService` + `SynonymService`.

---

## 4. Vehicle Pricing

All under `App\Services\Vehicle\Pricing\`

### `ImportSessionService`
Manages the gated pricing pipeline lifecycle. One active session at a time.

### `PriceListDetectService`
Detects new OEM codes from Price List sheets. Creates INCOMPLETE vehicle stubs. Never creates as INACTIVE.

### `VehicleInfoImportService`
Processes Vehicle Info workbook — enforces completeness gate. Only this service triggers status → ACTIVE.

### `PricingImportService`
Imports price data (WEF versioning). Same WEF = update live row. Different WEF = expire previous (`is_active=0`, `expired_on=WEF`) then insert.

### `AddonImportService` / `RuleImportService`
Imports add-ons and pricing rules. **Never seed zero-value addon/rule rows under ANY scope** (overrides ALL on re-import).

### `InsuranceImportService` / `RtoImportService`
Import insurance and RTO rules. On re-import: keep existing if no new data provided.

### `PricingEngineService`
SSOT for live pricing calculation and JSON snapshot generation.

Key responsibilities:
- `getPricing(oemCode)` → fixed-key JSON (see `PricingJsonContract`)
- Calculate & Publish snapshots
- Assembles: ex-showroom + incidentals + RSA/Shield + accessories + insurance + RTO + TCS − schemes

**Rule:** Quotation module calls `getPricing()`. Never recalculate from raw tables in quotation code.

### `PricingJsonContract`
Fixed key contract for the pricing JSON. Unused keys present with 0/null. **Never change keys** — consumers depend on fixed structure.

---

## 5. Accessories

### `AccessoryService` — `App\Services\Vehicle\AccessoryService`
SSOT for accessory catalog. Used by PricingEngine for accessory key injection in published JSON.

**Rule:** Do not rewrite. If accessory logic is needed, call this service.

---

## 6. Data Scoping

### `DataScopeFilter` / `ScopedQuery` — `App\Services\DataScopeFilter`
SSOT for applying branch/location/segment/model scope filters.

Rules:
- Check `bypassesDataScoping()` before applying filters
- Jobs always use `withoutDataScope()`
- User-facing models that hide rows by assignment use `ScopedQuery`

### `OrgScopeService` — `App\Services\OrgScopeService`
Resolves which branches/locations/divisions a user has access to via `xlr8_admin_user_scopes`.

---

## 7. Documents & Media

### `DocService` — `App\Services\DocService`
SSOT for document management. Uses `DocGroup`, `DocAccess`, Spatie media collections. Optional Vision AI tagging. Laravel Scout search.

**Rule:** Never create ad-hoc file columns on tables. Use Spatie Media Library via `DocService`.

---

## 8. Notifications & Communication

### `NotificationService` — `App\Services\NotificationService`
Routes notifications to Firebase/FCM via `FirebaseService`. Tables: `xlr8_utils_noty_*`.

**Rule:** Notification ≠ Communication History. Do not conflate.

### `EntityHistoryService`
Communication history for entities. Tables: `xlr8_utils_comm_*`. Trait: `HasCommunications`.

---

## 9. Reporting

### `EmployeeTopicReporter` / `ReportingTopic` resolver
Topic-based reporting. Fallback = `reporting_manager_code`.

---

## 10. System & Utils

### `SystemSettingService` — `App\Services\Utils\SystemSettingService`
SSOT for system settings. Supports JSON/CSV import-export.

### `HRJourneyService` — `App\Services\HR\HRJourneyService`
Employee journey/relieving/transfer operations.

### `UserReportingService` — `App\Services\HR\UserReportingService`
Reporting manager resolution and HR hierarchy queries.

---

## Service Usage Anti-Patterns (Never Do)

```php
// ❌ Direct raw query bypassing OrgService cache
$branches = Branch::all();

// ❌ Direct KeyValue query
$fuel = KeyValue::where('type', 'fuel')->get();

// ❌ Phone regex in controller
$phone = preg_replace('/[^0-9]/', '', $request->phone);

// ❌ Business logic in controller
public function store(Request $request) {
    $price = $request->ex_showroom + $request->rto + $request->insurance;
    Booking::create(['price' => $price]);
}

// ❌ Duplicate service logic
class NewQuotationService {
    public function getVehicle($code) {
        return VehicleVariant::where('code', $code)->first(); // use VehicleService
    }
}
```

```php
// ✅ Use OrgService
$branches = app(OrgService::class)->getBranches();

// ✅ Use KeywordValueService
$fuelTypes = app(KeywordValueService::class)->getByType('fuel');

// ✅ Use PersonService
$person = app(PersonService::class)->normalizeAndStore($data);

// ✅ Thin controller
public function store(StoreBookingRequest $request) {
    $booking = app(BookingService::class)->create($request->validated());
    return response()->json(['success' => true, 'data' => $booking]);
}
```

## Job timeout and tries
Every job must explicitly set public int $timeout and public int $tries sized to its workload; don't rely on queue defaults.

## Job batching for session fan-out
For pipelines that fan out N sub-jobs from a session, use Bus::batch() with ->allowFailures()->then()->catch()->dispatch(), and have the child job use the Batchable trait and check $this->batch()?->cancelled().

## Job failure handling
Long-running or session-tracked jobs must implement failed(Throwable $e) (or a batch ->catch() callback) to flip session/cache status to failed; don't let failures pass silently.

## Job dispatch style
Dispatch jobs from controllers with Job::dispatch(); use dispatchSync() only when a job needs to synchronously invoke another job from inside its own handle().
