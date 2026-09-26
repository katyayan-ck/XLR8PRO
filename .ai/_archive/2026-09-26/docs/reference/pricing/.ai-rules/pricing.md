---
paths:
  - "app/Services/Vehicle/Pricing/**"
  - "app/Models/Vehicle/Pricing/**"
  - "app/Jobs/Vehicle/Pricing/**"
  - "config/pricing.php"
  - "resources/views/admin/pricing/**"
---

# Pricing (loads when pricing files are in context)

Dense law: `docs/PRICING_CLINE_CONTEXT.md`. Locked spec: Machine Spec v3.1. Permission: `manage_pricing`.
SSOT on-road: `App\Services\Vehicle\Pricing\PricingEngineService`.
Headers: `SheetHeaderService` field_code registry. Never hardcode Excel labels.

- One active ImportSession.
- Same WEF updates live row; new WEF expires previous then inserts.
- Group import expires only that group (RSA ≠ Shield).
- Skip incomplete + no-price. No publish if Hold or incomplete.
- PhpSpreadsheet: one sheet, cap AZ, chunk 50, disconnect + GC.
- RTO formula text → `tax_basis`. Numeric rate → `tax_slab`.
- JSON keys fixed (`PricingJsonContract`). Unused keys 0/null.
- Do not grow `app/Helpers/XpricingHelper.php`.
- July leftover paths (`app/Services/Vehicle/PricingEngineService.php` without `\Pricing\`) — extend the namespaced class.
- Procedure: `/pricing-workflow.md`.
