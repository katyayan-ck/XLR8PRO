# Xceler8 core rules (always on)

Keep this file short. Domain detail lives in **conditional rules** (auto when matching files are open) and **workflows** (only when you type `/name.md`).

**Project:** BMPL / Xceler8 DMS · Laravel 12 + Backpack · `katyayan-ck/XLR8`
**Deep catalog:** `docs/CLINE_SERVICES.md` — read it only when the task needs method-level contracts.

## How Cline should load context

| Need | Mechanism | Cost |
|---|---|---|
| Always-true law | this file | every prompt |
| Editing Person/Vehicle/Pricing files | matching `.clinerules/*.md` with `paths:` | only then |
| Multi-step task (import, complete vehicle, publish price) | `/person-workflow.md` etc. | that message only |
| Event guardrail (block bad writes) | Hooks — not used for domain docs | N/A |

If the user names a domain and no matching files are open, **read the matching workflow file** from `.clinerules/workflows/` before coding. Do not load every workflow.

Slash commands (workspace):

- `/person-workflow.md`
- `/vehicle-workflow.md`
- `/pricing-workflow.md`
- `/booking-workflow.md`
- `/quotation-workflow.md`
- `/docs-workflow.md`
- `/comm-history-workflow.md`
- `/model-traits-workflow.md`
- `/new-slice.md`

## Work rules

1. Never invent a parallel Person / Org / Vehicle / Pricing / Accessory / Auth service.
2. Open the real class. Match namespace, method names, return shape.
3. Full files, not snippets, unless the user asks for a patch.
4. After a slice: offer changelog + laradocs. Use `/new-slice.md`.
5. Do not grow `app/Helpers/XpricingHelper.php`, `QuotesHelper.php`, `VehicleHelper.php`, `XCommonHelper.php`.

## Delivery (this user)

- New schema = **raw SQL** until asked for a migration.
- Every table: `created_at/by`, `updated_at/by`, `deleted_at/by`. Soft delete + Spatie media.
- Models extend `App\Models\BaseModel` (already has SoftDeletes, media, audit actor, CrudTrait). Pricing models `array_merge` casts with BaseModel.
- Services SSOT. Controllers thin.
- Cache org/keyword masters 3600s.
- Prefixes: `xlr8_admin_*`, `xlr8_iam_*`, `xlr8_vehicle_*`, `xlr8_vehicle_pricing_*`.

## Source of truth

Pricing: `docs/PRICING_CLINE_CONTEXT.md` + Machine Spec v3.1 > FRS v3.1 > code > chat.
Approvals: Final Behavioural Spec. **No schema/code** unless asked.

## Never break

- People / phone / PAN / bank → `PersonService`
- Org lists / upline → `OrgService` + `OrgScopeService`
- Enums → `KeywordValueService`. Typos → `SynonymService` first
- Can-user-X → `RBACService`. Pricing admin: `manage_pricing`
- Vehicle create/complete → `VehicleService`
- Accessories → `AccessoryService`
- On-road JSON → `PricingEngineService` + `PricingJsonContract`
- Excel headers → `SheetHeaderService` field_code
- OTP → `AuthService` (device limit **5**)
- Push → `NotificationService` → `FirebaseService`
- Files → `DocService` + Spatie collections on BaseModel
- Booking status → `BookingStateService`
- Fresh Price List stubs = **INCOMPLETE**, never INACTIVE
- No calc/publish for incomplete vehicles
- No whole-workbook `toArray()` on fat Excel
- No zero-value ANY rows on addon/rule export

## Router (then open only that workflow)

| Topic | Slash |
|---|---|
| person, contact, PAN, user type | `/person-workflow.md` |
| segment/model/variant, Vehicle Info | `/vehicle-workflow.md` |
| price list, addons, insurance, RTO, snapshot | `/pricing-workflow.md` |
| booking status | `/booking-workflow.md` |
| quotation / on-road consume | `/quotation-workflow.md` |
| documents, media | `/docs-workflow.md` |
| comm history, threads | `/comm-history-workflow.md` |
| BaseModel, traits, User, scopes | `/model-traits-workflow.md` |
| done a feature | `/new-slice.md` |
