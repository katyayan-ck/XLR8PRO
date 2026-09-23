# XCELER8 PRICELIST — CLINE CONTEXT
Feed this file (or `/pricing-workflow.md` which points here) before any pricing edit.
Spec wins: this file + Machine Spec v3.1 > FRS v3.1 > code comments > chat.
Companion human doc: `Xceler8_Vehicle_Pricing_Human_Guide_v3.1.md`
Deep catalog: `docs/CLINE_SERVICES.md` §7

```
Stack: Laravel 12 + Backpack | RBAC: manage_pricing
Models: App\Models\Vehicle\Pricing\* extend BaseModel (SoftDeletes + Spatie media + audit actor)
Services SSOT: App\Services\Vehicle\Pricing\* + App\Services\Vehicle\VehicleService
Utils: App\Services\Utils\SynonymService | App\Services\Utils\KeywordValueService
Accessories: App\Services\Vehicle\AccessoryService (DO NOT rewrite)
Tables: xlr8_vehicle_pricing_*
Config: config/pricing.php
Routes: routes/backpack/pricing.php  names pricing.*
Env: PRICING_PROCESS_LOG | PRICING_TCS_LIMIT=1000000 | PRICING_TCS_RATE=1
Log: storage/logs/pricing/pricing_process_session_{id}.log
Worker: php artisan queue:work --timeout=1800 --tries=1
Delivery: migrations only unless user asks SQL | full files not snippets
Casts: array_merge with BaseModel | short custom index names
Do not grow: app/Helpers/XpricingHelper.php | QuotesHelper.php | VehicleHelper.php
```

---
## WANT

Staged, one-session pipeline that turns OEM Price Lists + addons + rare Insurance/RTO books into:

1. Complete vehicle masters (or listed incomplete).
2. WEF-versioned ex-showroom rows.
3. Scoped dealer charges / RSA / Shield / Exchange / Corporate.
4. Insurance (all company×plan×addon) + RTO (most-specific) + TCS.
5. Published snapshot JSON per complete vehicle (NV/OV, permit options).
6. On-demand `getPricingPayload` with **fixed keys** (zeros ignored by consumers).

Quotation consumes the engine. It does not re-implement math.

---
## HAVE (built)

Detect → Vehicle Info export/import + 4 statuses + completeness gate → chunked price import + WEF → addon/discount import-export (ANY-safe) → rules keep/import (tax_basis varchar) → hold/TCS screens → session lock → process logger → engine/JSON contract skeleton → reset service → header registry → synonym resolve on import.

---
## PLAN (locked order — do not reorder)

```
Start(Pricing.xlsx, multi-select sheets, WEF)
  → DetectPricingWorkbookJob / PriceListVehicleDetector
  → Vehicle_Info.xlsx export (ALL vehicles) → ops fill → VehicleInfoImportService
  → ImportPriceListsJob / PriceListPricingImporter
  → Addon_N_Discounts export (ACTIVE rules only) → ops edit → AddonDiscountImportService
  → Rules: KEEP existing OR import Insurance.xlsx + RTO-Rules.xlsx
  → Impact summary
  → optional Hold → CalculatePricingSessionJob / PricingEngineService::calculateAndPublish
  → Reopen holds → session complete
```

CSD optional standalone (`CSD Index Codes.xlsx`) only if CSD sheet selected.
RSA/Shield are part of addon pass (checkboxes default on), not the price-list multipass.

---
## PENDING

- Insurance addon-rate parse from wide Insu Premium columns (often empty after import)
- Write satellite `*_history` tables (today: in-place wef/expired_on/is_active only)
- Insurance company master (companies are strings on ins_defaults / base_rules)
- Invoice-base formula for IDV % (business TBD — leave hook)
- E2E sign-off of Calculate & Publish on full PV+CV+CSD
- Verify JSON: accessories via AccessoryService, taxi dual-permit, Shield pack, withheld, cash/credit
- Synonym coverage gaps (PEROSNAL etc.)
- Changelog + laradocs for this slice
- Configurable standard-insurance combo admin (default = Base+NilDep+Consumables)

---
## NEVER BREAK

- 1 variant row = 1 colour = 1 OEM Code. Color is a **column on variant**, not a table.
- `vehicle_model.code` = OEM Model UPPER, **no** last-2 colour. `vehicle_variant.code` = full OEM Code.
- Detect from **Price List sheets only**. PV Vehicle / CV Vehicle retired.
- Fresh stub status = **INCOMPLETE**, never INACTIVE. `is_active` true only if status=ACTIVE.
- Active requires completeness. Complete may be Inactive or Discontinued.
- Calc/publish **complete + has price** only. Incomplete listed on impact, skipped.
- Header labels change → `field_code` via `SheetHeaderService`. Never hardcode Excel titles.
- Header `Model`/`Variant` → display names. `OEM Model`/`OEM Variant` → OEM names. Project-wide.
- ANY/blank scope = all descendants. Comma list = union (trim, case-insensitive, SynonymService).
- More specific row **replaces entire previous match** (not field-merge).
- Same WEF = update live row. New WEF = expire old (`is_active=0`,`expired_on=WEF`) then insert.
- Group expire only that group (RSA import does not touch Shield). No TRUNCATE of history.
- One active ImportSession. Abandoned blocks until Resume or Discard.
- Discard does **not** rollback WEF writes. Restore = re-import previous workbooks.
- Do not calculate if addons.written==0 or (no rules kept AND rules.written==0).
- Export addons/rules = **active DB rows only**. Never seed zero model rows under ANY.
- RTO Tax Factor text → `tax_basis`. Numeric → `tax_slab`. Never put "% of Rounded Up ESR" in decimal.
- PhpSpreadsheet: `setLoadSheetsOnly`, columns A–AZ, chunk 50–100, disconnect + `gc_collect_cycles()`.
- One `PricingController` API class. Do not also register `PricingApiController` (duplicate-class fatal).
- Keyword lookups = `KeywordValueService`. Utils namespace = `App\Services\Utils`.

---
## VEHICLE CREATE (detect)

```
oem_code, oem_model, oem_variant = trim+UPPER
lookup variant by full oem_code
if found: reuse ancestors; do not rewrite specs
else:
  segment = config pricing.sheet_segment_map[first token after "PRICE LIST"]
    PV,CV,BEV,LMM,CSD as-is | TZU→LMM | TAXI→PV
  findOrCreate Segment + SubSegment(same code ok temporarily)
  findOrCreate Model(code=oem_model, name=oem_name=oem_model)
  Variant(code=oem_code, model_code=model.code, oem_name=custom_name=oem_variant,
          color=color_code=colour_name=last2(oem_code), status=INCOMPLETE, is_active=false)
```

SSOT: `VehicleService` (`ensure`/`findOrCreate*`/`applyVehicleInfo`/`isComplete`/`missingFields`).
Do not use a narrower completeness list from `VehicleMasterService` for the Active gate.

### Completeness (Vehicle Info import ONLY)

Always: Segment, SubSegment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST%, Permit, Taxi Price, Custom Model, Custom Variant, Display Name, Colour Name.

Conditional:
- Private or Passenger-4W + ICE → CC
- Private or Passenger-4W + EV → Motor
- Goods → GVW
- Passenger 3W → none of CC/Motor/GVW

Status: DISCONTINUED if marked; INACTIVE if marked (allowed incomplete); else ACTIVE only if complete; else INCOMPLETE.

Export Vehicle Info: **all** statuses, sort Segment then OEM Model.

---
## MATCHING

```
normalize = trim + casefold + SynonymService.getSynonym(type, value)
each scope dim: blank|ANY|ALL → pass; else vehicle/ancestor in comma tokens
specificity = count of non-ANY dims
winner = last match when rows ordered general → specific
RTO/Insurance: most specific wins
Dealer: Segment+Permit+Model. Model-level overrides Segment ANY.
Shield: variant.shield_pack; blank pack matches ANY pack only.
```

---
## WORKBOOKS

| Book | Sheets | When | Target |
|---|---|---|---|
| Pricing.xlsx | Price List PV/CV/BEV/LMM/LMM TZU/CSD | rapid | detect + `xlr8_vehicle_pricing` |
| Addon-N-Discounts.xlsx | Dealer Charges, RSA, Shield, Exchange, Corporate | mid | dealer_charges / addons / discounts |
| Insurance.xlsx | Insurance Co, Insu Premium, Rules, addons | rare | ins_defaults, ins_base_rules, ins_addon_rates, session.notes.insu_permit_map |
| RTO-Rules.xlsx | RTO Rules | rare | rto_rules |
| CSD Index Codes.xlsx | CSD | optional | pricing_csd / CSD price sheet |
| Vehicle_Info_*.xlsx | generated | gate | vehicle_model + vehicle_variant |

Dealer charges WIDE cols: incidental, fastag, trc, rto_tape, cod.
RSA: Segment+Model; first paid year = quote default.
Shield: Pack+Transmission+Fuel; Scheme 1 default.
Exchange/Corporate: Model+Variant; oem_share+dealer_share+total; Corporate has category (CAT B, BULK 1…).

Re-import after a bad export: **originals**, not zero-seeded exports.

---
## SCREENS / ROUTES / OPS LOGIC

Prefix `/admin/pricing` · middleware Backpack web+admin + `can:manage_pricing`.
Controller: `app/Http/Controllers/Admin/Pricing/PricingWorkflowController.php`
Blades: `resources/views/admin/pricing/workflow/*`

| # | Screen | Route name | Blade | Logic |
|---|---|---|---|---|
| 0 | Hub | `pricing.workflow.index` | `index.blade.php` | Show active session or start CTA. Resume vs Discard. |
| 1 | Start detect | `pricing.workflow.start-form` POST `start` | `start.blade.php` | Multi-select Price List sheets + WEF + file. Forbidden if another session not complete/discarded. Dispatches Detect job. Poll `pricing.workflow.progress`. |
| 1b | Progress JSON | `pricing.workflow.progress` | — | Cache `pricing_progress_{id}`. Counts + last OEM code + sheet name. |
| 2 | Vehicle Info | `vehicle-info-form` GET export POST import | `vehicle-info.blade.php` | Export all vehicles. Import applies gate. Poll `vehicle-info-progress`. Incomplete may remain. |
| 3 | Prices | `prices-form` POST `prices` | `prices.blade.php` | Re-upload Pricing.xlsx. ImportPriceListsJob. Skip no-price + still-incomplete. ChangeFlag on material change. |
| 4 | Addons | `addons-form` GET export POST import | `addons.blade.php` | Export active rules. Import expires per sheet type. Gate: written>0 before next. |
| 5 | Rules | `rules-form` keep=`rules-keep` import=`rules` | `rules.blade.php` | If rules exist: Keep OR upload Insurance+RTO. Presence via `RulesWorkbookService::presence`. |
| 6 | Impact | `impact-summary` + `impact-summary-view` | `impact-summary.blade.php` | KPI: stubs, completed, incomplete, prices written, addon written/skipped, rule errors (cap 8–15). No giant JSON dump. |
| 7 | Calculate | POST `pricing.workflow.calculate` | same | Optional hold then CalculatePricingSessionJob. Skip Hold + incomplete. Snapshots. |
| 8 | Session JSON | `session-status` `failed-vehicles` | — | Job health / retry list. |
| — | Discard | POST `pricing.workflow.discard` | — | Stage=discarded. Does not undelete prices. |
| H | Hold | `pricing.hold.index/apply/reopen` | hold views | Hold All or PV/CV/LMM/CSD/BEV. Blocks quote/booking. |
| T | TCS | `pricing.tcs.index/update` | tcs | Global limit/rate (also in engine array). |
| R | RTO CRUD | `pricing.rto.*` + test-calculate | rto/* | Manual rule edit. `RtoService::quote`. |
| I | Insurance browse | defaults / base-rules / addon-rates + test | insurance/* | `InsuranceService::quote`. |
| Z | Reset (dev) | `pricing.reset` | text log | Flush sessions/profile/prices/snapshots/holds/CSD/jobs. KEEP headers, addons, discounts, RTO, ins, TCS, synonyms. Delete model/variant created after cutoff. |

API: existing `PricingController@getPricing` (not a second Api class).
Quote call:

```
app(PricingEngineService::class)->getPricingPayload($oemCode, [
  'channel' => 'normal', // or csd
  'vin_type' => 'nv',    // nv|ov  default nv until Stock
  'permit'  => 'Private',
]);
```

---
## DEFAULT QUOTE LOAD

RSA 1yr first-paid · Shield Scheme 1 · all matching dealer heads · default insurer + standard combo (OD+TP+NilDep+Consumables, configurable) · RTO vehicle.permit (taxi_price=Yes → force Private|Passenger choice, both pre-calc) · AccessoryService packs · TCS 1% above 10L.
All insurance addons shown; NilDep+Consumables preselected.
Remove RSA/Shield/Acc below threshold → discount to **withheld** (quotation JSON only).
Cash vs credit-note split at quotation (`*_mode` flags).

On-road = ESR + dealer heads + RSA + Shield + accessories + insurance + RTO + TCS − discounts.
Insurance: `od = round(sum(IDV1..n)*od_factor, 3)` or year-wise OD; compute all company×plan×permit; mark default.
RTO: if tax_basis contains "% of Rounded Up ESR" use rounded ESR × slab; surcharge may be "% of Tax".

---
## TABLES

```
xlr8_vehicle_pricing_import_sessions   stage, wef_date, selected_sheets json, notes json
xlr8_vehicle_pricing_sheet_headers     sheet_code, field_code, current_label  (KEEP on reset)
xlr8_vehicle_pricing_profile           per code completeness flags
xlr8_vehicle_pricing                   live prices + wef/expired_on/is_active
xlr8_vehicle_pricing_history           schema exists; importer versions in-place today
xlr8_vehicle_pricing_change_flags      old/new per field
xlr8_vehicle_pricing_affected | draft | snapshots | holds | csd
xlr8_vehicle_pricing_dealer_charges    WIDE incidental/fastag/trc/rto_tape/cod + segment/permit/model
xlr8_vehicle_pricing_addons            RSA|SHIELD + scope + scheme/tenure
xlr8_vehicle_pricing_addon_history     not written by current import
xlr8_vehicle_pricing_discounts         EXCHANGE|CORPORATE + category/scheme + shares
xlr8_vehicle_pricing_discount_history  not written by current import
xlr8_vehicle_pricing_rto_rules         tax_basis varchar, tax_slab decimal, surcharge + formula
xlr8_vehicle_pricing_ins_defaults      model+permit+company+is_default
xlr8_vehicle_pricing_ins_base_rules    company+plan+permit+factors
xlr8_vehicle_pricing_ins_addon_rates
xlr8_vehicle_pricing_tcs_config
xlr8_utils_synonyms
vehicle_segment | vehicle_sub_segment | vehicle_model | vehicle_variant
```

Live filter: `is_active=1 AND wef_date<=today AND (expired_on IS NULL OR expired_on>=today)`.

---
## FILES

```
app/Services/Vehicle/VehicleService.php
app/Services/Vehicle/VehicleMasterService.php          # narrower helpers; Active gate stays on VehicleService
app/Services/Vehicle/AccessoryService.php
app/Services/Vehicle/Pricing/SheetHeaderService.php
app/Services/Vehicle/Pricing/PriceListVehicleDetector.php
app/Services/Vehicle/Pricing/PriceListPricingImporter.php
app/Services/Vehicle/Pricing/VehicleInfoExportService.php
app/Services/Vehicle/Pricing/VehicleInfoImportService.php
app/Services/Vehicle/Pricing/AddonDiscountImportService.php
app/Services/Vehicle/Pricing/AddonDiscountExportService.php
app/Services/Vehicle/Pricing/RulesWorkbookService.php
app/Services/Vehicle/Pricing/InsuranceService.php
app/Services/Vehicle/Pricing/RtoService.php
app/Services/Vehicle/Pricing/TcsService.php
app/Services/Vehicle/Pricing/PricingEngineService.php
app/Services/Vehicle/Pricing/PricingJsonContract.php
app/Services/Vehicle/Pricing/PricingSessionService.php
app/Services/Vehicle/Pricing/PricingResetService.php
app/Services/Vehicle/Pricing/PricingProcessLogger.php
app/Services/Utils/SynonymService.php
app/Models/Vehicle/{Segment,SubSegment,VehicleModel,Variant}.php
app/Models/Vehicle/Pricing/{ImportSession,SheetHeader,Profile,Pricing,PricingHistory,
  ChangeFlag,Affected,Draft,Snapshot,Hold,Csd,DealerCharge,Addon,AddonHistory,
  Discount,DiscountHistory,RtoRule,InsDefault,InsBaseRule,InsAddonRate,TcsConfig}.php
app/Http/Controllers/Admin/Pricing/{PricingWorkflow,Hold,TcsConfig,RtoRule,Insurance,PricingReset}Controller.php
app/Http/Controllers/.../PricingController.php         # getPricing only once
app/Jobs/Vehicle/Pricing/{DetectPricingWorkbook,ImportPriceLists,CalculatePricingSession,
  RecalculateVehiclePricing,ProcessPricingWorkbook}Job.php
resources/views/admin/pricing/workflow/{index,start,vehicle-info,prices,addons,rules,impact-summary}.blade.php
routes/backpack/pricing.php
config/pricing.php
database/migrations/2026_08_*_pricing_*.php
```

Traits: none pricing-specific. All pricing models use **BaseModel** (SoftDeletes, HasMedia collections documents/photos/attachments, audit created_by/updated_by/deleted_by, CrudTrait). User is Authenticatable, not BaseModel.

---
## JOBS / CACHE

| Job | After |
|---|---|
| DetectPricingWorkbookJob | start upload |
| ImportPriceListsJob | prices upload |
| CalculatePricingSessionJob | calculate click |
| RecalculateVehiclePricingJob | single-code refresh |
| ProcessPricingWorkbookJob | legacy umbrella — prefer split jobs |

Cache keys: `pricing_progress_*`, `pricing_vi_progress_*`, `pricing.rto.rules`, `pricing.ins.base_rules`, `pricing.ins.addon_rates`. Forget ins/rto cache after rules import. TTL `config('pricing.progress_cache_ttl_hours')`.

---
## CODE MOVES

When editing import: `onlyExisting($table,$payload)` before insert (schema drift).
Insurance Premium header often **row 2** — scan first 15 rows for Permit+Insu Co+Plan.
LMM and TZU are **two sheets**.
If UI stuck on detect/prices: worker down or OOM — `queue:flush` + `optimize:clear` + restart worker.
Reset route is DEV. Do not use as ops undo.

---
## ACCEPTANCE (quick)

stubs created for unknown OEM Codes · no detect stub with status INACTIVE · active_incomplete==0 · prices_written>0 · addon export has no extra all-zero model overrides · addon written>0 · RTO import zero "Incorrect decimal value" · ins_defaults and ins_base_rules >0 after insurance import · snapshots>0 for complete · JSON has every PricingJsonContract key · two non-terminal sessions impossible.
