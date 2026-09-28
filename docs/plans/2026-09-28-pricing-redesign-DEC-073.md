# Plan: Pricing import process — full redesign (DEC-073)

## Context
You want the vehicle pricing process (11 steps, gate → publish → on-demand `getPricing`) working end to end. A read-only
study of `docs/reference/XLRM-Pricing-Data` (8 workbooks), the locked spec v3.1.1, the existing code
(`app/Services/Vehicle/Pricing/**`, 22 workflow routes, 5 jobs, 26 tables) and every consumer shows the screens exist,
but the results can't be trusted:

1. **The session never completes, and discard damages live data.**
   - The stage stops at `summary`, so the one-session gate stays locked.
   - Discarding after publish soft-deletes live prices and add-ons, and does not un-expire the rows it replaced.
2. **Snapshots collide or duplicate.**
   - Taxi snapshots overwrite the normal one: the unique key has no permit.
   - NV and OV are identical, and every vehicle is written 3 times.
3. **Prices are wrong:**
   - Dealer charges are always 0, and model / variant scope is ignored (BUG-178).
   - The TCS rate is never read (`rate_pct` vs `rate_percent`).
   - Accessories are not in on-road.
   - The RTO block breaks the fixed JSON keys.
   - Insurance NilDep / Consumables are always 0: add-on rates are expired but never imported.
4. **Round-trips lose data.**
   - The Vehicle Info export leaves Fuel / Permit / Body blank (wrong key-value table name).
   - Re-importing the rules workbook wipes insurance.
   - The price import can leave two active price rows.
5. **Gates are weak.**
   - Completeness lives in two places (the variant, and the profile flag); stubs pass fields from their defaults.
   - The variant screen can activate an incomplete vehicle.
   - There is no INCOMPLETE status.
6. **Nothing reads the output.**
   - `getPricingPayload` rebuilds live and never reads snapshots.
   - The pricing API is unrouted.
   - The quotation screen runs on a hard-coded mock with different keys.
   - Hold blocks only the engine; TAXI hold never matches.
7. **Hygiene.**
   - Whole-workbook loads, synchronous imports in HTTP, 5 never-written tables, and a destructive GET `reset` route.
   - No `manage_pricing` permission (the `PRC_*` codes exist).
   - No pricing menu (BUG-069).
   - No tests for the workflow, jobs, `build()` or publish.

**Decisions (user, 28-09) — recorded as DEC-073 before any change:**
- **Completeness:** Passenger **and** MISC need none of CC / Motor / GVW. Private + ICE → CC; Private + EV → Motor;
  Goods → GVW. This overrides spec §3.3, which is amended.
- **Taxi** (`taxi_price = YES`) publishes Private and Passenger snapshots:
  - Passenger 4W uses the RTO **"Taxi"** rules and the insurance **Passenger** permit.
  - The mapping lives in one editable permit map (the insurance "Rules" sheet).
  - MISC uses the RTO "Ambulance" rules and the insurance "Misc" permit.
- **CSD** never creates vehicles; it only writes CSD-channel prices for existing vehicles (unknown codes are reported).
- **LMM TZU** creates stubs with colour `NA`, OEM Model = Model Name, OEM Variant = Material Description.
- **Bases = ex-showroom:**
  - RTO ESR = ex-showroom rounded up to ₹1,000 (BH rules: assessable + dealer margin).
  - IDV = ex-showroom × slot %.
  - Snapshot TCS = configured rate when ex-showroom ≥ the configured limit (₹10 L).
- **Quotation screen rewired** to `getPricing` as the last phase, with hold enforcement and server-side gate re-validation.

**My calls (no business choice; flagged in DEC-073):**
- **Permission:** `PRC_WKFL_MANAGE` is the "manage_pricing" gate; `PRC_WKFL_VIEW` gives read-only.
- **Add-on export:** every applicable group appears. On import, **blank = no rule (skipped)** and **0 = an explicit zero**.
  This honours "blank / 0" and the spec's zero-override guard (INV-18).
- **Prices:**
  - Same WEF → update in place.
  - New WEF → expire and insert only on a material change (DEC-058).
  - Every import row goes to `*_history`.
- **Add-on / discount groups:** the imported group under a new WEF expires its previous rows and inserts the new ones
  (your step 5); other groups are untouched.
- **Duplicate codes in a sheet:**
  - identical rows → one row;
  - conflicting rows (e.g. LMM `1AM2NV4T9LEC1BY`) → both rejected and listed.
- **OV:** only PV / CV / BEV carry an OV block (by column position). Other lists publish OV = NV with OV discounts 0.
- **Insurance combos:** store, per company × plan, OD / TP / GST and each add-on premium separately. Every combination is
  "base + any subset of add-ons", so the snapshot lists them without a 2^n explosion. The default is Base + NilDep +
  Consumables, flagged `frozen`.
- **Withheld:** the snapshot carries `withheld` = zeros; the quotation fills it (not a ledger). Cash / credit-note split
  lives on the quotation.

## Target architecture (Laravel, our rules: controllers thin → services → entity services; Result objects; jobs with timeout / tries / failed())

### State machine and session (`App\Services\Vehicle\Pricing\Session\*`)
- **`PricingStage` enum, stages in order:**
  - `started`
  - `detecting`
  - `vehicle_info` (export / import loop)
  - `prices`
  - `addons`
  - `rules`
  - `impact`
  - `hold_check`
  - `calculating`
  - `summary`
  - terminal: `completed` / `discarded`
- **`PricingSessionService` (one writer of sessions):**
  - `gate()` → the active session or null.
  - `start(file, sheets, wef, holdLists)` → `Result`, `ALREADY_ACTIVE` when one exists.
  - `advance()` with guards per transition.
  - `resume()`.
  - `discard()` — allowed only **before** publish.
  - `complete(reopenLists)`.
- **Discard is exact.** A new `xlr8_vehicle_pricing_session_changes` log records every insert / expire / update a session
  makes (table, id, action, previous state). Discard replays it backwards: soft-delete inserted rows, un-expire expired
  rows. After publish, only Complete is possible.
- **Uploads:** stored per session (`storage/app/pricing/{session}/…`); step 4 may reuse or replace them.
- **Background work:**
  - Every heavy step runs as a job (detect, vehicle-info import, price import, add-on import, rules import, calculate).
  - Progress goes through the existing cache key, plus a `session.progress` JSON column.
  - The UI polls one status endpoint.
- **Streaming reader** (`PricingWorkbookReader`, shared by all importers):
  - loads one sheet at a time with a column cap;
  - chunked rows;
  - header rows found by `SheetHeaderService` (adds `PRICE_LIST_LMM_TZU` and the CSD 2-row header);
  - Indian-format / text numbers parsed ("3,00,752", "-" = 0);
  - percent vs fraction normalised (GST 40 ↔ 0.40);
  - `YES / NO / Y / N` normalised;
  - synonyms applied before matching.

### Step-by-step behaviour
| Step | Behaviour | Main pieces |
|---|---|---|
| **0 Gate** | `PRC_WKFL_MANAGE` required. If a session is active: Resume or Discard (discard only before publish). | `PricingWorkflowController@index`, `PricingSessionService::gate()` |
| **1 Start** | Upload Pricing.xlsx; multi-select the price-list sheets found in the file (PV / CV / BEV / LMM / LMM TZU / CSD); WEF required; optional **Hold selected lists / Hold all**. The holds are applied immediately and recorded on the session. | `start.blade`, `PricingHoldService::hold(lists, session)` |
| **2 Detect** | Price-list sheets only (never PV / CV Vehicle).<br>**Known** = an existing variant by full OEM code (not the profile table).<br>**Unknown** (not CSD) → FRESH **INCOMPLETE** stub:<br>• code, OEM model, OEM variant;<br>• colour = last 2 chars (TZU: `NA`);<br>• segment from the sheet title, sub-segment = segment, model code = OEM model (DEC-049 hyphen stem);<br>• **no defaults that satisfy the gate**: `wheels`, `taxi_price`, colour name, custom names left empty.<br>Report new / known / skipped (CSD unknown) / duplicates. | `PriceListVehicleDetector` (streaming), `VehicleService::createStubFromPriceList` |
| **3 Vehicle Info** | **Export ALL** vehicles (complete + incomplete + inactive + discontinued), sorted Segment → OEM Model. Columns = the reference Vehicle Info layout plus Status and a "Missing fields" hint. Lookups filled from the real key-value table.<br>**Re-import** (job) through `VehicleService::applyVehicleInfo` → entity services.<br>**Completeness** = the single rule in `VehicleCompleteness` (below).<br>ACTIVE only when complete; incomplete may be INACTIVE / DISCONTINUED; otherwise INCOMPLETE. Unknown lookup values are **rejected with a message** (no auto-created key-values).<br>Screen summary: completed / still incomplete / rejected (with reasons, downloadable). The loop repeats until the user continues. | `VehicleInfoExportService`, `VehicleInfoImportService` (job) |
| **4 Prices** | Same or updated Pricing.xlsx + WEF → ex-showroom, assessable, GST, GST amount, MM invoice, dealer margin, NV / OV scheme blocks. History row per code; WEF rule as above. **Skip incomplete masters** (listed). CSD → channel `csd` for existing vehicles. No on-road. | `PriceListPricingImporter` (fixed WEF branch, history), `PriceService` |
| **5 Add-ons & discounts** | **Export `Addon-N-Discounts.xlsx`** with exactly the reference sheets:<br>• Dealer Charges - Segment Wise (wide);<br>• RSA (tenure columns);<br>• Shield (OEM model + pack + transmission + fuel, 2 schemes);<br>• Exchange (long, Scheme);<br>• Corporate (long, Category).<br>Every applicable group row appears (from masters × stored rows); blank where nothing is stored. Checkboxes default ON (RSA, Shield, Corporate, Exchange, Dealer Charges) choose which sheets are exported / imported.<br>**Re-import** + WEF: per selected group, expire previous and insert new; blank = skip, 0 = explicit.<br>A per-sheet result table is shown. | `AddonDiscountExportService` / `ImportService` (job), `Addons\*Service` |
| **6 Insurance & RTO** | Two standalone workbooks, each with presence stats:<br>• **no rules in DB → import required**;<br>• rules exist → **Keep** or **Download current → edit → re-import**.<br>**Insurance workbook** (sheets as the reference): Insurance Co. (model + permit → companies), Insu Premium (scope, IDV basis, OD factor, TP heads, 17 add-ons incl. `=x/Inv` factors and flat amounts), Rules (permit map), plus exported IDV-slot and add-on-rate columns so a round-trip is lossless.<br>**RTO workbook**: every column of the reference sheet (permit, wheels, reg type, body type, GVW, seater, fuel, CC, assessable band, tax factor / slab incl. the BH formula text, surcharge, all fee heads).<br>Import expires by **kind** (never another kind's rows). | `InsuranceWorkbookService`, `RtoWorkbookService` (split from `RulesWorkbookService`), `Rules\*Service` (+ add-on-rate importer, GAP-01) |
| **7 Impact summary** | Always, before calc, from the session change log:<br>• new vehicles, masters completed, prices changed (up / down / new), add-on groups replaced, rules replaced;<br>• **N complete vehicles will calculate**;<br>• incomplete vehicles listed and skipped;<br>• held lists shown. | `PricingImpactService` |
| **8 Hold check** | Hold / keep / reopen the selected lists (PV, CV, BEV, LMM, LMM TZU, CSD, TAXI, ALL). | `PricingHoldService` |
| **9 Calculate & publish** | Batch job per complete vehicle (skips held lists). Per vehicle, per applicable permit (vehicle permit; taxi → PRIVATE + PASSENGER) × VIN type (NV, OV) × channel (normal; csd when a CSD price exists), writes a **snapshot**. The build runs the component services below. | `CalculatePricingSessionJob`, `RecalculateVehiclePricingJob`, `PricingEngineService::build()` |
| **10 Summary + reopen** | Counts (published / skipped incomplete / skipped held / failed), failures with reasons, **Retry failed**. **Mark complete** (+ reopen selected held lists) → session `completed`, gate released. | `summary.blade`, `PricingSessionService::complete()` |
| **11 getPricing** | Serves the **published snapshot** for `(vehicle, permit, vin_type, channel)`.<br>• Vehicle resolved from oem_code, display name or hierarchy.<br>• Response = fixed-key JSON: every key always present, unused amounts 0, lists `[]`; `options` apply selections (RSA years, shield scheme, insurance combo, corporate / exchange) on top of the snapshot.<br>• Held list → `hold = true` (+ HTTP 423 on the API). | `PricingQueryService::getPricing()`, `Api/V1/Vehicle/Pricing/PricingController` (routed), `admin/pricing/quote` JSON |

**The build components used in step 9:**
- **Price:** ex-showroom / assessable / GST / NV-OV schemes.
- **Dealer charges:** wide heads, most-specific row wins (model > segment + permit > ANY). Fixes BUG-178.
- **RSA / Shield:** all options, defaults (first paid RSA year, Shield scheme 1).
- **Discounts:** cash / accessory / shield + OEM / dealer schemes from the price row; corporate / exchange options.
- **Insurance:** every company × plan for the mapped insurance permit, each add-on premium, default combo
  Base + NilDep + Consumables (`frozen`), GST (OD 18%, Goods TP 6% from config).
- **RTO:** most-specific matching rule on permit, wheels, reg type, body type, GVW, seater, fuel, CC and assessable band,
  plus the full bifurcation.
- **TCS:** from config.
- **Accessories:** `AccessoryService` packs.
- **On-road** NV / OV; `withheld` = 0.

### Single completeness rule — `App\Services\Vehicle\VehicleCompleteness`
- **Always required:** Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST%,
  Permit, Taxi Price, Custom Model, Custom Variant, Display Name, Colour Name.
- **Conditional:** Private + ICE → CC; Private + EV → Motor; Goods → GVW; Passenger / MISC → none.
- **Used by:** Detect, Vehicle Info import, `VariantService` (**blocks `is_active = 1` when incomplete** — the variant screen
  can no longer bypass it), calculate and price import.
- **The profile flag becomes derived** — refreshed from the rule, never set independently.
- **`VEHICLE_STATUS` key-value** gains INCOMPLETE and DISCONTINUED through `KeyvalueService` (migration).
- **DB defaults** `is_active = 1` and `wheels = 4` stop pre-satisfying the gate: stubs are written explicitly.

### Fixed-key contract v2 (`PricingJsonContract`)
Redefined once, documented key by key in the guide; builder and snapshot must both round-trip through `normalize()`,
which fills missing keys.

| Group | Keys |
|---|---|
| Identity | oem_code, display_name, segment, sub_segment, model_code, variant_code, colour, permit, fuel, channel, vin_type, wef_date, taxi_price, source (`snapshot` / `live`), published_at |
| Price | ex_showroom, assessable_value, gst_percent, gst_amount, mm_invoice, dealer_margin |
| `dealer_charges` | incidental, fasttag, trc, rto_tape, cod, kazam, other, total, lines[] |
| `rsa`, `shield` | selected + options[] (item shape defined) |
| `discounts` | oem_scheme, dealer_cont, cash, accessory, shield, rsa, total_consumer_scheme, corporate{…options[]}, exchange{…options[]} |
| `insurance` | insu_permit, default{company, plan, addons[], total, frozen}, companies[]{company, plans[]{plan, idv[], od, tp, gst, base_total, addons[]{code, name, premium, default}}} |
| `rto` | rto_permit, rule_id, tax, surcharge, hypothecation, green_tax, registration, dtc, fitness, penalty, trc, rto_tape, other, total, bifurcation[] |
| Totals | `accessories`{packs[], total, discount}, `tcs`{limit, rate, applicable, amount}, invoice_value, on_road, `withheld`{rsa, shield, accessories, total}, hold, incomplete, errors[] |

### Schema changes (local migrations, guarded, reversible; backup of the pricing tables first)
- **snapshots:** add `permit` (+ `insu_permit`, `rto_permit`); unique key → `(model_code, channel, vin_type, permit, wef_date)`.
- **new `xlr8_vehicle_pricing_session_changes`** (the discard log).
- **import_sessions:** `progress` JSON, `hold_lists` JSON, `published_at`, `completed_at` / `completed_by`.
- **new `xlr8_vehicle_pricing_permit_map`:** vehicle permit + wheels + fuel → rto_permit, insu_permit. Seeded from the
  reference Rules sheet; editable through the insurance workbook.
- **History tables:** start being written. Unused `_draft` / `_csd` / `affected` are dropped only with approval (listed
  in DEC-073, not dropped).
- **RTO rules:** the columns already exist; `seater` is added to `RtoRuleService` fields.
- **Holds:** `list_code` normalised (PV, CV, BEV, LMM, LMM_TZU, CSD, TAXI, ALL) + `session_id`.

### Consumers and hygiene
- **Quotation (last phase):**
  - `QuotationCrudController` + `create.blade` read `getPricing` (same UI; mock removed; keys mapped once in a small
    adapter).
  - Hold → "prices on hold" banner and create blocked.
  - `store()` re-validates the discount gate and TCS server-side and stores the full snapshot in `standard_data`.
  - Booking create checks holds too.
- **Menu:** a "Pricing" dropdown (Workflow, Hold, TCS, RTO rules, Insurance, Price lookup), each item gated by `PRC_*`
  (resolves D15 / BUG-069).
- **`pricing/reset`:** becomes POST + confirm, `PRC_RESET_MANAGE` only, local environments only.
- **Removed (dead):**
  - `ProcessPricingWorkbookJob`;
  - the unused `pricing.*` config keys;
  - the "run_prices_now" dead checkbox;
  - the old `rulesImport` path.
- **Data scoping:** pricing masters stay unscoped (DEC-071 decision 2); `getPricing` is not scoped.

## Phases (each its own commit with tests, changelog, guide sync; one DEC-073 entry up front)
1. **Foundations:**
   - DEC-073 + spec §3.3 amendment;
   - migrations (snapshot permit key, session changes log, session columns, permit map, hold list_code);
   - key-value statuses;
   - `VehicleCompleteness` + `VariantService` gate;
   - `PricingWorkbookReader`;
   - `PricingStage` / session service with the change log and exact discard.
2. **Steps 0–2:** gate / start / hold-at-start, streaming detect with stubs (TZU / CSD rules), job + progress UI.
3. **Step 3:** Vehicle Info export (fixed lookups, all vehicles, sorting) and import job with the summary.
4. **Step 4:** price import (WEF branch fix, history, duplicates, skip incomplete, CSD channel).
5. **Step 5:** add-ons & discounts export / import in the reference shapes; BUG-178 fixed in the engine.
6. **Step 6:** insurance and RTO standalone workbooks (lossless round-trip, add-on rates, permit map); RTO matcher on all
   columns.
7. **Steps 7–8:** impact summary + hold check (`PricingHoldService`, TAXI / list scopes).
8. **Step 9:** contract v2 + builder (per-permit, NV / OV, insurance combos, TCS rate fix, accessories in on-road) +
   calculate batch.
9. **Step 10:** summary, retry failed, complete + reopen.
10. **Step 11:** `PricingQueryService::getPricing` from snapshots, API route (Sanctum), admin price-lookup screen.
11. **Quotation rewire:** getPricing adapter, hold enforcement on quote / booking, server-side gate + TCS re-validation;
    menu; reset hardening; dead code removal.
12. **Wrap-up:**
    - guides `docs/domains/pricing.md` (rewritten), `vehicle.md`, `crm-enquiry-quotation.md`;
    - `.ai/rules/modules/vehicle-pricing.md`;
    - tracker (BUG-178, 069, 146, new findings logged then closed);
    - full suite + smoke;
    - ask before push / merge (stage merge is on hold pending your talk with Krishan).

## Critical files
- **Services:** `app/Services/Vehicle/Pricing/**` (Session/, Import/, Workbooks/, Engine, Contract, Query, Hold, Impact),
  `app/Services/Vehicle/VehicleService.php`, new `VehicleCompleteness.php`, `app/Services/Vehicle/Entities/VariantService.php`
  (or its current path), `AccessoryService`.
- **Jobs:** `app/Jobs/Vehicle/Pricing/*`.
- **Controllers:** `app/Http/Controllers/Admin/Pricing/*` (the workflow controller split per step),
  `Api/V1/Vehicle/Pricing/PricingController`, `QuotationCrudController`, `BookingCrudController` (hold check).
- **Routes and views:** `routes/backpack/pricing.php`, `routes/api.php`; `resources/views/admin/pricing/**`,
  `admin/sales/quotation/create.blade.php`, `menu_items.blade.php`.
- **Config:** `config/pricing.php`.
- **Reuse:** entity services `Prices\PriceService`, `Addons\*`, `Rules\*`; `SheetHeaderService`, `SynonymService`,
  `KeywordValueService` / `KeyvalueService`, `AccessoryService::listForVehicle`, `Bus::batch` pattern,
  `PricingProcessLogger`, `Result`.

## Verification
- **Fixture workbooks:** small copies of the reference files under `tests/Fixtures/pricing/`, built from real rows,
  including PV / CV / BEV / LMM / TZU / CSD, duplicates, Indian numbers and a taxi PV.
- **Feature tests per step:**
  - gate / resume / discard (exact rollback);
  - detect stubs (TZU `NA`, CSD skipped);
  - completeness matrix (Private ICE / EV, Goods, Passenger, MISC; Active refused when incomplete, also via
    `VariantService`);
  - Vehicle Info round-trip (lookups not blank);
  - price WEF branches + history;
  - add-on blank vs 0;
  - rules round-trip lossless;
  - impact counts;
  - holds (TAXI, lists);
  - publish: taxi → PRIVATE + PASSENGER snapshots with RTO Taxi / insurance Passenger, NV ≠ OV where the OV block
    exists, dealer charges, TCS rate, accessories in on-road;
  - summary / complete / reopen;
  - `getPricing` fixed keys (every key present, zeros / empty lists);
  - API 423 on hold;
  - quotation uses published prices and rejects a broken gate.
- **End-to-end on `xlrm_testing`** with the real reference workbooks: every step through the UI, as a `PRC_WKFL_MANAGE`
  user and a view-only user.
- **Numbers:** spot-check on-road for 3 real vehicles (PV taxi, BEV, LMM) against a hand calculation from the workbooks.
- **Gates:** full suite, `--group=smoke`, screenshots at 1366 / 390, light and dark.
