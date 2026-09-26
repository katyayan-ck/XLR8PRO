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
Migrated: `Vehicle\SegmentService`, `SubSegmentService`, `VehicleModelService`, `VariantService`.
Next: Org masters, Person, Employee/User (+ importer), KeyValue, Pricing entities.

Never re-implement a capability below; open the service, match its contract, extend it if needed.
Full health notes: `docs/reference/Shared-Services-Utilities-Catalog.md`.

| Capability | Service | Notes |
|---|---|---|
| Org lookups, hierarchy, users by designation/branch | `App\Services\OrgService` (static, cached 3600s) | Branch filter uses `employee.primary_branch_code` |
| Org entity CRUD | `App\Services\Org\*Service` + `OrgEntityGuard` | |
| Person / contacts / addresses / banking | `App\Services\PersonService` | never hand-roll phone/PAN/Aadhaar cleanup |
| Identifier formats & normalisation | `App\Services\IdentifierService` + `App\Rules\*` | Aadhaar, PAN, mobile, GSTIN, chassis, OTF/DMS/invoice |
| Enquiry references (`XENQ-{id}`) | `EnquiryReferenceService`, `Enquiry::resolveByAnyReference()` | |
| Keyword/lookup values | `App\Services\KeywordValueService` (cached) | never query `Keyvalue` directly |
| Synonyms before matching imported values | `App\Services\Utils\SynonymService` | |
| RBAC helpers | `App\Services\RBACService`, `App\Services\IAM\{PermissionTreeService,RolePermissionService}` | |
| Row-level data scope | `App\Services\IAM\DataScopeService`, `OrgScopeService` | enforcement not yet switched on |
| OTP login, devices, tokens | `App\Services\AuthService` | Sanctum tokens (User has `HasApiTokens`) |
| Vehicle completeness/status | `App\Services\Vehicle\VehicleService` | |
| Pricing pipeline & engine | `App\Services\Vehicle\Pricing\*` (`PricingEngineService::getPricing()`) | see `.ai/rules/modules/vehicle-pricing.md` |
| Accessories | `App\Services\Vehicle\AccessoryService` | |
| Booking sub-domains | `App\Services\Sales\Booking\Booking{Core,Kyc,Dms,Insurance,Rto,Delivery,Finance,Exchange,Refund,Otf}Service` | each tested |
| Settings | `App\Services\SystemSettingService` / `SystemSetting::get/set/getByTopic` | |
| Date display | `DateFormatService`, `site_date()`, `@sitedate` | |
| Entity history (timeline) | `App\Services\Utils\EntityHistoryService`, `HasCommunications` trait | Booking uses `addHistory()` |
| Notifications / push | `NotificationService` → `FirebaseService` | legacy; rebuilt in Track B |
| Documents | `DocService` | partly broken (BUG-139); rebuilt in Track B |

Removed 26-09-2026 (dead, DEC-030): AuthenticationService, BookingStateService, VehicleMasterService,
SegmentService, PricingService, legacy Chat/Quotes/Task/Docs/Notification/Vehicle helpers. Legacy
`ApprovalService` (graph approve/reject) is deprecated — the FRS approval engine is built in Track B.

## Anti-patterns
Business math in controllers · raw org/keyvalue queries · duplicate services · `Model::all()` on big tables ·
hardcoded user ids / role names (use permissions) · `dd()`/`dump()` in app code.

## Jobs
- Set `public int $timeout` and `$tries`; implement `failed(Throwable $e)` (or batch `->catch()`).
- Session fan-out: `Bus::batch()->allowFailures()->then()->catch()`; children use `Batchable` and check cancellation.
- Dispatch with `Job::dispatch()`; `dispatchSync()` only inside another job.
- Queue worker for pricing: `php artisan queue:work --timeout=1800 --tries=1`.
