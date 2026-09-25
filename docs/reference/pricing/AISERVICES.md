# Xceler8 — Cline Service Catalog

Companion to `.ai-rules/` (folder). How Rules vs Workflows vs Hooks work: `docs/CLINE_CONTEXT.md`.

Cline should **not** ingest this whole file on every prompt. Open only the section the current task needs, or invoke `/…-workflow.md`.
Open the **real PHP file** before generating code. This page is the map, not a substitute for the class.

**Repo:** `katyayan-ck/XLR8` branch `dev`
**Note on drift:** GitHub `dev` as of 2026-07-25 does not yet contain every Aug-2026 pricing class listed under `app/Services/Vehicle/Pricing/`. Those classes exist in the current working tree / artifacts and are the SSOT going forward. Prefer them over `app/Helpers/XpricingHelper.php`.

---

## 1. Namespaces

| Layer | Namespace / folder |
|---|---|
| Cross-cutting services | `App\Services` → `app/Services/` |
| Vehicle master + accessories | `App\Services\Vehicle` → `app/Services/Vehicle/` |
| Staged pricing (current) | `App\Services\Vehicle\Pricing` → `app/Services/Vehicle/Pricing/` |
| Utils | `App\Services\Utils` → `app/Services/Utils/` |
| HR journeys | `App\Services\HR` → `app/Services/HR/` |
| Quotation | `App\Services\Quotation` → `app/Services/Quotation/` |
| Importers / exporters | `App\Services\Importers`, `App\Services\Exporters` |
| Admin CRUD | `App\Http\Controllers\Admin` → `app/Http/Controllers/Admin/` |
| Models Admin | `App\Models\Admin` |
| Models Vehicle | `App\Models\Vehicle` |
| Models Pricing | `App\Models\Vehicle\Pricing` |
| Models IAM | `App\Models\IAM` |
| Models Utilities | `App\Models\Utilities\*` |
| Legacy helpers (do not grow) | `app/Helpers/` |

---

## 2. Identity, people, org, access

### `App\Services\PersonService`
`app/Services/PersonService.php`

SSOT for person / contact / address / banking / PAN / Aadhaar / GST / TAN.

| Method | Use |
|---|---|
| `find($criteria, $options)` | Smart find. String auto-detects PAN, Aadhaar, mobile, email, username/emp, person_code |
| `search($criteria, $options)` | Filtered list + `q` |
| `get($personCode, $options)` | Full profile array (or model if `asArray=false`) |
| `upsert($data, $options)` | Person + nested contacts/addresses/banking |
| `upsertContact` / `upsertAddress` / `upsertBanking` | Child rows |
| `setPrimary($type, $personCode, $id)` | Primary contact/address/bank |

Enums on the class: `DATA_TYPES`, `CONTACT_TYPES`, `ADDRESS_TYPES`, `ACCOUNT_TYPES`, `ACCOUNT_NATURES`.
`cleanPhone()` keeps 10 digits; strips `91` only on 12-digit numbers. Do not reimplement.

### `App\Services\PersonUserTypeService`
`app/Services/PersonUserTypeService.php`

Emp / Cust / Insurer associations on a person.

- `getUserTypes`, `getPrimary`, `formatForProfile`, `getSummary`
- `assign`, `setPrimary`, `remove`
- `syncFromUsers()`, `syncFromPersons()`
- Normalises `Employee→Emp`, `Customer→Cust`, `Insurance/Insu→Insurer`

### `App\Services\OrgService`
`app/Services/OrgService.php`

Cached master maps (TTL 3600): `branches()`, `locations($branchCode)`, `departments()`, `divisions($deptCode)`, `verticals()`, `segments()`, `subSegments($segmentCode)`, `models($segmentCode)`, `variants($modelCode)`, `colors($variantCode)`.

User lists honor **primary employee columns and** `xlr8_admin_user_scopes`:

- `getUsers(...)` / `getUsersForListing(...)` (`primaryOnly` flag)
- `getUpline`, `getDownline`, `getDirectReports`, `getDownlineCount`
  status = `active|inactive|all`, optional `excludeBypassUsers`

Also: `keywordValueByCode`, `getKeyValueById`, `getKeyValueByCode`, `keywordValueByParentCode`, pincode helpers, sales-consultant shortcuts.

Do not `Model::all()` these masters from new code.

### `App\Services\OrgScopeService`
`app/Services/OrgScopeService.php`

Hierarchy SSOT in `$hierarchy`: branch→location, department→division, segment→sub_segment→model→variant.

- `resolveCode($type, $value)` → canonical code; ALL/ANY/NULL → `ALL`
- `expandCodes($type, $value, $context)` → ALL expands active children, parent-filtered
- Comma-separated values supported

### `App\Services\RBACService`
`app/Services/RBACService.php`

- `canUserAccess($user, $resource, $action)` → Spatie `resource.action`. SuperAdmin = true
- `getUserPermissions` — roles + post permissions + temporal `UserRoleAssignment`. Cache 3600s
- `assignRole` / `removeRole` / `grantPermission` / `revokePermission`
- `getAccessibleResources` uses `DataScopeService` IDs

Pricing admin: permission `manage_pricing`.

### `App\Services\KeywordValueService`
`app/Services/KeywordValueService.php`

- Preferred: `getByCode`, `getCode`, `getEnum`
- Legacy: `getValueId`, `getValue`
- Cache prefix `kwv_`, TTL 3600. `clearCache($keywordCode)` after admin edits
- Codes uppercased + trimmed

### `App\Services\Utils\SynonymService`
`app/Services/Utils/SynonymService.php`

*(On the July GitHub snapshot this file may still be missing; `app/Services/Utils/` currently holds `EntityHistoryService.php`. Target path above is the locked design.)*

- `getSynonym($entityType, $value)` → canonical or null
- `resolve($entityType, $value)` → canonical or original trim
- `setSynonym($entityType, $canonical, $csv, ADD|REPLACE|REMOVE)`
- Cache per entity type, TTL 3600
- Use for Branch, Fuel, Segment, Permit, etc. **before** Excel/UI match

### `App\Services\Utils\EntityHistoryService`
`app/Services/Utils/EntityHistoryService.php`

Audit threads for entity changes. `BookingStateService` and `DocService` write through this (or `EntityHistory` model). Do not insert history rows from a controller.

---

## 3. Authentication and devices

### `App\Services\AuthService` (preferred)
`app/Services/AuthService.php`

OTP request / verify / profile / logout. Throws `ValidationException`, `AuthenticationException`, `RateLimitException`, `AccountLockedException`.

Constants: OTP 6 digits / 10 min; 5 requests per 15 min; 5 failed verifies → 30 min lock; **device limit 5**.

### `App\Services\AuthenticationService` (older sibling)
`app/Services/AuthenticationService.php`

Same flow, array `{success, code, message, http_status}`. One copy hardcoded device limit to 1 — **do not copy that**. Canonical limit is 5.

### `App\Services\OtpNotificationService`
`app/Services/OtpNotificationService.php`

Email OTP, SMS placeholder, verification-success, account-locked.
Views: `emails.otp_notification_email`, `emails.verification-success`, `emails.account-locked`.

### `App\Services\FirebaseService`
`app/Services/FirebaseService.php`

Register/update FCM token, send to device / user / many users, Android/iOS/Web configs, deep links `vdms://{entity}/{id}`, revoke + cleanup.

---

## 4. Notifications, documents, settings

### `App\Services\NotificationService`
`app/Services/NotificationService.php`

`sendAndLogNotification`, `sendToMultipleUsers`, `sendAlert`, `sendMessage`, `markAsRead`, `markAllAsRead`, `getUnreadCount`.
Always push via `FirebaseService`.

### `App\Services\DocService`
`app/Services/DocService.php`

Upload (collection `documents`), optional Vision tags when `ai_tagging_enabled`, groups + zip, Scout search, access via owner / DocAccess / RBAC / DataScope, approval hook, expiry stub.

### `App\Services\SystemSettingService`
`app/Services/SystemSettingService.php`

Typed get/set with `validation_rules` on the row. Helpers: site name/logos/theme, dealership details, GST/TDS.

### `App\Services\SystemSettingExportImportService`
`app/Services/SystemSettingExportImportService.php`

JSON / CSV import-export. Stable keys (`site.name`, `pricing.gst_rate`, …).

### `App\Services\RulesUserImportTemplateGenerator`
`app/Services/RulesUserImportTemplateGenerator.php`

Excel template (User List + Instructions). Required: Emp Code, Designation, Department. Comma-separated multi-assign. NULL child = all children. Re-import replaces assignments for that Emp Code.

Paired importer: `app/Services/Importers/RulesUserImporter.php`.
Generic users: `app/Services/Importers/UserImporter.php`, `app/Services/Exporters/UserExporter.php`.
Artisan: `app/Console/Commands/ImportUsersCommand.php`.

---

## 5. Vehicle master

### `App\Services\Vehicle\VehicleService` (primary SSOT)
`app/Services/Vehicle/VehicleService.php`

Segment → SubSegment → Model → Variant.

| Method | Use |
|---|---|
| `norm()` / `colorFromOemCode()` / `segmentFromSheetTitle()` | Normalise |
| `findOrCreateSegment` / `SubSegment` / `Model` | Masters |
| `createStubFromPriceList(...)` | Detect stub, status INCOMPLETE |
| `applyVehicleInfo($variant, $row, $userId)` | Completeness + status gate |
| `isComplete()` / `missingFields()` | Full FRS gate |
| `permitCode()` / `fuelCode()` / `kkvId()` | Keyword lookups |
| `variantsOf()` / `descendantsOf()` / `findByOemCode()` / `copySpecifications()` | Lists |

Status constants: `ACTIVE`, `INACTIVE`, `DISCONTINUED`, `ALL`.

### `App\Services\Vehicle\VehicleMasterService`
`app/Services/Vehicle/VehicleMasterService.php`

`ensureFromOemCode($data, $userId)`. Narrower completeness list (`segment_code`, `sub_segment_code`, `model_code`, `display_name`, `fuel_type_id`, `permit_id`, `drivetrain`).
For “can this be Active?” prefer `VehicleService::isComplete()`.

Helpers: `stemFromModelCode()`, `colorCodeFromModelCode()`, `segmentFromSheetTitle()`.

### July snapshot leftovers (do not grow)

| File | Note |
|---|---|
| `app/Services/Vehicle/SegmentService.php` | Older segment helper |
| `app/Helpers/VehicleHelper.php` | Large legacy helper |
| `app/Dtos/Vehicle/Segment/SegmentDto.php` | DTO only |

---

## 6. Accessory catalog

### `App\Services\Vehicle\AccessoryService`
`app/Services/Vehicle/AccessoryService.php`

- `importExcel($path, $userId)` — purge + reload named sheets
- `importExcelWithSheetOrder($path, $userId)` — numeric sheet index fallback
- `list($filters)` — four-key contract
- `listForVehicle($segment, $model, $variant, $permit)` — bundle types
- `listByType($type, ...)` — including `RTO_Tape` / `Kazam`
- `exportRows($filters)`

Types: `Accessory`, `Ceramic`, `PPF`, `Maxicare`, `GPS_VLTD`, `RTO_Tape`, `Kazam`.
Scope NULL = ANY. Fetch: all ANY **or** all concrete.
Bundle `type=all` excludes RTO_Tape and Kazam.
Import: MRP (Rounded) + Discount only. Missing → 0.

### `App\Services\Vehicle\AccessoryExportService`
`app/Services/Vehicle/AccessoryExportService.php`

Disk export via Maatwebsite + `app/Exports/VehicleAccessoriesExport.php`.

Artisan:

- `app/Console/Commands/ImportVehicleAccessories.php`
- `app/Console/Commands/ExportVehicleAccessories.php`

Do not add a third importer. July also has `app/Services/Vehicle/AccessoryImportService.php` — fold into `AccessoryService`.

---

## 7. Pricing (current namespaced SSOT)

Cline feed: `docs/PRICING_CLINE_CONTEXT.md` (screens, gates, pending, file map).

Target folder: `app/Services/Vehicle/Pricing/`
Models: `app/Models/Vehicle/Pricing/`
Config: `config/pricing.php`
Routes: pricing workflow under Backpack + `pricing_routes.php` pattern
Permission: `manage_pricing`
Log: `storage/logs/pricing/pricing_process_session_{sessionId}.log`

| Class | Path | Responsibility |
|---|---|---|
| `SheetHeaderService` | `app/Services/Vehicle/Pricing/SheetHeaderService.php` | Label → `field_code`. `findHeaderRow`, `val`, `forgetCache` |
| `PriceListVehicleDetector` | `app/Services/Vehicle/Pricing/PriceListVehicleDetector.php` | Detect OEM codes from Price List sheets only |
| `PriceListPricingImporter` | `app/Services/Vehicle/Pricing/PriceListPricingImporter.php` | Chunked price import. Skip incomplete + no-price. WEF expire/insert |
| `VehicleInfoExportService` | `app/Services/Vehicle/Pricing/VehicleInfoExportService.php` | All variants + session profiles. Model Code = OEM Code |
| `VehicleInfoImportService` | `app/Services/Vehicle/Pricing/VehicleInfoImportService.php` | Apply Vehicle Info via `VehicleService::applyVehicleInfo` |
| `AddonDiscountImportService` | `app/Services/Vehicle/Pricing/AddonDiscountImportService.php` | Dealer Charges, RSA, Shield, Exchange, Corporate |
| `AddonDiscountExportService` | `app/Services/Vehicle/Pricing/AddonDiscountExportService.php` | Active rows only. Never seed zero ANY |
| `RulesWorkbookService` | `app/Services/Vehicle/Pricing/RulesWorkbookService.php` | Insurance / RTO rules workbook |
| `InsuranceService` | `app/Services/Vehicle/Pricing/InsuranceService.php` | `quote($ctx)` company × plan |
| `RtoService` | `app/Services/Vehicle/Pricing/RtoService.php` | `quote($ctx)` most-specific match. `tax_basis` is formula text |
| `TcsService` | `app/Services/Vehicle/Pricing/TcsService.php` | Limit + rate from table or `config/pricing.php` |
| `PricingEngineService` | `app/Services/Vehicle/Pricing/PricingEngineService.php` | SSOT on-road JSON. `getPricingPayload` / `calculateAndPublish` |
| `PricingJsonContract` | `app/Services/Vehicle/Pricing/PricingJsonContract.php` | Fixed output keys |
| `PricingSessionService` | `app/Services/Vehicle/Pricing/PricingSessionService.php` | One-active-session lifecycle |
| `PricingResetService` | `app/Services/Vehicle/Pricing/PricingResetService.php` | Controlled reset |
| `PricingProcessLogger` | `app/Services/Vehicle/Pricing/PricingProcessLogger.php` | Per-session file log |

Jobs (`app/Jobs/Vehicle/Pricing/` when present):

- `DetectPricingWorkbookJob`
- `ImportPriceListsJob`
- `CalculatePricingSessionJob`
- `RecalculateVehiclePricingJob`
- `ProcessPricingWorkbookJob`

Controllers (thin):

- `app/Http/Controllers/Admin/PricingController.php` (or Vehicle/Pricing equivalents)
- `PricingWorkflowController`, `PricingApiController`, `PricingResetController`

### July snapshot leftovers (do not grow)

| File | Note |
|---|---|
| `app/Services/Vehicle/PricingEngineService.php` | Old location — move callers to `Vehicle\Pricing\PricingEngineService` |
| `app/Services/Vehicle/VehiclePricingImportService.php` | Replaced by detector + `PriceListPricingImporter` |
| `app/Services/PricingService.php` | Legacy umbrella |
| `app/Helpers/XpricingHelper.php` | ~70k legacy pricing |
| `app/Helpers/QuotesHelper.php` | ~67k legacy quotes |
| `app/Services/Quotation/QuotationPricingService.php` | Quotation consumer — should call the engine, not duplicate formulas |

Memory / Excel rules for price lists:

- `setLoadSheetsOnly([$title])`
- Cap columns at `AZ`
- Chunk 50 rows
- `disconnectWorksheets()` + `gc_collect_cycles()`
- Skip if profile is not `is_vehicle_master_complete`
- Skip if no ex-showroom and cannot derive from MM invoice / assessable+GST

---

## 8. Booking, approvals, HR, reporting

### `App\Services\BookingStateService`
`app/Services/BookingStateService.php`

```
Draft → Pending | Cancelled
Pending → Live | Cancelled
Live → Invoiced | Cancelled | Refund Requested
Refund Requested → Cancelled | Live
Invoiced → (terminal)
Cancelled → Draft
```

Always write entity history. Never set `status_kw` from a controller.

### `App\Services\ApprovalService`
`app/Services/ApprovalService.php`

**Legacy** graph-node approval (`GraphNode` / `GraphEdge` / `ApprovalHierarchy`).
This is **not** Approval Engine v1. Do not extend it into the locked engine.
CRUD: `ApprovalHierarchyCrudController`, `GraphNodeCrudController`, `GraphEdgeCrudController`.

### HR

| Class | Path |
|---|---|
| `EmployeeJourneyService` | `app/Services/HR/EmployeeJourneyService.php` |
| `HRJourneyService` | `app/Services/HR/HRJourneyService.php` |
| `UserReportingService` | `app/Services/HR/UserReportingService.php` |

Admin: `EmployeeJourneyController`, `HRRelievingController`, `HRTransferController`.

### Emp reporting topics

| Class | Path |
|---|---|
| `ReportingTopicService` | `app/Services/EmpReporting/ReportingTopicService.php` |
| `TopicReportingResolverService` | `app/Services/EmpReporting/TopicReportingResolverService.php` |

---

## 9. Backpack CRUD — how to add an admin screen

Location: `app/Http/Controllers/Admin/{Entity}CrudController.php`
Views: `resources/views/admin/{entity}/list.blade.php` (and create/edit)
Model: existing `App\Models\...` with SoftDeletes + audit columns + Spatie HasMedia when files exist.

Reference implementation: `app/Http/Controllers/Admin/BranchCrudController.php`.

Checklist:

1. Extend `Backpack\CRUD\app\Http\Controllers\CrudController`.
2. Use List/Create/Update/Delete operation traits.
3. Use `App\Http\Controllers\Admin\Traits\ScopedCrud` when the entity is org-scoped. Implement `getScopeType()`.
4. `setup()` sets model, route (`config('backpack.base.route_prefix') . '/slug'`), entity name strings.
5. Code-keyed entities: edit URL `backpack_url("slug/{$code}/edit")`.
6. Many lists are custom AG Grid pages (`gridConfig.columns` + `gridConfig.data`), not stock Backpack columns. Match the neighbouring entity.
7. Validate in `store`/`update`. Persist via domain service when one exists.
8. Media: `addMediaFromRequest($field)->toMediaCollection($collection)`.
9. `\Alert::success(...)->flash(); return redirect(backpack_url('slug'));`
10. Do not put matching / pricing / completeness rules in the CRUD controller.

Existing CRUD family (do not duplicate): Branch, Location, Department, Division, Designation, Employee + assignment CRUDs, PersonAddress, PersonBankingDetail, KeywordMaster, Keyvalue, Permission, Brand, Color, Lead, Enquiry, Booking, Insurance, Finance, Garage, Campaign, Modules, …

God-controllers (`BookingCrudController`, `EnquiryCrudController`) already exist. New behaviour belongs in a service, then a thin call from the controller.

---

## 10. Matching algorithms (copy, do not rewrite)

### Synonym then match

```
raw = trim(cell)
canonical = app(SynonymService::class)->resolve($entityType, $raw)
codes = OrgScopeService::expandCodes($type, $canonical, $parentContext)
```

### Header resolution

```
[$headerIdx, $fieldMap] = $headers->findHeaderRow($sheetCode, $matrix, $maxScan);
$value = $headers->val($row, $fieldMap, 'model_code');
```

Add new Excel labels to the **sheet_headers registry**, not to importer if-blocks.

### Accessory fetch

```php
app(AccessoryService::class)->list([
    'type'    => 'all',        // or a concrete type
    'segment' => 'PV',         // or ANY
    'model'   => 'THAR ROXX',  // or ANY
    'variant' => 'FULLCODEWD', // or ANY
    'permit'  => 'Private',    // or ANY
]);
```

### On-road payload

```php
app(PricingEngineService::class)->getPricingPayload($oemCode, [
    'channel'  => 'normal', // or csd
    'vin_type' => 'nv',     // nv | ov
    'permit'   => 'Private',
]);
```

Incomplete or Hold → do not treat as a published snapshot.

---

## 11. Console commands already in the tree

| Command class | Path |
|---|---|
| Import vehicle accessories | `app/Console/Commands/ImportVehicleAccessories.php` |
| Export vehicle accessories | `app/Console/Commands/ExportVehicleAccessories.php` |
| Import users | `app/Console/Commands/ImportUsersCommand.php` |
| Import RBAC master | `app/Console/Commands/ImportRbacMaster.php` |

Add new import/export as Artisan + service. Do not hide it only inside a CRUD action.

---

## 12. After every completed slice

Ask the user:

1. Changelog via `markwalet/laravel-changelog` (give artisan commands).
2. Laradocs FRS / usage guide via `petebishwhip/laradocs`.
3. Update `.clinerules` and this file if a **new SSOT service** or locked invariant appeared.

---

## 13. Locked documents (read when the task is in that domain)

| Doc | When |
|---|---|
| `Xceler8_Vehicle_Pricing_Machine_Spec_v3.1` | Implementing / changing pricing |
| `Xceler8_Vehicle_Pricing_Human_Guide_v3.1` | Explaining pricing to a human |
| FRS Workflow v3.1 | Pipeline stages |
| `Vehicle_Accessory_Catalog_FRS_and_Dev_Guide` | Accessory import/list |
| Approval Engine Final Behavioural Specification | Future engine only — no code yet |
