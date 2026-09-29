# Module guides — models and services (with a card per module)

How to use every business model and service in Track A: what each public method takes and returns, the rules behind
it, worked examples, gotchas and how to test. Companion to the platform utilities guides in
[`tech-guides/platform/`](../platform/README.md) (Settings, Notify, Chat, Docs, Tasks, Tickets, Approvals, Templates,
Email / SMS / WhatsApp / Telephony, UI kit).

**Start with [core.md](../architecture/core.md)** — BaseModel, User, traits, entity services (the only write path), `Result`, helpers.

| Guide | Covers |
|---|---|
| [core.md](../architecture/core.md) | `BaseModel`, `User`, `HasColumnTransformations`, `HasTreeStructure`, `HasDataScope`, `EntityService` + `Field`, `Result`, `site_date()` / `DateFormatService` |
| [org.md](org.md) | branches, locations, departments, divisions, verticals, designations (= roles), employees; `OrgService`, `OrgScopeService`, org entity services |
| [person.md](person.md) | persons, contacts, addresses, bank accounts, user types; `PersonService`, `PersonUserTypeService`, person entity services |
| [iam-auth.md](iam-auth.md) | permissions, roles, overrides, data scopes, devices, mobile OTP login; `UserService`, `UserScopeService`, data scoping (`DataScope`, `ScopeResolver`, `ScopeCodeFiller`), `MyAccountService`, `AuthService`, RBAC helpers |
| [dashboard.md](dashboard.md) | dynamic dashboard: widget registry, `DashboardService` (every widget's definition), `DashboardPeriod` |
| [hr.md](hr.md) | employee history over time; `EmployeeJourneyService` |
| [vehicle.md](vehicle.md) | segment → sub segment → model → variant (per colour), status, accessories; `VehicleService`, vehicle entity services, `AccessoryService` |
| [pricing.md](pricing.md) | the price-list pipeline, rule / add-on / price entity services, the pricing JSON and `PricingQueryService` |
| [crm-enquiry-quotation.md](crm-enquiry-quotation.md) | leads, enquiries, quotations, test drives, campaigns; `EnquiryReferenceService` |
| [sales-booking.md](sales-booking.md) | the booking and its 10 sub-domain services (KYC, DMS, OTF, finance, insurance, RTO, exchange, delivery, refunds) |
| [accounts.md](accounts.md) | receipts and journal vouchers (`Bookingamount`) |
| [spares.md](spares.md) | part master, stock, orders, consumption, spare requests |
| [utils-legacy.md](../architecture/legacy-utils.md) | `KeywordValueService` + KeyValue writers, `SynonymService`, `IdentifierService`, the legacy settings stack, old core models |
| [api-v1-adapters.md](api-v1-adapters.md) | services kept for the frozen mobile API (`NotificationService`, `FirebaseService`, `DocService`, `EntityHistoryService`, `OtpNotificationService`) |
| [reference.md](../architecture/model-reference.md) | every model → table → writer → guide (generated from the code) |

## Module cards (read the card; open the guide only if you need more)

**IAM & auth** — [iam-auth.md](iam-auth.md) · rules `.ai/rules/modules/iam-rbac.md` · skill `xcelr8-rbac-debug`
- Admin login: Backpack (username + password); mobile: OTP → device-bound Sanctum token (broken until BUG-187 / D1).
- Permissions `{MOD}_{PROC}_{ACT}`, roles = designations (`xlr8_admin_designation`); per-user denials; SuperAdmin via a Gate `before` hook.
- Data scoping (DEC-071): `HasDataScope` + `xlr8_admin_user_scopes`; writers `UserService`, `UserScopeService`; `MyAccountService`.
- Security baseline (DEC-084): idle logout / screen lock (settings `security.*`), security headers.

**Org** — [org.md](org.md) · rules `.ai/rules/modules/org-person.md`
- Branches, locations, departments, divisions, verticals, designations, employees — all keyed by **code**.
- Reads: `OrgService` (cached); writes: the org entity services (DEC-050/052).

**Person** — [person.md](person.md) · rules `.ai/rules/modules/org-person.md` · spec `frs/person-system-frs.md`
- One row per human or company (`xlr8_admin_person`, key `person_code`); contacts / addresses / banking in fixed type slots (DEC-053).
- `PersonService::find($anything)`; open issue BUG-206 (`person_code` = PAN / Aadhaar).

**HR** — [hr.md](hr.md) · employee history over time (`xlr8_admin_employee_history`), written only by `EmployeeJourneyService`.

**Vehicle** — [vehicle.md](vehicle.md) · rules `.ai/rules/modules/vehicle-pricing.md`
- Segment → sub segment → model → variant; **one variant row = one OEM code = one colour** (DEC-048).
- `VehicleService` (dropdowns, stubs, Vehicle Info), `VehicleCompleteness` (the one completeness rule), `AccessoryService`.
- Local masters purged (DEC-051) until the fresh import.

**Pricing** — [pricing.md](pricing.md) · rules `.ai/rules/modules/vehicle-pricing.md` · skill `xcelr8-pricing` · workflow `../frs-and-workflows/workflows/pricing-process.md`
- 11-step Pricing Process → snapshots per permit × NV / OV × channel → `PricingQueryService::getPricing()` (contract v2).
- Masters with WEF versioning, automatic recalculation, sync stamp `pricing.last_updated_at` (DEC-083).

**CRM: leads, enquiries, quotations** — [crm-enquiry-quotation.md](crm-enquiry-quotation.md) · rules `.ai/rules/modules/sales.md` · skill `xcelr8-sales-process`
- Enquiry / quotation statuses, data-scoped (DEC-071); quotations on published prices via `QuotationPricingService` (DEC-082).

**Booking** — [sales-booking.md](sales-booking.md) · workflow `../frs-and-workflows/workflows/sales-lifecycle.md`
- `xlr8_booking_master` + 10 services in `App\Services\Sales\Booking` (Core, KYC, DMS, OTF, finance, insurance, RTO, exchange, delivery, refunds). Booking team owns it.

**Accounts** — [accounts.md](accounts.md) · receipts and journal vouchers, both rows of `xlr8_booking_amount` (`Bookingamount`, by `type`); no service yet.

**Spares** — [spares.md](spares.md) · legacy `xlr8_spare_*`, one screen (Spare requests, BUG-031 / 032 / 116); rebuild pending (D28).

**Dashboard** — [dashboard.md](dashboard.md) · `config/dashboard.php` widget registry + `DashboardService`; permission- and scope-aware (DEC-072).

**API v1 adapters** — [api-v1-adapters.md](api-v1-adapters.md) · services kept only for the frozen mobile API (DEC-004); new code uses the platform utilities. API docs: `../api/index.md`.

## How to read a guide
- **"Writer"** = the entity service that is the only way to create / change that table (DEC-050). Never `Model::create()`
  or `DB::table()->insert()` for those tables.
- **Signatures** are copied from the code; return shapes list the keys you get back.
- **Known bugs** are named inline with their `BUG-###` id (`docs/bugs/open.md`) so you don't build on
  broken code.
- Examples assume the facades / services are imported; run them against `xlrm_testing` (the local vehicle masters are
  purged until the fresh import, DEC-051).

## Keeping them true
When you change a model or service, update its guide (and `reference.md` for new models) in the **same change** —
see the rule in `.ai/rules`. A method that exists in code but not in a guide is a defect in the change.
