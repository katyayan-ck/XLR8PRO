# XCELER8 VEHICLE PRICING — MACHINE SPECIFICATION
# Document-ID: X8-PRC-MS-3.1
# Status: LOCKED-AS-OF-2026-08-31
# Audience: compilers, other AI models, implementers
# Companion: Xceler8_Vehicle_Pricing_Human_Guide_v3.1.md
# Source-of-truth-order: this spec > FRS v3.1 > code comments > informal chat
# Project: BMPL / Xceler8 DMS (Laravel 12 + Backpack)
# Permission: manage_pricing
# Namespace-models: App\Models\Vehicle\Pricing
# Namespace-services: App\Services\Vehicle\Pricing AND App\Services\Vehicle
# Table-prefix: xlr8_vehicle_pricing_*
# Config-file: config/pricing.php
# Env-flags: PRICING_PROCESS_LOG, PRICING_TCS_LIMIT, PRICING_TCS_RATE

---
## 0. META

```yaml
spec_version: "3.1.1"
locked_date: "2026-08-31"
workflow_name: "Staged Multipass Pricing Workflow"
session_constraint: "ONE active ImportSession at a time; discard or complete before start"
rbac: "can:manage_pricing on all admin/pricing routes"
delivery_rules:
  - "DB changes = Laravel migrations only unless user explicitly asks SQL"
  - "Audit columns on pricing tables: created_at, created_by, updated_at, updated_by, deleted_at, deleted_by"
  - "Soft delete + Spatie media where applicable"
  - "Table names start with xlr8_vehicle_pricing_"
  - "Models App\\Models\\Vehicle\\Pricing with array_merge casts against BaseModel"
  - "Header labels never hardcoded in logic; field_code registry in xlr8_vehicle_pricing_sheet_headers"
  - "Services are SSOT; controllers stay thin"
  - "Always deliver full files, not snippets, unless user says otherwise"
  - "Performance: Cache::flexible/remember, eager load, short indexes, chunked jobs"
  - "After sprint: offer changelog (markwalet/laravel-changelog) and laradocs (petebishwhip/laradocs)"
logging:
  file_pattern: "storage/logs/pricing/pricing_process_session_{sessionId}.log"
  toggle: "config('pricing.process_log') / PRICING_PROCESS_LOG"
queue:
  worker: "php artisan queue:work --timeout=1800 --tries=1"
  jobs:
    - App\Jobs\Vehicle\Pricing\DetectPricingWorkbookJob
    - App\Jobs\Vehicle\Pricing\ImportPriceListsJob
    - App\Jobs\Vehicle\Pricing\CalculatePricingSessionJob
    - App\Jobs\Vehicle\Pricing\RecalculateVehiclePricingJob
```

---
## 1. GLOSSARY

| Term | Meaning |
|---|---|
| Segment | Top vehicle family. Codes: PV, CV, BEV, LMM, CSD. Sheet title token after "Price List" maps to segment. TZU maps to LMM. TAXI maps to PV. |
| SubSegment | Child of Segment. On fresh stub, code/name may equal Segment temporarily. |
| Model | Stem without colour. `vehicle_model.code` = OEM Model uppercased. `vehicle_model.name` and `oem_name` = OEM Model on create. |
| Variant | One row per colour. `vehicle_variant.code` = full OEM Code. Last 2 chars = color_code. `model_code` = stem. `oem_name` = OEM Variant. |
| Color | Not a standalone entity. Stored on variant: color, color_code, colour name. |
| OEM Code | Full Model Code WITH colour. Header aliases: OEM Code, Model Code, Vehicle Code. |
| OEM Model | Stem. Header "OEM Model" matches `vehicle_model.name` / `oem_name`. Header "Model" matches `vehicle_model.name`. |
| OEM Variant | Header "OEM Variant" matches `vehicle_variant.oem_name`. Header "Variant" matches `vehicle_variant.display_name`. |
| WEF | With-Effect-From date of a price/rule version. |
| ANY | Blank / "Any" / "ALL" in a scope column = all descendants of that ancestor. |
| Override | More specific row REPLACES the entire previous match (not field-merge). |
| Incomplete | Status INCOMPLETE. Fresh detect stubs. Missing required completeness fields. |
| Active | Status ACTIVE. Requires completeness AND for sale. `is_active` true only when status=ACTIVE. |
| Inactive | Operator took off sale. May be complete. Must NOT be used as default for fresh stubs. |
| Discontinued | No longer manufactured. |
| Taxi Price | Variant flag. When Yes, quotation must choose Private vs Passenger permit; both RTO options calculated. |
| Standard Insurance | Configurable combo. Default = Base Premium + NilDep + Consumables. |
| Snapshot | Frozen JSON of calculated on-road for a vehicle+permit(+VIN age) at publish. |
| Hold | Blocks quote/booking for selected pricelist(s) until reopen. |
| ImportSession | Single pipeline instance. Stages listed in §4. |

---
## 2. INVARIANTS (MUST NEVER BREAK)

INV-01. One variant row = one colour = one OEM Code.
INV-02. `vehicle_model.code` NEVER contains the last 2 colour chars.
INV-03. `vehicle_variant.code` ALWAYS contains those last 2 chars.
INV-04. Every child stores ancestor codes (segment, subsegment, model).
INV-05. Detect from Price List sheets ONLY. PV Vehicle / CV Vehicle sheets are retired.
INV-06. New vehicle from Price List: capture OEM Model, OEM Variant, OEM Code, colour from last 2 chars, pricing numbers. Leave specs blank. Status = INCOMPLETE. Never INACTIVE on create.
INV-07. Active requires completeness. Complete may still be Inactive or Discontinued.
INV-08. Calculate only COMPLETE vehicles. Incomplete listed in impact summary, skipped by calc job.
INV-09. Header labels may change; logic uses `field_code` from sheet_headers registry + synonym resolve.
INV-10. "Model"/"Variant" headers match display names. "OEM Model"/"OEM Variant" match OEM names. Project-wide.
INV-11. Comma-separated scope values = union. Trim + case-insensitive. SynonymService normalizes typos.
INV-12. ANY/blank scope = all descendants. Specific row replaces entire previous match.
INV-13. Same-WEF re-import updates the live row. Different WEF expires previous (`is_active=0`, `expired_on=WEF`) then inserts new.
INV-14. Group import expires ONLY that group (RSA does not expire Shield). No TRUNCATE of live history.
INV-15. One active session. Abandoned sessions stay blocking until resume or discard.
INV-16. Do not calculate until addons written > 0 AND (rules kept OR rules written > 0).
INV-17. Pricing JSON contract is FIXED KEYS. Unused keys present with 0/null. Consumers ignore zeros.
INV-18. Export of addons/rules must dump ACTIVE DB rows only. MUST NOT seed zero-value model rows under ANY (that would override ANY on re-import).
INV-19. RTO Tax Factor text (" % of Rounded Up ESR") is NOT a decimal. Store formula in `tax_basis` (varchar). Numeric rate in `tax_slab`.
INV-20. PhpSpreadsheet must load ONE sheet, cap columns (AZ), chunk rows, disconnect + gc. Whole-workbook `toArray` OOMs on CSD.
INV-21. Index names must be short custom names (MySQL 64-char identifier limit).
INV-22. Casts on Pricing models MUST `array_merge` with BaseModel casts.
INV-23. Queue worker required for detect, price import, calculate.

---
## 3. VEHICLE MASTER RULES

### 3.1 Hierarchy
Segment → SubSegment → Model → Variant(colour)

### 3.2 Create-from-Price-List algorithm
```
INPUT row: oem_code, oem_model, oem_variant, sheet_title
NORMALIZE: trim + UPPERCASE all three codes
LOOKUP variant by full oem_code
IF found: reuse ancestors; do not rewrite specs
ELSE:
  segment_token = first token after "PRICE LIST" in sheet_title
  segment_code = config('pricing.sheet_segment_map')[token] ?? token
  findOrCreate Segment(code=segment_code)
  findOrCreate SubSegment(code=segment_code, segment_code=segment_code)  # temporary same-name ok
  model_code = oem_model UPPER  # NOT stem-of-oem-code
  findOrCreate Model(code=model_code, name=oem_model, oem_name=oem_model, ancestor codes)
  color_code = last 2 chars of oem_code
  create Variant(
    code=oem_code,
    model_code=model.code,
    oem_name=oem_variant,
    custom_name=oem_variant,
    color=color_code, color_code=color_code, colour_name=color_code,
    ancestor codes,
    status=INCOMPLETE,
    is_active=false
  )
```

### 3.3 Completeness gate (Vehicle Info import ONLY, not detect)
ALWAYS required:
Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain,
Body Make, Body Type, GST%, Permit, Taxi Price, Custom Model, Custom Variant,
Display Name, Colour Name.

CONDITIONAL (insurance-aligned):
```
IF permit in {Private} AND fuel is ICE AND (wheels=4 OR passenger-4W): REQUIRE cc
IF permit in {Private} AND fuel is EV  AND (wheels=4 OR passenger-4W): REQUIRE motor
IF permit in {Passenger} AND wheels=4 AND fuel is ICE: REQUIRE cc
IF permit in {Passenger} AND wheels=4 AND fuel is EV:  REQUIRE motor
IF permit in {Goods}: REQUIRE gvw
IF permit in {Passenger} AND wheels=3: REQUIRE none of cc/motor/gvw
```

Status decision:
```
IF user marks DISCONTINUED: status=DISCONTINUED
ELSE IF user marks INACTIVE: status=INACTIVE (allowed even if incomplete)
ELSE IF complete AND user wants on-sale: status=ACTIVE, is_active=true
ELSE: status=INCOMPLETE, is_active=false
ACTIVE is illegal when incomplete.
```

### 3.4 Vehicle Info export
ALL vehicles (active, incomplete, inactive, discontinued), sorted Segment then OEM Model.
New stubs: OEM Model filled; colour name = last 2 of OEM Code if no other name.

---
## 4. WORKFLOW STATE MACHINE

```
STATES = [
  start,
  detecting,
  awaiting_vehicle_info,
  importing_vehicle_info,
  awaiting_prices,
  importing_prices,
  awaiting_addons,
  importing_addons,
  awaiting_rules,
  importing_rules,
  awaiting_calculate,   # impact summary
  calculating,
  complete,
  discarded
]

TRANSITIONS:
  start --upload Pricing.xlsx + multi-select sheets + WEF--> detecting
  detecting --job done--> awaiting_vehicle_info
  awaiting_vehicle_info --export Vehicle_Info--> (ops fill) --import--> importing_vehicle_info
  importing_vehicle_info --done--> awaiting_prices   # incomplete may remain
  awaiting_prices --upload same/updated Pricing.xlsx--> importing_prices
  importing_prices --done--> awaiting_addons
  awaiting_addons --export Addon-N-Discounts--> (ops edit) --import--> importing_addons
  importing_addons --written>0--> awaiting_rules
  awaiting_rules --keep existing OR import Insurance.xlsx + RTO-Rules.xlsx--> awaiting_calculate
  awaiting_calculate --hold optional + calculate--> calculating
  calculating --snapshots written--> complete
  ANY_NON_TERMINAL --discard--> discarded  # does NOT rollback already-committed WEF rows; ops must re-import previous workbook to restore
]

SESSION_RULE:
  IF exists session WHERE status NOT IN (complete, discarded):
    new start FORBIDDEN
    UI offers Resume or Discard
```

Abort effects:
- Discard marks session discarded. Does not DELETE expired previous versions.
- To restore prior live prices, re-import the previous workbooks with a new session/WEF.
- Reset route (`pricing.reset`) is a DEV tool: flushes session/profile/prices/snapshots/holds/CSD; KEEPS headers, addons, discounts, dealer charges, RTO, insurance, TCS, synonyms; deletes vehicle_model/variant created after cutoff date.

---
## 5. SOURCE WORKBOOKS

### 5.1 Pricing.xlsx (rapid change)
Sheets (multi-select): Price List PV | CV | BEV | LMM | LMM TZU | CSD
CSD may alternatively come from CSD Index Codes.xlsx (optional standalone).

Price List columns (logical field_codes, labels vary):
- oem_code, oem_model, oem_variant
- ex_showroom / asset value / freight / dealer handling / dealer margin
- gst related amounts as present
- colour premium if present
Store in `xlr8_vehicle_pricing` keyed by variant.code (full OEM Code).
Colour premium: same variant-code family may differ by last 2 chars.

### 5.2 Addon-N-Discounts.xlsx
| Sheet | Type | Scope keys | Amount model |
|---|---|---|---|
| Dealer Charges - Segment Wise | DEALER_CHARGES | Segment + Permit + Model | WIDE columns: incidental, fastag, trc, rto_tape, cod |
| RSA | ADDON RSA | Segment + Model | tenure rows / year amounts; first paid option default |
| Shield | ADDON SHIELD | Shield Pack + Transmission + Fuel | scheme columns; Scheme 1 default; variant.shield_pack used to match; blank pack matches ANY pack only |
| Exchange | DISCOUNT EXCHANGE | Model + Variant | scheme_name + oem_share + dealer_share + total |
| Corporate | DISCOUNT CORPORATE | Model + Variant | category (CAT B, BULK 1, …) + oem + dealer + total |

Grouping: Segment ANY Model ANY = all descendants. Later specific Model row replaces whole previous match. Export MUST output only stored rule rows, not one blank row per vehicle.

### 5.3 Insurance.xlsx (rare)
Sheets: Insurance Co. | Insu Premium | Rules | (addons if present)
- Insurance Co → `xlr8_vehicle_pricing_ins_defaults` (model + permit + company + is_default). NO separate company master table.
- Insu Premium → `xlr8_vehicle_pricing_ins_base_rules`. Header often on ROW 2. Plans like 1+3 / 3+3. IDV1..N. OD factor applies to SUM of available IDVs, 3 decimal places.
- Rules → RTO permit → Insu permit map. Stored on session.notes.insu_permit_map.
- Addons per company/plan/vehicle-group → `xlr8_vehicle_pricing_ins_addon_rates`.
Standard combo default = Base + NilDep + Consumables; combo is configurable.
Quotation shows all addons as checkboxes; NilDep+Consumables preselected (frozen if policy says so).

### 5.4 RTO-Rules.xlsx (rare)
Scope: Permit, Wheels, Reg Type, Body Type, GVW, Seater, Fuel, CC, …
Values: Tax Factor (TEXT formula), Tax Slab (numeric), Surcharge (often "12.5% of Tax"), Hypothecation, Green Tax, Registration Fee, Duplicate Tax Card, Fitness, Penalty, Outside State TRC / RTO Tape.
Most specific matching row wins.
Taxi: taxi_price=Yes forces Private vs Passenger choice; insurance follows mapped Insu permit.

### 5.5 CSD Index Codes.xlsx
Optional. Limited inventory. Same WEF rules. Selected only when CSD sheet checked.

---
## 6. MATCHING ENGINE

```
normalize(value):
  trim
  case-insensitive
  SynonymService.getSynonym(entity_type, value) if set

scope_match(row, vehicle):
  for each dimension in {segment, subsegment, model, variant, permit, fuel, transmission, wheels, shield_pack, ...}:
    cell = row[dimension]
    if cell is blank OR ANY OR ALL: pass
    else:
      tokens = split(cell, ',') map normalize
      if vehicle.dimension not in tokens AND vehicle.ancestor_name not in tokens: fail
  return all dimensions pass

specificity(row):
  count of dimensions that are NOT any/blank
  (business rule: more specific REPLACES entire previous match; no field-level merge)

winner = last matching row when rows ordered general → specific
# Workbook convention: global first, overrides below.
```

---
## 7. CALCULATION CONTRACT

### 7.1 Default quotation load
```
RSA = first paid 1-year option (or first available paid)
Shield = first available scheme (Scheme 1)
Dealer charges = all applicable resolved heads
Insurance = default company + standard combo (OD+TP+NilDep+Consumables if available)
RTO = vehicle.permit (if taxi_price: user chooses Private|Passenger)
Accessories = AccessoryService applicable packs
TCS = rate/limit from TcsService / pricing array (default 1% above 10 Lakh)
VIN age toggle default = New VIN until Stock module exists
```

### 7.2 On-road composition (keys always present)
```
ex_showroom
+ incidental, fastag, trc, rto_tape, cod   # dealer charges
+ rsa_amount, shield_amount
+ accessory_pack_amount
+ insurance { companies[], permits[], addons[], bifurcation }
+ rto { permit_options[], tax, hypothecation, green_tax, fees, tape, bifurcation }
+ tcs
- rsa_discount, shield_discount, accessory_discount, cash_discount
- corporate / exchange if selected
= on_road

withheld_bucket:
  if user removes RSA/Shield/Accessories below threshold:
    corresponding discount moves to withheld (stored in quotation JSON only)

discount_bifurcation:
  cash vs credit_note from *_mode flags at quotation level
```

### 7.3 Insurance math
```
idv_sum = sum(available IDV_1..IDV_n for plan years)
od = round(idv_sum * od_factor, 3)   # if company uses factor
# else od = sum of year-wise OD components
tp = tp_basic mapped to seating/cc/gvw/permit
gst on premium as per rule
standard = od + tp + nildep + consumables (if flagged available)
compute ALL company × plan × permit combos for the vehicle; mark default
```

### 7.4 RTO math
```
match most specific rule
if tax_basis contains "% of Rounded Up ESR": tax = f(round_up(ex_showroom), tax_slab)
if surcharge_formula contains "% of Tax": surcharge = tax * pct
+ hypothecation + green_tax + registration_fee + duplicate_tax_card + fitness + penalty + rto_tape
```
Invoice-base formula for IDV %: STILL TBD BY USER. Engine must accept a hook.

### 7.5 JSON producer
`PricingEngineService` + `PricingJsonContract`.
All keys always emitted. Accessories via existing AccessoryService. Dual permit when taxi_price.

---
## 8. DATA MODEL (LOGICAL)

```
xlr8_vehicle_pricing_import_sessions
  stage, wef_date, selected_sheets json, notes json, created_by, audit, soft delete

xlr8_vehicle_pricing_sheet_headers
  sheet_code, field_code, current_label   # KEEP across resets

xlr8_vehicle_pricing_profile
  model_code (unique), variant_code, segment, completeness flags, is_disabled

xlr8_vehicle_pricing
  variant/model_code, price heads, wef_date, expired_on, is_active

xlr8_vehicle_pricing_history          # schema exists; live importer currently versions in-place
xlr8_vehicle_pricing_change_flags     # per-field old/new for impact
xlr8_vehicle_pricing_affected
xlr8_vehicle_pricing_draft
xlr8_vehicle_pricing_snapshots        # published JSON
xlr8_vehicle_pricing_holds            # pricelist / ALL
xlr8_vehicle_pricing_csd

xlr8_vehicle_pricing_dealer_charges   # WIDE incidental/fastag/trc/rto_tape/cod + segment/permit/model
xlr8_vehicle_pricing_addons           # RSA / SHIELD + scope cols + scheme/tenure
xlr8_vehicle_pricing_addon_history    # schema exists; import does not copy yet
xlr8_vehicle_pricing_discounts        # EXCHANGE / CORPORATE + category/scheme + shares
xlr8_vehicle_pricing_discount_history # schema exists; import does not copy yet

xlr8_vehicle_pricing_rto_rules        # tax_basis varchar, tax_slab decimal, surcharge numeric + formula
xlr8_vehicle_pricing_ins_defaults     # company per model+permit
xlr8_vehicle_pricing_ins_base_rules
xlr8_vehicle_pricing_ins_addon_rates
xlr8_vehicle_pricing_tcs_config

xlr8_utils_synonyms                   # entity_type + canonical + synonym
vehicle_segment / vehicle_sub_segment / vehicle_model / vehicle_variant
```

Versioning pattern (ALL rule/price tables):
```
is_active=1 AND wef_date <= today AND (expired_on IS NULL OR expired_on >= today)
```

---
## 9. SERVICES (SSOT)

| Service | Responsibility |
|---|---|
| VehicleService / VehicleMasterService | findOrCreate segment/sub/model/variant; completeness; descendant queries |
| SheetHeaderService | label ↔ field_code; header row scan |
| SynonymService | setSynonym / getSynonym |
| PriceListVehicleDetector | detect stubs from selected price sheets |
| VehicleInfoExportService / ImportService | round-trip masters + gate |
| PriceListPricingImporter | chunked single-sheet price write + WEF expire |
| AddonDiscountImportService / ExportService | group expire + wide dealer + ANY-safe export |
| RulesWorkbookService | RTO + insurance import/export/keep |
| RtoService | cached match + amount split |
| InsuranceService | cached multi-company plans + addons |
| TcsService | limit/rate |
| AccessoryService | existing packs/discounts (DO NOT rewrite) |
| PricingEngineService | compose JSON |
| PricingJsonContract | key lock |
| PricingSessionService | stage machine, one-active |
| ImpactResolver | who recalculates |
| PricingProcessLogger | per-session file |
| PricingResetService | dev reset |

Controllers: PricingWorkflowController, HoldController, TcsConfigController, RtoRuleController, InsuranceController, PricingResetController, PricingController (API getPricing).
Do NOT register a second PricingApiController class (duplicate class fatal).

---
## 10. CODE PRECAUTIONS

CP-01. Never `Spreadsheet::load` full book into memory for CSD/PV. `setLoadSheetsOnly([$name])`.
CP-02. `rangeToArray` cap columns A–AZ. Chunk 50–100 rows. `disconnectWorksheets(); gc_collect_cycles();`.
CP-03. Worker `--timeout=1800 --tries=1`. `queue:flush` after FatalError / MaxAttempts.
CP-04. Custom short index names.
CP-05. `onlyExisting($table, $payload)` before insert — live schema drifts.
CP-06. Dealer charges WIDE columns, not charge_code rows, unless a new migration says otherwise.
CP-07. Export dealers/RSA/Shield/Corporate from ACTIVE rules only. No vehicleGroups zero-fill.
CP-08. Insurance Premium: scan first 15 rows for header (Permit + Insu Co + Plan).
CP-09. RTO: never write Tax Factor text into decimal `tax_factor` alone.
CP-10. Blade progress: poll `pricing.workflow.progress` / cache keys. Cap error lists (8–15).
CP-11. Routes live in `routes/backpack/pricing.php` with Backpack web+admin + can:manage_pricing. Named `pricing.*`.
CP-12. Login/route missing usually means pricing.php not loaded by RouteServiceProvider / backpack route loader.
CP-13. complete_vehicle_info.php helper must implement §3.3 gate and four statuses.
CP-14. Do not mark detect stubs INACTIVE.
CP-15. Model unique `code` — catch duplicate BOLERONEO etc.; candidate codes in findOrCreateModel.
CP-16. Casts: `protected $casts = array_merge(parent::casts(), [...]);` (adapt to BaseModel pattern).
CP-17. Color is a variant column, not a pivot table.
CP-18. KeywordValueService for lookups — no old Keyword helper.
CP-19. Utils namespace is `App\Services\Utils`.
CP-20. Impact / calculate disabled until addons.written > 0.

---
## 11. USER / OPS STEPS

```
0. php artisan queue:work --timeout=1800 --tries=1
1. Admin → Pricing Workflow → Start
   upload Pricing.xlsx, multi-select Price List sheets, set WEF
2. Wait detect job. Download Vehicle_Info. Fill completeness. Re-import.
3. Continue to Prices. Re-upload Pricing.xlsx (same or edited). Wait job.
4. Download Addon-N-Discounts (active rules + blank amounts only where NO rule exists at that scope).
   Edit. Re-import. Confirm written > 0.
5. Rules screen: Keep existing OR download current + import original Insurance.xlsx and RTO-Rules.xlsx.
6. Impact summary. Optional Hold All / per list. Calculate & Publish.
7. Reopen holds. Session complete.
API: getPricing(variant_code, permit, options) returns fixed JSON.
```

Workbooks to re-import after a bad export: ALWAYS the ORIGINALS
(Addon-N-Discounts.xlsx, Insurance.xlsx, RTO-Rules.xlsx), never a zero-seeded export.

---
## 12. DONE vs STILL NEEDED

### DONE (implemented in current tree)
- Staged session workflow + blades + manage_pricing routes
- Detect from Price List sheets; Vehicle Info export/import + completeness
- Four vehicle statuses
- Chunked price import + WEF in-place versioning + change flags
- Sheet header registry
- Addon/discount import-export with group expire
- Wide dealer charges + scope columns migration
- RTO tax_basis varchar + RulesWorkbookService locateHeader
- Insurance Co → ins_defaults; Premium → ins_base_rules; permit map on session notes
- Hold + TCS screens
- Reset service (keeps rules/headers)
- Process logger per session
- PricingEngineService / JSON contract skeleton
- VehicleService SSOT direction
- Synonym table + service planned/used on import
- Single-sheet OOM mitigation
- Impact blade KPI (not raw JSON dump) — verify on next run

### PARTIAL / GAPS
GAP-01. Insurance addon-rate rows from wide Insu Premium addon columns — importer still thin; many addon rates empty after import.
GAP-02. `*_history` satellite tables not written on expire (versioning is in-place only).
GAP-03. No insurance company master; companies only as strings on defaults/rules.
GAP-04. Invoice-base formula for IDV still TBD.
GAP-05. End-to-end Calculate & Publish not signed off after latest import fixes.
GAP-06. Accessory packs inside published JSON — wiring must use existing AccessoryService; verify keys.
GAP-07. Withheld-discount + cash/credit bifurcation at quotation — specified, quotation module not this sprint’s UI.
GAP-08. Synonym coverage incomplete (typos still leak into segment/fuel).
GAP-09. CSD detect/import still the heaviest sheet; keep chunking.
GAP-10. Shield pack matching against variant.shield_pack needs live test.
GAP-11. Dual-permit taxi snapshot both options — verify in engine output.
GAP-12. Progress UI copy-paste of logs; download button for exports must be obvious.
GAP-13. Discard does not rollback WEF writes (documented, not a bug, but ops must understand).
GAP-14. Changelog + laradocs update after this slice — pending user ask.
GAP-15. Configurable standard-insurance combo persisted (defaults exist; admin editor thin).

### MUST-TEST BEFORE GO-LIVE
T-01. Detect PV+CV+BEV+LMM+TZU+CSD without 300s/OOM.
T-02. Vehicle_Info contains ALL vehicles sorted; stubs INCOMPLETE not INACTIVE.
T-03. Completeness gate matches §3.3 (3W passenger no CC).
T-04. Price import written ≈ source rows; same-WEF update vs new-WEF expire.
T-05. Addon export has ANY rows with real amounts; NO extra 0 model overrides.
T-06. Re-import ORIGINAL addons → written>0 for dealer/RSA/shield/exchange/corporate.
T-07. RTO import: tax_basis text saved; tax_slab numeric; 0 SQL 1366.
T-08. Insurance Co + Premium written > 0; defaults companies visible.
T-09. Calculate produces snapshot JSON with full key set, insurance multi-company, RTO split, accessories.
T-10. Hold blocks quote path; reopen restores.
T-11. Second session blocked until complete/discard.

---
## 13. ACCEPTANCE CRITERIA (MACHINE)

```
AC-DETECT: stubs_created >= new_oem_codes_not_in_variant_table
AC-STATUS: count(variant where created_by_detect AND status='INACTIVE') == 0
AC-GATE: active_incomplete == 0
AC-PRICE: prices_written > 0 AND job_exit == success
AC-ADDON-EXPORT: no row with all-zero amounts AND model not ANY unless DB already stored that row
AC-ADDON-IMPORT: sum(written across 5 sheets) > 0
AC-RTO: errors containing "Incorrect decimal value" == 0
AC-INS: ins_defaults_active > 0 AND ins_base_rules_active > 0
AC-CALC: snapshots_for_complete_vehicles > 0
AC-JSON: every key in PricingJsonContract present
AC-SESSION: two concurrent non-terminal sessions impossible
```

---
## 14. FILE MAP (EXPECTED)

```
app/Services/Vehicle/VehicleService.php
app/Services/Vehicle/Pricing/*.php
app/Services/Utils/SynonymService.php
app/Models/Vehicle/Pricing/*.php
app/Http/Controllers/Admin/Pricing/*.php
app/Http/Controllers/Api/.../PricingController.php
app/Jobs/Vehicle/Pricing/*.php
resources/views/admin/pricing/workflow/*.blade.php
routes/backpack/pricing.php
config/pricing.php
database/migrations/2026_08_*_pricing_*.php
storage/logs/pricing/pricing_process_session_*.log
```

END-OF-SPEC
