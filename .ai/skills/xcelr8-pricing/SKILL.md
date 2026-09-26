---
name: xcelr8-pricing
description: "Use for vehicle pricing work: pricing pipeline, ImportSession, price lists, WEF imports, add-ons, discount rules, RTO/insurance rules, Calculate & Publish, getPricing()/PricingEngineService, PricingJsonContract, xlr8_vehicle_pricing_* tables."
---

> Ported 26-09-2026 from `.ai/_archive/2026-09-26/.ai/skills/xcelr8-pricing` (DEC-031). Current facts in
> `.ai/rules/**` win over anything below that conflicts (e.g. dead code removed on 26-09-2026,
> roles = designations, tests on `xlrm_testing`, migrations not SQL-first).

# Skill: XCELR8 Pricing Pipeline

**When to activate:** Any task involving: vehicle pricing, ImportSession, price list upload, WEF import, add-ons, discount rules, RTO, insurance import, Calculate, Publish, getPricing, PricingEngineService, quotation pricing, `xlr8_vehicle_pricing_*` tables.

**Keyword triggers:** pricing, price list, ImportSession, WEF, ex-showroom, on-road price, addon, discount rule, RTO, insurance import, Calculate, Publish, getPricing, PricingJsonContract, pricing pipeline, vehicle pricing machine.

---

## Context

XCELR8 uses a gated multi-stage pricing pipeline. The canonical spec is **Xceler8 Vehicle Pricing Machine Spec v3.1.1 (locked 2026-08-31)**. All code must conform to that spec. Do NOT reference `xlrk` or old QuotationCrudController calculations.

Load `.ai/rules/vehicle-pricing.md` before writing any code.

---

## Investigation Protocol

When investigating a pricing issue:

1. **Identify the session state**
   - Check active `ImportSession` — is there one? What stage is it at?
   - Use MCP: `SELECT * FROM xlr8_vehicle_pricing_import_sessions WHERE is_active = 1 LIMIT 5`

2. **Check vehicle status**
   - Is the vehicle ACTIVE (completeness gate passed)?
   - `SELECT code, status, is_active FROM xlr8_vehicle_variant WHERE code = '{oemCode}'`

3. **Inspect the pricing stack**
   - Profile → Pricing row → Addons → Discount Rules → Insurance → RTO
   - Check `is_active = 1` and `expired_on IS NULL` on all rule rows

4. **Inspect getPricing output**
   - Call `PricingEngineService::getPricing($oemCode)` via Tinker
   - Verify JSON keys match `PricingJsonContract`

5. **Check scope matching**
   - Are rules using `ANY` scope? More specific = wins.
   - Check `SynonymService` resolution for segment/branch/permit

6. **Queue worker**
   - Is `queue:work --timeout=1800 --tries=1` running?
   - Check failed_jobs table for pricing job failures

---

## Implementation Protocol

When implementing pricing features:

### Detect new vehicles
```php
// Always from Price List sheets, never PV/CV Vehicle sheets
// New stubs → INCOMPLETE, never INACTIVE
// Store only: OEM Model, OEM Variant, full OEM Code, colour, segment from sheet title
// Do NOT invent: fuel, display_name, drivetrain, seats
```

### WEF import
```php
// Same WEF → update live row
// Different WEF → expire previous (is_active=0, expired_on=WEF), insert new
// Never TRUNCATE live history
// Group import only expires that group
```

### Excel loading
```php
// One sheet at a time — never load full workbook
// Cap columns ~AZ
// Chunk rows (100-500 depending on columns)
// Disconnect and GC after each sheet
// SynonymService resolution BEFORE matching
```

### Never seed zero-value addon/rule rows
```php
// ANY-scoped row with zero value overrides ALL variants
// Validate: skip rows where all monetary values are 0
```

### Quotation pricing
```php
// Call PricingEngineService::getPricing($oemCode)
// UI driven by JSON keys only (missing key = hidden)
// Maxicare, PPF, Ceramic Coating = accessories, NOT separate grid rows
// Re-validate formulas server-side on Save
// Store full snapshot for audit
```

---

## Key Tables

```sql
xlr8_vehicle_pricing_import_sessions   -- Pipeline sessions (one active at a time)
xlr8_vehicle_pricing_profiles          -- Price list profiles
xlr8_vehicle_pricing_prices            -- Ex-showroom prices (WEF versioned)
xlr8_vehicle_pricing_addons            -- Add-on items (RSA, Shield, accessories)
xlr8_vehicle_pricing_addon_rules       -- Rules for addon scope matching
xlr8_vehicle_pricing_discount_rules    -- Discount rule rows
xlr8_vehicle_pricing_insurance_rules   -- Insurance plan rules
xlr8_vehicle_pricing_rto_rules         -- RTO charge rules
xlr8_vehicle_pricing_snapshots         -- Published price snapshots
```

---

## Services

```
App\Services\Vehicle\Pricing\ImportSessionService
App\Services\Vehicle\Pricing\PriceListDetectService
App\Services\Vehicle\Pricing\VehicleInfoImportService
App\Services\Vehicle\Pricing\PricingImportService
App\Services\Vehicle\Pricing\AddonImportService
App\Services\Vehicle\Pricing\RuleImportService
App\Services\Vehicle\Pricing\InsuranceImportService
App\Services\Vehicle\Pricing\RtoImportService
App\Services\Vehicle\Pricing\PricingEngineService    ← SSOT for getPricing()
App\Services\Vehicle\Pricing\PricingJsonContract     ← Fixed key structure
```

---

## Open Gaps (Do NOT paper over without explicit instruction)

- GAP-01: Insurance addon-rate importer thin
- GAP-05: Calculate & Publish not signed off on full PV+CV+CSD
- GAP-06: Accessory keys in JSON must use AccessoryService
- GAP-11: Dual-permit taxi snapshots need engine proof
