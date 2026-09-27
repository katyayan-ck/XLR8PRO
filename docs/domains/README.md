# Domain developer guides — models and services

How to use every business model and service in Track A: what each public method takes and returns, the rules behind
it, worked examples, gotchas and how to test. Companion to the platform utilities guides in
[`docs/utilities/`](../utilities/README.md) (Settings, Notify, Chat, Docs, Tasks, Tickets, Approvals, Templates,
Email / SMS / WhatsApp / Telephony, UI kit).

**Start with [core.md](core.md)** — BaseModel, User, traits, entity services (the only write path), `Result`, helpers.

| Guide | Covers |
|---|---|
| [core.md](core.md) | `BaseModel`, `User`, `HasColumnTransformations`, `HasTreeStructure`, `HasDataScope`, `EntityService` + `Field`, `Result`, `site_date()` / `DateFormatService` |
| [org.md](org.md) | branches, locations, departments, divisions, verticals, designations (= roles), employees; `OrgService`, `OrgScopeService`, org entity services |
| [person.md](person.md) | persons, contacts, addresses, bank accounts, user types; `PersonService`, `PersonUserTypeService`, person entity services |
| [iam-auth.md](iam-auth.md) | permissions, roles, overrides, data scopes, devices, mobile OTP login; `UserService`, `UserScopeService`, data scoping (`DataScope`, `ScopeResolver`, `ScopeCodeFiller`), `AuthService`, RBAC helpers |
| [hr.md](hr.md) | employee history over time; `EmployeeJourneyService` |
| [vehicle.md](vehicle.md) | segment → sub segment → model → variant (per colour), status, accessories; `VehicleService`, vehicle entity services, `AccessoryService` |
| [pricing.md](pricing.md) | the price-list pipeline, rule / add-on / price entity services, the pricing JSON and `PricingEngineService` |
| [crm-enquiry-quotation.md](crm-enquiry-quotation.md) | leads, enquiries, quotations, test drives, campaigns; `EnquiryReferenceService` |
| [sales-booking.md](sales-booking.md) | the booking and its 10 sub-domain services (KYC, DMS, OTF, finance, insurance, RTO, exchange, delivery, refunds) |
| [accounts.md](accounts.md) | receipts and journal vouchers (`Bookingamount`) |
| [spares.md](spares.md) | part master, stock, orders, consumption, spare requests |
| [utils-legacy.md](utils-legacy.md) | `KeywordValueService` + KeyValue writers, `SynonymService`, `IdentifierService`, the legacy settings stack, old core models |
| [api-v1-adapters.md](api-v1-adapters.md) | services kept for the frozen mobile API (`NotificationService`, `FirebaseService`, `DocService`, `EntityHistoryService`, `OtpNotificationService`) |
| [reference.md](reference.md) | every model → table → writer → guide (generated from the code) |

## How to read a guide
- **"Writer"** = the entity service that is the only way to create / change that table (DEC-050). Never `Model::create()`
  or `DB::table()->insert()` for those tables.
- **Signatures** are copied from the code; return shapes list the keys you get back.
- **Known bugs** are named inline with their `BUG-###` id (`docs/refactor/known-bugs-report.md`) so you don't build on
  broken code.
- Examples assume the facades / services are imported; run them against `xlrm_testing` (the local vehicle masters are
  purged until the fresh import, DEC-051).

## Keeping them true
When you change a model or service, update its guide (and `reference.md` for new models) in the **same change** —
see the rule in `.ai/rules`. A method that exists in code but not in a guide is a defect in the change.
