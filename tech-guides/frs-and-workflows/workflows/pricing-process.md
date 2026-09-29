# Workflow — the Pricing Process (DEC-073 … DEC-083)

**Where:** Admin → Pricing → Pricing Process (`pricing.workflow.*`). **Permissions:** `PRC_WKFL_MANAGE` (run),
`PRC_WKFL_VIEW` (read-only). **Code:** `App\Services\Vehicle\Pricing\{Session,Import,Engine}\*`, jobs in
`App\Jobs\Vehicle\Pricing\Process\*`. **Detail:** `modules/pricing.md`. **Spec:** `frs/pricing-machine-spec-v3.1.md`.

## The steps (one process at a time; every heavy step is a queued job with on-screen progress)

| # | Step | What happens | Output |
|---|---|---|---|
| 0 | Gate | One open process at a time: Resume, or Discard (only before publish) | — |
| 1 | Start | Upload Pricing.xlsx, pick the price lists (PV / CV / BEV / LMM / LMM TZU / CSD), WEF date, optional holds | session + stored upload |
| 2 | Detect | Known codes = existing variants (full OEM code); unknown → **INCOMPLETE** stub (never for CSD) | detect report |
| 3 | Vehicle Info | Export all vehicles → fix missing fields → re-import (loop); only complete vehicles become ACTIVE | completed / rejected counts |
| 4 | Prices | Ex-showroom, GST, schemes (NV / OV) per WEF: same WEF updates, newer + changed expires + inserts | price rows + history |
| 5 | Add-ons & discounts | Dealer charges, RSA, Shield, Exchange, Corporate, Loyalty: per ticked sheet, expire the group at the WEF and insert; blank = no rule, 0 = zero | rule rows |
| 6 | Insurance & RTO | Standalone workbooks; import required if none stored, else keep or re-import | rule rows + permit map |
| 7 | Impact summary | New vehicles, price ups / downs, groups replaced, how many vehicles will calculate | review |
| 8 | Hold check | Hold / reopen lists (ALL, PV, CV, BEV, LMM, LMM_TZU, CSD, TAXI) | holds |
| 9 | Calculate & publish | Batch per complete vehicle: snapshots per permit × NV / OV × channel (taxi → Private + Passenger) | `xlr8_vehicle_pricing_snapshots` |
| 10 | Summary | Published / skipped / failed; retry failed; **Mark complete** (+ reopen lists) releases the gate | session `completed` |
| 11 | getPricing | Serves the published snapshot (fixed-key contract v2); held list → `hold = true` / API 423 | API + Price Lookup + quotation |

**Discard** undoes exactly what the session wrote (change log `xlr8_vehicle_pricing_session_changes`), only before
publish.

## After the process
- **Masters (DEC-083):** Admin → Pricing → Masters edits dealer charges, discounts, RSA, Shield, corporate, exchange,
  loyalty, RTO / insurance rules, insurance companies / preferences / add-ons, accessories — with WEF versioning.
- **Automatic recalculation:** any pricing-parameter change (except accessories) marks the affected vehicles; a debounced
  queued `RecalculateAffectedJob` republishes them (log: Admin → Pricing → Recalculation log).
- **Sync stamp:** setting `pricing.last_updated_at` moves on any published-price, vehicle-master or accessory change; the
  app re-downloads when it moves.
- **Consumers:** the Price List screens (every user), the API `GET v1/vehicle/pricing/{oemCode}`, the quotation screen
  (published prices only, server-side gate + TCS re-check, hold guard), the booking hold guard.

## Rules that bite
- Completeness: Private + ICE → CC; Private + EV → Motor; Goods → GVW; Passenger / MISC → none (DEC-073 amends §3.3).
- Bases = ex-showroom: RTO ESR rounded up to ₹1,000; IDV = ex-showroom × slot %; TCS when ex-showroom ≥ the limit.
- Never load a whole workbook: `PricingWorkbookReader` (one sheet, chunked, column cap).
- Local databases may have no published snapshots — run the process to fill them.
