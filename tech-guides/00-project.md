# Xceler8 (XLRM) — project card

**What:** BMPL's dealership management system: enquiries → quotations (with approvals) → bookings (KYC, DMS, finance,
insurance, RTO, exchange, delivery, refunds, OTF) → accounts; vehicle master and pricing; org / HR / IAM; spares; shared
platform utilities; a Sanctum API for the mobile app.

**Two tracks (DEC-001):** Track A = this repo, stabilised for UAT (live app). Track B = the greenfield rebuild in
`D:\laragon\www\xceler8` (Laravel 13, Filament 5), paused after B0; this app's data migrates to it at switch-over.

**Stack:** Laravel 12, PHP 8.4, Backpack 7 (Tabler, no PRO), MySQL 8.4, Spatie permission (roles = designations) +
medialibrary, Sanctum, AG Grid 36, PhpSpreadsheet / Maatwebsite Excel, database queue + cache. Tests (PHPUnit) run on
the `xlrm_testing` copy, never on `xlrm`.

## Modules

| Module | Code | Guide | Rules (auto) |
|---|---|---|---|
| IAM / auth / scoping | IAM | `modules/iam-auth.md` | `.ai/rules/modules/iam-rbac.md` |
| Org (branches, designations, employees) | ORG | `modules/org.md`, `hr.md` | `modules/org-person.md` |
| Person (contacts, addresses, banking) | ORG | `modules/person.md` | `modules/org-person.md` |
| Vehicle master | VEH | `modules/vehicle.md` | `modules/vehicle-pricing.md` |
| Vehicle pricing | PRC | `modules/pricing.md` | `modules/vehicle-pricing.md` |
| CRM: leads, enquiries, quotations | SLS | `modules/crm-enquiry-quotation.md` | `modules/sales.md` |
| Booking and sub-domains | SLS | `modules/sales-booking.md` | `modules/sales.md` |
| Accounts | ACC | `modules/accounts.md` | — |
| Spares | SPR | `modules/spares.md` (rebuild pending, D28) | — |
| Dashboard | — | `modules/dashboard.md` | — |
| Platform utilities | UTL | `platform/README.md` | `modules/platform.md` |

## Laws that apply everywhere
1. **Layers:** controller (validate, permission, one service call) → service (`App\Services\{Module}\{Process}`) →
   model (`BaseModel`). Business failures are `App\Support\Result`.
2. **Entity writes only through the entity service** (`App\Support\Entity\EntityService`, DEC-050) — its `fields()` is
   the only field rule set.
3. **Business keys are codes** (`person_code`, `branch_code`, …), not integer ids.
4. **Lookups:** `KeywordValueService` / `OrgService` (cached), never direct table queries.
5. **Permissions** `{MOD}_{PROC}_{ACT}` (e.g. `SLS_BKNG_KYC`); inline `backpack_user()->can()` first in each action.
6. **Never guess a business rule or override a locked spec** — stop and ask; decisions go to the decision log first.
7. **Dates** in IST (DEC-046), shown with `site_date()`; **money** `DECIMAL(15,2)`; labels from `resources/lang/en/{module}.php`.
8. **API** envelope `{http_status, success, code, message, timestamp, data | errors}` for every response (DEC-085).

## Status
Live status, next steps and open questions: `.ai/state/handoff.md`. The go-live to-do: `docs/todo.md`.
