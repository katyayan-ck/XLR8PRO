# Pricing workflow

Invoke: `/pricing-workflow.md`

Use for ImportSession stages, Price List import, addons, insurance/RTO rules, calculate & publish.

## Read first (required)

1. `docs/PRICING_CLINE_CONTEXT.md` — dense law + screens + files + pending
2. Machine Spec v3.1 if the context file is not enough
3. Open the real class you will edit (`app/Services/Vehicle/Pricing/…`)
4. `docs/CLINE_SERVICES.md` §7 for method-level catalog

Permission: `manage_pricing`. One active session.

## Pipeline (do not skip gates)

1. Start / resume / discard session.
2. Detect vehicles from selected Price List sheets + WEF (`PriceListVehicleDetector`).
3. Export / import Vehicle Info (`VehicleInfoExportService` / `VehicleInfoImportService`).
4. Import prices (`PriceListPricingImporter`) — skip incomplete + no-price.
5. Import addons (`AddonDiscountImportService`) — expire only that group. written>0 required.
6. Keep or import Insurance / RTO (`RulesWorkbookService`).
7. Impact summary. Optional Hold.
8. Calculate & Publish (`PricingEngineService::calculateAndPublish`). Skip Hold + incomplete.

Jobs: DetectPricingWorkbookJob, ImportPriceListsJob, CalculatePricingSessionJob, RecalculateVehiclePricingJob.
Worker: `php artisan queue:work --timeout=1800 --tries=1`.

## Excel

- Headers → `field_code` via `SheetHeaderService`. Add labels to registry, not if-blocks.
- One sheet load, cap AZ, chunk 50, disconnect + GC.
- Same WEF: update live row. New WEF: expire previous then insert.
- Export addons/rules = active DB rows only. Never seed zero ANY.
- RTO Tax Factor text → `tax_basis`. Numeric → `tax_slab`.
- Re-import originals after a bad zero-seeded export.

## Quote payload

```
PricingEngineService::getPricingPayload($oemCode, [
  'channel' => 'normal', // or csd
  'vin_type' => 'nv',    // nv|ov
  'permit' => 'Private',
]);
```

Fixed keys: `PricingJsonContract`.

## Do not

- Grow `XpricingHelper`.
- Register a second `PricingApiController` class.
- Calculate before addons written > 0 AND (rules kept OR rules written > 0).
- Mark detect stubs INACTIVE.
- Treat colour as a standalone table.
