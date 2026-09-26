---
description: Critical gotchas, edge cases, and traps specific to XCELR8. Load before debugging or touching any critical path.
---

# XCELR8 Known Pitfalls & Critical Gotchas

## 🔴 CRITICAL — Will Break Production

### P-01: Vehicle Variant Code — Never Strip Colour from OEM Code on Lookup
OEM Code = full code WITH colour suffix (last 2 chars). When matching by "Model Code" / "OEM Code" header in Excel → match `vehicle_variant.code` (full). Never re-append colour or strip it.

### P-02: Price List Creates as INCOMPLETE, Never INACTIVE
Fresh Price List detect stubs → `INCOMPLETE`. Never create as `INACTIVE`. `INACTIVE` means an operator intentionally took a vehicle off sale after it was once ACTIVE.

### P-03: Do NOT Zero-Seed ANY-Scope Addon/Rule Rows
Exporting any-scope addons/rules then re-importing with zero values will override ALL variants at that scope level. Zero-value rows under `ANY` scope = destructive wipe. Never seed them.

### P-04: One Active ImportSession at a Time
Starting a new pricing import while one is active will corrupt pipeline state. Always discard or complete the current session before starting another.

### P-05: Do NOT Load Entire Pricing Workbook into Memory
PhpSpreadsheet loads one sheet at a time. Cap columns (~AZ). Chunk rows. CSD sheet is the heaviest. Loading full workbook = memory exhaustion on server.

### P-06: Quotation TCS Is on FinvoiceAmount, Not Raw Subtotal
TCS = 1% of `FinvoiceAmount` (Subtotal − InvoicedDiscount). NOT on raw Subtotal. Billing threshold ≥ ₹10,00,000 or financier-invoice. Getting this wrong = incorrect statutory deduction.

### P-07: Quotation Credit Note Gate
If any ordinary `C`-type discount > 0 → `CreditNoteDiscount ≥ OEM Scheme`. C1 and C2 types are excluded from both sides. Gate must be enforced server-side on Save.

### P-08: Never bypass OrgService Cache with Raw Queries
`OrgService` cache TTL = 3600s. Direct queries to Branch/Location/Dept/Division tables bypass the cache and cause inconsistency and N+1 load. Always use `OrgService`.

### P-09: person_code Links, Not person.id
Child tables link to `Person` via `person_code` (natural key), NOT `person.id` (integer PK). Building an integer FK from, say, `Employee` back to `person.id` breaks the identity model.

### P-10: WEF Re-import Versioning — Same vs Different
Same WEF → update live row in-place. Different WEF → expire previous row (`is_active=0`, `expired_on=WEF`) then insert new. NEVER truncate live history.

---

## 🟡 IMPORTANT — Silent Failures

### P-11: Group Import Expires Only That Group
RSA import does NOT expire Shield/Insurance/RTO rules. Each group expires independently. Never TRUNCATE or mass-expire all rules when re-importing one group.

### P-12: Synonym Resolution BEFORE Matching
Any importer matching Branch, Fuel, Segment, Permit values MUST run `SynonymService` resolution first. Skipping it causes "not found" errors on valid data that just uses a nickname or has a typo.

### P-13: Vehicle Info Completeness Gate — Only on Vehicle Info Import
The completeness gate (insurance-aligned conditional fields) is enforced ONLY during Vehicle Info import, NOT during Price List detect. Detect just creates an INCOMPLETE stub.

### P-14: KeywordValueService Only — No Direct KeyValue Queries
Direct `KeyValue::where('type', ...)` queries bypass the 3600s cache and the `KeywordHelper` abstraction is deprecated. All keyword/lookup values MUST go through `KeywordValueService`.

### P-15: Jobs Must Use withoutDataScope()
If a queued job uses a model with `ScopedQuery`, it will be scoped to the queue worker's (no) user context and return empty results. Always use `Model::withoutDataScope()` in jobs.

### P-16: SuperAdmin vs bypass_data_scoping
`isSuperAdmin()` bypasses RBAC. `bypassesDataScoping()` bypasses data scope filters. These are DIFFERENT checks. A user can bypass data scoping without being a SuperAdmin (e.g. pricing operator).

### P-17: Header Labels in Importers Are Dynamic
Never hardcode Excel column header strings in importers. Use `SheetHeaderService` with `field_code` + `SynonymService`. Headers change between OEM versions and dealer configurations.

### P-18: Vehicle Model Code Is STEM Only
`vehicle_model.code` = OEM Model uppercase WITHOUT the last 2 colour chars. If you include colour chars in model code, you'll create duplicate model records or fail lookups.

### P-19: `is_active = true` ONLY When Status = ACTIVE
`is_active` is not a status field — it's a derived boolean. Never set `is_active = true` on INCOMPLETE, INACTIVE, or DISCONTINUED vehicles. Only ACTIVE status → `is_active = true`.

### P-20: Calculate Skips Incomplete Vehicles
The Calculate step silently skips incomplete vehicles (they appear in impact summary but are not priced). Do not try to calculate for an incomplete vehicle — make it complete first via Vehicle Info import.

---

## 🟢 ARCHITECTURAL — Avoid Design Mistakes

### P-21: XCELR8 Is NOT Sequential Approval
The Approval Engine is a **parallel negotiation**, not a sequential escalation workflow. Approvers never Reject — they counter. Only the requester can Accept/Reject. Highest-level counter wins. Do not implement as a simple approve/reject chain.

### P-22: Colour Is NOT a Separate Entity
Colour lives on `vehicle_variant` row as `color`, `color_code`, and colour name. There is NO `colors` table, NO `vehicle_color_map` table in the active schema. Do not create one.

### P-23: Quotation Does NOT Create Booking in v1
The Quotation FRS v1.0 does not include direct Booking creation from Quotation. They are separate modules. Do not wire them together without explicit instruction.

### P-24: xlrk Legacy Database — Not SSOT
The `xlrk` database (legacy CRM/booking) is a legacy data source. Never treat its `QuotationCrudController` calculations as SSOT for pricing. The `xlrm` database and `PricingEngineService` are SSOT.

### P-25: Shared Services Must Not Become Dumping Grounds
`App\Core` / `App\Shared` is for genuinely shared infrastructure (Notification, File, Audit, PDF). If code actually belongs to one business module, it should live there, not in Shared. Ask: "Is this truly cross-cutting?"

---

## Legacy Code Watch List

- `xlrk` QuotationCrudController pricing calculations → replaced by `PricingEngineService`
- Old `KeywordHelper` → replaced by `KeywordValueService`
- Old `PV Vehicle` / `CV Vehicle` sheets for vehicle detect → replaced by Price List sheet detect
- Old `draft→review→explicit-publish` pricing controller surface → replaced by gated ImportSession pipeline
- Old `is_vehicle_master_complete` / `is_publishable` flags → replaced by four-status model
- Old sequential approval with Reject button → replaced by parallel counter-offer engine
- Old `colors` / `vehicle_color_map` table → colour is on variant row
- Old `xlr8_pricing_*` prefix → replaced by `xlr8_vehicle_pricing_*`
