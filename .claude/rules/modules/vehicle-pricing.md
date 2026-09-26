---
description: Vehicle hierarchy, status machine, pricing pipeline and its pitfalls. Load for vehicle master or pricing work.
paths:
  - app/Services/Vehicle/**
  - app/Models/Vehicle/**
  - app/Jobs/Vehicle/**
  - app/Http/Controllers/Admin/Vehicle/**
  - app/Http/Controllers/Admin/Pricing/**
  - routes/backpack/pricing.php
---

# Vehicle master & pricing (spec: Machine Spec v3.1.1, locked 2026-08-31)

## Hierarchy
Segment → SubSegment → Model → Variant (one row per colour). Code-based relations; children store ancestor codes.
- `vehicle_model.code` = OEM model stem, uppercase, **without** the 2-char colour suffix.
- `vehicle_variant.code` = **full** OEM code **with** colour; `color_code` = last 2 chars. Colour lives on the
  variant row (no colours/colour-map table). Never strip or re-append colour when matching.

## Status (four values)
`INCOMPLETE` (fresh detect stub, not sellable) · `ACTIVE` (complete + on sale; the only `is_active=true`) ·
`INACTIVE` (operator took off sale) · `DISCONTINUED`. Detect never creates `INACTIVE`.
Completeness SSOT: `VehicleService::missingFields()` / `isComplete()`; the gate runs only on Vehicle Info import.

## Pipeline (gated, one active ImportSession at a time)
Upload price lists + WEF → detect (INCOMPLETE stubs) → Vehicle Info import → prices (WEF history) →
add-ons & discounts → insurance & RTO (keep or import) → impact summary → optional hold → Calculate & Publish
→ reopen holds → consumers call `PricingEngineService::getPricing(oemCode)` (fixed-key `PricingJsonContract`).

## Pitfalls (each has broken production before)
- Never load a whole workbook: one sheet, cap columns (~AZ), chunk rows (CSD is heaviest).
- WEF: same WEF → update live row; different WEF → expire previous (`is_active=0`, `expired_on=WEF`) then insert.
  Group imports expire only their group (RSA ≠ Shield). Never truncate live history.
- Never seed zero-value add-on/rule rows at ANY scope (they override everything on re-import).
- Scope matching: `ANY`/blank/`ALL`/`*` = all; comma = union; more specific row replaces the whole match;
  trim + case-insensitive; synonyms first.
- Calculate skips incomplete vehicles; requires add-ons written > 0 and rules kept or written.
- Pricing JSON keys never change (unused keys present with 0/null).
- Pricing admin requires `manage_pricing` / `PRC_*` permissions; `PricingResetService` is destructive — local only.
- Open spec gaps GAP-01…11 (insurance addon importer, history satellites, insurer master, IDV formula…) —
  do not paper over them without instruction.
