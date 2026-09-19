---
description: Vehicle hierarchy, pricing pipeline, status machine, quotation rules. Load for any pricing, vehicle master, or quotation work.
paths:
  - app/Services/Vehicle/**
  - app/Models/Vehicle/**
  - app/Http/Controllers/Admin/Vehicle/**
  - app/Http/Controllers/Api/V1/Vehicle/**
---

# XCELR8 Vehicle & Pricing Rules

## 1. Vehicle Hierarchy (Project-Life Constant)

```
Segment → SubSegment → Model → Variant (one row per colour)
```

- `vehicle_model.code` = OEM Model UPPERCASED, **WITHOUT** colour (never contains last 2 colour chars)
- `vehicle_variant.code` = **FULL** OEM Code **WITH** colour (always contains them)
- `color_code` = last 2 chars of OEM Code (used for `color`, `color_code`, default colour name)
- Colour is **NOT** a standalone entity / separate table. Colour lives on the variant row.
- Every child stores ancestor codes: `segment_code`, `sub_segment_code`, `model_code`
- Relations are **code-based**, no SQL FKs

Example: stem `ASEP23QWSC1` in five colours = five variant rows: `…WD`, `…GR`, `…BG`, `…SB`, `…BL`

**DEPRECATION: Do NOT look for a `colors` / `vehicle_color_map` table — they do not exist in active schema.**

---

## 2. Vehicle Status Machine (Four Values Only)

| Status | Meaning | `is_active` |
|---|---|---|
| `INCOMPLETE` | Missing required fields. Fresh detect stubs start here. Cannot be sold. | false |
| `ACTIVE` | Complete **and** on sale. Only status where `is_active = true`. | **true** |
| `INACTIVE` | Operator took it off sale. May still be complete. | false |
| `DISCONTINUED` | No longer manufactured. | false |

Rules:
- Fresh Price List detect → always `INCOMPLETE`. **Never `INACTIVE` on create.**
- `ACTIVE` requires completeness. Do NOT set ACTIVE on incomplete vehicles.
- `VehicleService::isComplete()` is the SSOT completeness check.

---

## 3. Detect from Price List Sheets — Rules

Detect new vehicles from **Price List sheets ONLY**. PV Vehicle / CV Vehicle sheets are retired.

Sheet title token after "Price List" maps to Segment:
- "Price List PV" → PV
- "Price List TZU" → LMM
- "Price List TAXI" → PV

**Detect flow:**
1. Lookup Variant by full OEM Code. If found → reuse ancestors. **Do NOT rewrite specs.**
2. If missing → create: Segment (from sheet title token), SubSegment (same code/name temporarily OK), Model (`code` = stem without colour, `name` / `oem_name` from OEM Model), Variant (full OEM Code, ancestor codes, colour from last 2 chars)
3. Store only: OEM Model, OEM Variant, full OEM Code, colour, segment (guessed from sheet title)
4. Do **NOT** invent: display_name, fuel, permit, drivetrain, seats, CC
5. Status = `INCOMPLETE`. Never `INACTIVE`.

---

## 4. Completeness Gate (Vehicle Info Import Only, NOT Detect)

**Always required before ACTIVE:**
Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST%, Permit, Taxi Price, Custom Model, Custom Variant, Display Name, Colour Name.

**Conditional (insurance-aligned):**
- Private + ICE **or** Passenger 4W + ICE → CC required
- Private + EV **or** Passenger 4W + EV → Motor required
- Goods → GVW required
- Passenger 3W → none of CC / Motor / GVW required

SSOT: `VehicleService::missingFields()` and `VehicleService::isComplete()`. Prefer `VehicleService` over `VehicleMasterService` for full gate.

---

## 5. Global Excel Header Match Rule (Project-Wide)

| Header in Sheet | Match Against |
|---|---|
| "Model" or "Variant" | `vehicle_model.name` and `vehicle_variant.display_name` |
| "OEM Model" and/or "OEM Variant" | `vehicle_model.name`/`oem_name` and `vehicle_variant.oem_name` |
| "Model Code" / "OEM Code" / "M Code" / "Vehicle Code" / "OEM Code" | `vehicle_variant.code` (FULL code WITH colour) |

**Never append colour again** when matching by full OEM Code — it already contains it.

Always run `SynonymService` resolution BEFORE matching Branch, Fuel, Segment, Permit, etc.

---

## 6. Rule / Scope Matching Rules (Project-Wide)

- `ANY` / blank / `ALL` / `*` = all descendants at that level
- Comma-separated = union match
- More specific row **replaces the entire previous match** (not field-merge)
- Trim + case-insensitive matching
- Resolve typos through `SynonymService` BEFORE matching

---

## 7. Pricing Pipeline (Gated Workflow)

```
Upload Price Lists + pick sheets + WEF
  → Detect new OEM codes (INCOMPLETE stubs)
  → Vehicle Info import (completeness + status)
  → Import prices (history by WEF)
  → Add-ons & Discounts
  → Insurance & RTO (keep or import)
  → Impact summary
  → Optional Hold → Calculate & Publish snapshots
  → Reopen holds
  → Quotation calls getPricing(oemCode) → receives fixed-key JSON
```

**Critical rules:**
- One active `ImportSession` at a time. Discard or complete before starting another.
- Stages are gated. Incomplete vehicles are listed in impact summary and **SKIPPED** by Calculate.
- Do NOT calculate until: addons written > 0 AND (rules kept OR rules written > 0).
- Do NOT load entire pricing workbook into memory. One sheet, cap columns (~AZ), chunk rows.
- Do NOT seed zero-value addon/rule rows under ANY scope (they override ALL on re-import).

**WEF versioning:**
- Same WEF re-import → updates the live row
- Different WEF → expires previous (`is_active=0`, `expired_on=WEF`) then inserts new row
- Group import expires **only that group** (RSA does not expire Shield). No TRUNCATE of live history.

**Queue requirement:**
```bash
php artisan queue:work --timeout=1800 --tries=1
```

---

## 8. Holds

At Calculate or from Hold screen: freeze one list (PV, CV, LMM, CSD, …) or ALL.
- Frozen lists must not accept new quotes or bookings
- Reopen after snapshots are published
- Taxi sits under PV unless TAXI is held separately

---

## 9. Pricing JSON Contract (`PricingJsonContract`)

`getPricing(oemCode)` returns fixed-key JSON. Rules:
- **Never change keys** — consumers depend on fixed structure
- Unused keys present with `0` or `null`
- Consumers ignore zeros

Key categories assembled:
- Ex-showroom (colour premium = different price on different OEM Code)
- Dealer incidental, FastTag, TRC, RTO tape, COD
- RSA, Shield, accessory packs
- Insurance (multi-company, multi-plan, multi-addon, multi-permit)
- RTO / tax (permit-aware)
- TCS
- Minus schemes: cash, exchange, corporate, RSA/Shield/accessory discounts

---

## 10. Quotation Rules (FRS v1.0 July 2026)

- Entire form locked until valid Enquiry Number resolves via `getEnq()`
- Reject closed / converted / cancelled enquiries
- Vehicle finalisation: Variant → Colour → `getPricing(oemCode)`
- UI driven exclusively by pricing JSON keys. Missing key = hidden / N/A
- Maxicare, PPF, Ceramic Coating are **accessories**, NOT separate grid rows
- Discount types: `I` (invoice), `C` (ordinary CN), `C1` (special CN e.g. Exchange), `C2` (special CN e.g. Charger Swapping), `B` (user chooses I or C)
- **Critical gate:** if any ordinary C > 0 → `CreditNoteDiscount ≥ OEM Scheme` (C1/C2 excluded from both sides)
- TCS on `FinvoiceAmount` = Subtotal − InvoicedDiscount (NOT raw Subtotal)
- Server MUST re-validate formulas on Save. Store full snapshot for audit.

**Out of scope v1:** Direct Booking from Quote, multi-vehicle quotes, customer self-service, automated expiry beyond basic save.

---

## 11. Open Gaps (Machine Spec §12 — Do Not Paper Over)

- GAP-01: Insurance addon-rate importer still thin
- GAP-02: `*_history` satellites not written on expire
- GAP-03: No insurance company master (companies are strings on defaults/rules)
- GAP-04: Invoice-base IDV formula TBD by business
- GAP-05: Calculate & Publish not signed off on full PV+CV+CSD set
- GAP-06: Accessory keys in published JSON must use existing `AccessoryService`
- GAP-09: CSD sheet heaviest — keep chunking
- GAP-10: Shield pack vs `variant.shield_pack` needs live test
- GAP-11: Dual-permit taxi snapshots need engine proof

**Do NOT attempt to resolve gaps without explicit user instruction.**
