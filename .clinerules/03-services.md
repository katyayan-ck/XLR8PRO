# Services Reference Catalog

This catalog outlines all 51 service classes across `app/Services/`, detailing their core responsibilities, key methods, caching patterns, dependencies, and business rules.

---

## 1. Identity, People & Access Services

### `App\Services\PersonService`
- **Purpose**: SSOT for all person master records, multiple phone/email contacts, residential/office addresses, bank accounts, and PAN/Aadhaar identity resolution.
- **Key Methods**:
  - `find($identifier)` / `search($query)` / `get($personCode)`
  - `upsert(array $data)` / `upsertContact($personCode, $contactData)`
  - `upsertAddress($personCode, $addressData)` / `upsertBanking($personCode, $bankingData)`
  - `cleanPhone(string $phone)`: Preserves 10-digit number; strips leading `91` on 12-digit and `0` on 11-digit strings.
  - `detectCriteria(string $raw)`: Regex matching for PAN, Aadhaar, phone, email, emp_code.
  - `formatProfile(string $personCode)`: Builds consolidated profile DTO.

### `App\Services\PersonUserTypeService`
- **Purpose**: Manages multi-role identity tags (`Emp`, `Cust`, `Insurer`, `Vendor`, etc.) linked to a `Person`.
- **Key Methods**: `getUserTypes($personCode)`, `getPrimary($personCode)`, `assign($personCode, $type, $isPrimary)`, `setPrimary($personCode, $type)`, `syncFromUsers()`.

### `App\Services\OrgService`
- **Purpose**: Dealership organization trees, branch/location hierarchies, departments/divisions, reporting upline/downline, user scoping.
- **Cache Policy**: Master entity caching TTL = 3600 seconds (`Cache::remember`).
- **Key Methods**:
  - `branches()`, `locations($branchCode)`, `departments()`, `divisions($deptCode)`, `verticals()`
  - `getUsers($filters)` / `getUsersForListing($filters)`: Respects primary employee org columns AND flexible `xlr8_admin_user_scopes`.
  - `getUpline($userId)` / `getDownline($userId, $status, $excludeBypass)`: Hierarchical traversal.
  - `getKeyValueById($id)` / `getKeyValueByCode($code)`.

### `App\Services\OrgScopeService`
- **Purpose**: Recursive code expansion and resolution for Org and Vehicle hierarchies.
- **Key Methods**:
  - `resolveCode($type, $value)`: Resolves code/name to canonical code; expands `ALL`/`ANY` wildcards.
  - `expandCodes($type, $value, $context)`: Expands comma-separated values or `ALL` tokens into concrete active child codes.

### `App\Services\RBACService`
- **Purpose**: Permission resolution, Spatie role assignments, temporal access limits.
- **Cache Policy**: User permission list cached for 3600s per user.
- **Key Methods**:
  - `canUserAccess($user, $resource, $action)`: Evaluates `resource.action` permission (SuperAdmin returns `true`).
  - `getUserPermissions($userId)`: Merges direct permissions, roles, post permissions, and temporal assignments.
  - `assignRole($user, $role, $validFrom, $validTo)`.

---

## 2. Authentication, Devices & Notifications

### `App\Services\AuthService` (SSOT)
- **Purpose**: Typed OTP-based mobile/email authentication, token issuance, session and device tracking.
- **Rules**: 6-digit OTP / 10-minute validity; 5 requests per 15 min rate limit; 5 failed attempts locks account for 30 minutes; max 5 registered devices.
- **Key Methods**: `requestOtp($identifier)`, `verifyOtp($identifier, $token, $deviceInfo)`, `logout($user, $deviceUuid)`, `refreshDeviceSession($user, $deviceUuid)`.

### `App\Services\OtpNotificationService`
- **Purpose**: Email and SMS dispatch for OTP codes, login alerts, and account lockout notifications.
- **Key Methods**: `sendOtpEmail($email, $otp, $expiresInMinutes)`, `sendLockoutEmail($email, $lockoutMinutes)`.

### `App\Services\FirebaseService`
- **Purpose**: Firebase Cloud Messaging (FCM) push notification engine.
- **Key Methods**: `sendToDevice($token, $title, $body, $data)`, `sendToUser($userId, $title, $body, $data)`, `sendToUsers(array $userIds, $title, $body, $data)`, `registerToken($userId, $token, $deviceUuid)`.

### `App\Services\NotificationService`
- **Purpose**: Notification persistence and push dispatch coordination.
- **Key Methods**: `sendAndLogNotification($userId, $type, $title, $body, $data)`, `markAsRead($notificationId, $userId)`, `getUnreadCount($userId)`.

### `App\Services\DocService`
- **Purpose**: Document upload with Spatie Media Library collection `documents`, optional Google Vision AI metadata tagging, ZIP batch download, Scout search, and access control.
- **Key Methods**: `upload($file, $userId, $group, $attributes)`, `generateZip(array $docIds)`, `checkAccess($docId, $userId)`.

### `App\Services\SystemSettingService` & `SystemSettingExportImportService`
- **Purpose**: Typed key-value system settings (`pricing.gst_rate`, `site.name`, etc.) with validation and audit trail; JSON/CSV export/import.
- **Key Methods**: `get($key, $default)`, `set($key, $value, $userId)`, `exportToJson()`, `importFromJson($json, $userId)`.

---

## 3. Vehicle Master & Accessories

### `App\Services\Vehicle\VehicleService` (SSOT)
- **Purpose**: Master entity lifecycle for Segment -> SubSegment -> Model -> Variant (one row per colour).
- **Invariants**:
  - `model.code` = stem WITHOUT colour (OEM Model uppercased).
  - `variant.code` = full OEM code WITH colour.
  - New stubs created via Price List detection are marked `INCOMPLETE` (never `INACTIVE`).
  - Active status requires completeness gate.
- **Key Methods**:
  - `createStubFromPriceList($oemCode, $oemModel, $oemVariant, $sheetTitle, $userId)`
  - `applyVehicleInfo(Variant $variant, array $row, int $userId)`: Validates mandatory and conditional specs (CC / Motor / GVW) and updates status.
  - `isComplete(Variant $variant)`: Returns boolean based on full FRS field gate.
  - `missingFields(Variant $variant)`: Returns array of missing attribute keys.
  - `descendantsOf($type, $code)`.

### `App\Services\Vehicle\AccessoryService` & `AccessoryExportService`
- **Purpose**: Catalog import (purge + reload), bundle listing, filtering by vehicle scope (`segment`, `model`, `variant`, `permit`), and spreadsheet export.
- **Key Methods**:
  - `importExcel(string $filePath, int $userId)`: Purge and reload sheets.
  - `list(array $filters)`: Strict 4-key contract (all 4 `ANY` or all 4 concrete).
---

## 4. Vehicle Pricing Pipeline Services (`App\Services\Vehicle\Pricing`)

| Service | Primary Responsibility | Key Methods & Patterns |
|---|---|---|
| `SheetHeaderService` | Excel column header resolution registry | `findHeaderRow($sheet, $matrix, $maxScan)`, `val($row, $fieldMap, $fieldCode)`, `forgetCache()`. Hard aliases for legacy header variations. |
| `PriceListVehicleDetector`| Scans Price List sheets for new OEM codes | `detectFromSheets($path, $session, $userId)`. Extracts segment token from title, creates `INCOMPLETE` stubs via `VehicleService`. |
| `PriceListPricingImporter`| Chunked price list importer | `import($filePath, $session, $sheetTitle, $userId)`. Chunk size = 50. Skips incomplete/unpriced rows. Manages WEF expiry. Emits `ChangeFlag`. |
| `VehicleInfoExportService`| Generates Vehicle Info completion workbook | `exportSessionVehicles($session)`. Highlights missing specs in yellow. |
| `VehicleInfoImportService`| Ingests Vehicle Info specifications | `import($filePath, $session, $userId)`. Applies specs through `VehicleService::applyVehicleInfo()`. |
| `AddonDiscountImportService`| Addon and discount matrices ingestion | `importAddons($path, $type, $userId)`, `importDiscounts($path, $type, $userId)`. Purges/expires only the targeted group. |
| `AddonDiscountExportService`| Addon/Discount Excel export | `exportActiveAddons()`, `exportActiveDiscounts()`. Never seeds zero-value ANY rows. |
| `RulesWorkbookService` | RTO and Insurance rules importer/exporter | `importRules($path, $session, $userId)`, `keepExistingRules($session)`. |
| `InsuranceService` | Commercial insurance quote calculator | `quote($ctx)`: Company x Plan matrices. Standard combo (OD+TP+NilDep+Consumables). OD factor on sum of IDVs. |
| `RtoService` | RTO tax calculation | `quote($ctx)`: Matches most-specific branch/permit rule. Evaluates tax formulas in `tax_basis`. |
| `TcsService` | TCS tax calculation | `calculate($exShowroom, $ctx)`. Uses configurable threshold and rate from `xlr8_vehicle_pricing_tcs_configs`. |
| `PricingEngineService` | SSOT for on-road pricing calculation | `build($variantCode, $branchCode, $permit, $options)`, `calculateAndPublish($session)`. Verifies hold and completeness gate. |
| `PricingJsonContract` | Canonical JSON schema enforcement | `formatPayload(array $data)`: Fixed key contract. Zero-filled for unused components. |
| `PricingSessionService` | Single-active-session lifecycle | `startSession($wef, $userId)`, `getActiveSession()`, `discardSession($id)`, `completeSession($id)`. |
| `PricingResetService` | Controlled database rollback | `resetToStart($sessionId)`: Reverts staging records and restores previous active prices. |
| `PricingProcessLogger` | Dedicated per-session file logger | `log($session, $level, $message, $context)`: Writes to `storage/logs/pricing/`. |

---

## 5. Other Domain & HR Services

### `App\Services\BookingStateService`
- **Purpose**: Strict state machine governing vehicle booking order status transitions (`Draft` -> `Pending` -> `Live` -> `Invoiced` / `Cancelled` / `Refund Requested`).
- **Key Methods**: `transition($booking, $targetStatus, $userId, $remarks)`, `canTransition($current, $target)`. Always logs to `EntityHistory`.

### `App\Services\HR\HRJourneyService` & `EmployeeJourneyService`
- **Purpose**: Employee onboarding, department/branch transfers, promotions, status changes, and payroll revision tracking.
- **Key Methods**: `initiateTransfer($empCode, $transferData)`, `recordPromotion($empCode, $newDesig, $payroll)`, `terminateEmployee($empCode, $reason)`.

### `App\Services\HR\UserReportingService`
- **Purpose**: Manages manager-reportee organizational hierarchy relationships and effective reporting date intervals.
- **Key Methods**: `assignManager($userId, $managerId, $fromDate)`, `getReportingChain($userId)`.

### `App\Services\Utils\SynonymService`
- **Purpose**: Typo and alias normalization dictionary for branches, fuels, segments, permits, and models before matching.
- **Cache Policy**: Cached per entity type, TTL = 3600s.
- **Key Methods**: `resolve(string $entityType, string $value)`, `setSynonym(string $entityType, string $canonical, string $csv)`.

  - `listForVehicle($segment, $model, $variant, $permit)`.
  - `listByType($type, $segment, $model, $variant, $permit)`.

### `App\Services\KeywordValueService`
- **Purpose**: Standardized enums and keyword masters (`FUEL`, `PERMIT`, `BODY_TYPE`, `VEHICLE_STATUS`).
- **Cache Policy**: Prefixed with `kwv_`, TTL = 3600s.
- **Key Methods**: `getByCode($keyword, $code)`, `getCode($id)`, `getEnum($keyword)`.
