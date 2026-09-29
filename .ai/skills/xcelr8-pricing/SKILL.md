---
name: xcelr8-pricing
description: "Use for vehicle pricing work: the 11-step Pricing Process (PricingSessionService, detect, Vehicle Info, price / add-on / insurance / RTO workbooks, impact, holds, Calculate & Publish), snapshots, getPricing / PricingQueryService / PricingContract v2, Price List screens, pricing masters, automatic recalculation, the sync stamp, quotation pricing, xlr8_vehicle_pricing_* tables."
---

# Skill: vehicle pricing (current architecture, DEC-073 … DEC-083)

**Read first:** `tech-guides/frs-and-workflows/workflows/pricing-process.md` (1 page) → the section you need in
`tech-guides/modules/pricing.md` → the spec section in `tech-guides/frs-and-workflows/frs/pricing-machine-spec-v3.1.md`
(locked v3.1.1; §3.3 amended by DEC-073). Rules (auto-loaded): `.ai/rules/modules/vehicle-pricing.md`.

## Map (`App\Services\Vehicle\Pricing\…`)

| Area | Classes |
|---|---|
| Process session | `Session\PricingSessionService` (gate / start / advance / discard / complete), `PricingStage`, `PricingChangeRecorder` (exact Discard), `PricingIssueStore`, `PricingImpactService` |
| Step imports | `Import\PriceListDetectService` (2), `VehicleInfoWorkbookService` (3), `PriceListImportService` (4), `AddonDiscountWorkbookService` (5), `InsuranceWorkbookService` / `RtoWorkbookService` (6), `PricingWorkbookReader` (streaming reader for all) |
| Holds | `PricingHoldService` (lists ALL, PV, CV, BEV, LMM, LMM_TZU, CSD, TAXI) |
| Engine | `Engine\SnapshotBuilder`, `SnapshotPublisher`, `PricingCalculationService`, `ComponentResolver`, `RuleBook`, `ScopeMatcher`, `InsuranceCalculator`, `RtoCalculator`, `VehicleFacts` |
| Output | `Engine\PricingQueryService::getPricing()` (reads published snapshots), `PricingContract` (fixed keys v2, `normalize()`), `PriceListService` (Price List screens) |
| After publish | `Engine\PricingParamObserver` → `PricingRecalcService` → `Jobs\Vehicle\Pricing\RecalculateAffectedJob` (debounced); `PricingSyncStamp` (`pricing.last_updated_at`) |
| Masters | `App\Support\PricingMaster\*` (`MasterRegistry`, 14 masters) + `ImportPricingMasterJob` |
| Writers (DEC-050) | `Prices\PriceService`, `Addons\{Addon,DealerCharge,Discount}Service`, `Rules\*Service` (RTO, TCS, insurance base / IDV / add-ons / companies / defaults, permit map) |
| Quotation | `App\Services\Sales\Quotation\QuotationPricingService` (published prices, gate + TCS re-check, holds) |
| Jobs | `Jobs\Vehicle\Pricing\Process\{DetectPriceLists,ImportVehicleInfo,ImportPrices,ImportAddons,ImportRules,CalculateVehicles}Job` |

## Investigating
1. Open process? `PricingSessionService::gate()`; stage + progress on the session row.
2. Vehicle complete and ACTIVE? `VehicleCompleteness` (one rule; Private ICE → CC, Private EV → Motor, Goods → GVW).
3. Live rows: price / add-on / rule rows with `is_active = 1` and no `expired_on` for the WEF; the most specific scope
   wins (`ScopeMatcher`); an ANY-scoped zero row overrides everything (blank = no rule, 0 = explicit zero).
4. Published? `xlr8_vehicle_pricing_snapshots` for `(model_code, channel, vin_type, permit, wef_date)`; then
   `app(PricingQueryService::class)->getPricing($oemCode)` in tinker. Local DBs may have none — run the process.
5. Held? `PricingHoldService::isHeld()` (API answers 423).
6. Jobs: a queue worker must run (`queue:work --timeout=1800 --tries=1`); check `failed_jobs` and the recalculation log.

## Never
- Load a whole workbook (one sheet, chunked, column cap — `PricingWorkbookReader`).
- Write pricing tables outside the entity services, or TRUNCATE live history (WEF: same → update, newer → expire + insert).
- Create vehicles from CSD or from PV / CV Vehicle sheets; invent fields on stubs.
- Change a locked-spec rule without the owner (stop and ask); GAP-01…11 stay open until instructed.
