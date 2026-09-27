---
description: SSOT service catalog and job rules. Load before writing business logic, controllers or jobs.
paths:
  - app/Services/**
  - app/Jobs/**
  - app/Http/Controllers/**
---

# Services — single source of truth

## Entity services (DEC-050, mandatory for writes)
Every entity has ONE write path: an `App\Support\Entity\EntityService` subclass whose `fields()` (built from
`App\Support\Entity\Field`: format, transforms, rules, label, `immutable()`, `unique([scope])`) is the only definition of
those fields. CRUD controllers, importers, APIs, jobs and seeders call `create()` / `update()` / `upsert()`; they never
validate or transform themselves, never keep FormRequest rules for these fields, and never write the table with
`DB::table()` or `Model::create()`. Business rules go in `beforeCreate/beforeUpdate` via `fail()`. The model declares
`protected string $entityService` so its transform backstop reads the same definition.
Migrated: `Vehicle\{Segment,SubSegment,VehicleModel,Variant}Service`, `Org\{Branch,Location,Department,Division,Vertical,Designation}Service`, `Person\{PersonRecord,PersonContact,PersonAddress,PersonBanking}Service`, `Org\EmployeeService`, `IAM\{User,UserScope}Service`, `Utils\{KeywordMaster,Keyvalue}Service`, `Vehicle\Pricing\Rules\{RtoRule,TcsConfig,InsBaseRule,InsIdvSlot,InsDefault,InsAddonRate}Service`, `Vehicle\Pricing\Addons\{DealerCharge,Addon,Discount}Service`, `Vehicle\Pricing\Prices\PriceService`; `VehicleService` (price-list stubs, Vehicle Info) delegates to the vehicle services.
Next: accessories. Engine-written records (sessions, flags, snapshots, history) stay with the engine. The model backstop transforms only changed attributes on update (BUG-176). On update only changed values are validated (stored legacy values never block an edit, DEC-054).
Scopes: grant/revoke/sync only via `UserScopeService` (revoke = deactivate, never delete).

Never re-implement a capability below; open the service, match its contract, extend it if needed.
Full health notes: `docs/reference/Shared-Services-Utilities-Catalog.md`.

| Capability | Service | Notes |
|---|---|---|
| Org lookups, hierarchy, users by designation/branch | `App\Services\OrgService` (static, cached 3600s) | Branch filter uses `employee.primary_branch_code`; dropdown rows: `branchRows()`, `locationRows()`, `locationsByState()`, `serviceBranches()` |
| Org entity CRUD | `App\Services\Org\*Service` + `OrgEntityGuard` | |
| Person / contacts / addresses / banking | writes: `App\Services\Person\*Service`; lookups + aggregate upsert: `App\Services\PersonService` | never hand-roll phone/PAN/Aadhaar cleanup |
| Identifier formats & normalisation | `App\Services\IdentifierService` + `App\Rules\*` | Aadhaar, PAN, mobile, GSTIN, chassis, OTF/DMS/invoice |
| Enquiry references (`XENQ-{id}`) | `EnquiryReferenceService`, `Enquiry::resolveByAnyReference()` | |
| Keyword/lookup values | reads: `App\Services\KeywordValueService` (cached); writes: `Utils\KeyvalueService` / `KeywordMasterService` | never query or write `Keyvalue` directly |
| Synonyms before matching imported values | `App\Services\Utils\SynonymService` | |
| RBAC helpers | `App\Services\RBACService`, `App\Services\IAM\{PermissionTreeService,RolePermissionService}` | |
| Row-level data scope | `App\Services\IAM\DataScopeService`, `OrgScopeService` | enforcement not yet switched on |
| OTP login, devices, tokens | `App\Services\AuthService` | Sanctum tokens (User has `HasApiTokens`) |
| Vehicle completeness/status, dropdown options | `App\Services\Vehicle\VehicleService` | `segmentOptions()`, `modelOptions(For)()`, `variantOptions()`, `colorOptions()` (colour rows, DEC-060) |
| Pricing pipeline & engine | `App\Services\Vehicle\Pricing\*` (`PricingEngineService::getPricing()`) | see `.ai/rules/modules/vehicle-pricing.md` |
| Accessories | `App\Services\Vehicle\AccessoryService` | |
| Booking sub-domains | `App\Services\Sales\Booking\Booking{Core,Kyc,Dms,Insurance,Rto,Delivery,Finance,Exchange,Refund,Otf}Service` | each tested |
| Settings | `App\Services\Platform\Settings\SettingsService` (`Settings` facade, `setting()`, `feature()`, `@setting`, `@feature`) | only write path; dotted keys, typed, scoped (`getFor`), audited; seeds in `config/platform.php` (read by exact key). `SystemSettingService` backs the legacy CRUD screen |
| Date display | `DateFormatService`, `site_date()`, `@sitedate` | |
| Chat / timeline | `App\Services\Platform\Chat\ChatService` (`Chat` facade), `HasCommunications`, `<x-chat.thread>` | EVENT vs REMARK; access = `canView()` (entity permission in `config('platform.entities')` or model `chatCanView`); `EntityHistoryService` is the v1 adapter |
| Notifications / push | `App\Services\Platform\Notify\NotifyService` (`Notify::to()->…->send()`), `<x-notify.bell>` | inbox rows + queued FCM; idempotency key; `NotificationService` is the v1 adapter |
| Documents | `App\Services\Platform\Docs\DocsService` (`Docs` facade), `HasDocuments`, `<x-docs.uploader>` | only writer of docs tables; `canView()` is the only visibility check; `DocService` is the v1 adapter |

Removed 26-09-2026 (dead, DEC-030): AuthenticationService, BookingStateService, VehicleMasterService,
SegmentService, PricingService, legacy Chat/Quotes/Task/Docs/Notification/Vehicle helpers. 28-09-2026 (DEC-060): the
last helpers (`CommonHelper`, `XCommonHelper`, `XpricingHelper`) — `app/Helpers/` no longer exists; never add helper
classes: put logic in a service, and only one-line aliases in `app/Support/helpers.php`. Legacy
`ApprovalService` (graph approve/reject) is deprecated — the FRS approval engine replaces it (DEC-063).
Platform services (DEC-061) return `App\Support\Result`; a state change goes persist → `Chat::event` → `Notify` → domain event.

## Anti-patterns
Business math in controllers · raw org/keyvalue queries · duplicate services · `Model::all()` on big tables ·
hardcoded user ids / role names (use permissions) · `dd()`/`dump()` in app code.

## Jobs
- Set `public int $timeout` and `$tries`; implement `failed(Throwable $e)` (or batch `->catch()`).
- Session fan-out: `Bus::batch()->allowFailures()->then()->catch()`; children use `Batchable` and check cancellation.
- Dispatch with `Job::dispatch()`; `dispatchSync()` only inside another job.
- Queue worker for pricing: `php artisan queue:work --timeout=1800 --tries=1`.
