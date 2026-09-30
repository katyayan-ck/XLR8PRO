# Pricing — the price-list pipeline, rule sets and the on-road pricing engine

Turns OEM price lists plus dealer add-ons, discounts, insurance, RTO and TCS rules into one **fixed-key pricing JSON**
per variant (OEM code), channel and VIN type. Spec: `tech-guides/frs-and-workflows/frs/pricing-machine-spec-v3.1.md`
(locked); human guide next to it; pitfalls in `.ai/rules/modules/vehicle-pricing.md` — **read the pitfalls before
touching importers**.

| Need | Use |
|---|---|
| on-road price for a variant (quotation, booking, API) | `app(PricingQueryService::class)->getPricing($oemCode, ['permit' => 'PRIVATE', 'vin_type' => 'NV', 'channel' => 'normal'])` — the published snapshot (step 11, below) |
| publish prices after an import | **Pricing → Pricing Process** step 9 (`Engine\PricingCalculationService`, below) |
| drive the import process | `Session\PricingSessionService` + the step jobs (admin **Pricing → Pricing Process**) |
| read-only price lists for staff | **Price List** menu (`Engine\PriceListService`, DEC-081) |
| write a rule / add-on / price row | the entity services under `Pricing\Rules\*`, `Pricing\Addons\*`, `Pricing\Prices\PriceService` (DEC-056/057/058) |
| a single calculator (admin test screens) | `RtoService::quote()`, `InsuranceService::quote()`; the engine uses `Engine\RtoCalculator` / `Engine\InsuranceCalculator` |
| quotation prices, gate + TCS re-validation | `App\Services\Sales\Quotation\QuotationPricingService` ([crm-enquiry-quotation.md](crm-enquiry-quotation.md)) |

---

The legacy `PricingEngineService`, `PricingJsonContract` and `TcsService` were removed in Phase 11 (DEC-082); the
contract is `Engine\PricingContract` v2 and consumers read published snapshots through `getPricing`.

## Calculators
| Service | Method | Returns |
|---|---|---|
| `RtoService` | `quote(array $ctx)` / `calculate($modelOrCtx, $options)` | `['rule_id', 'permit', 'selected_permit', 'permit_options', 'tax', 'tax_basis', 'surcharge', 'surcharge_formula', 'registration_fee', 'hypothecation', 'green_tax', 'rto_tape', 'fitness', 'duplicate_tax_card', 'penalty', 'total', 'bifurcation', …]` — best-matching `RtoRule` (`findBestMatch`) |
| `InsuranceService` | `quote(array $ctx)` / `calculate($modelOrCtx, $options)` | `['companies', 'default_company', 'default_plan', 'selected', 'idv_sum', 'od', 'tp', 'nildep', 'consumables', 'addons', 'standard_combo', 'standard_total', 'selected_total', …]` from `InsDefault` (company order), `InsBaseRule::findBestMatch()`, IDV slots and add-on rates |
`$ctx` keys used by the engine: `segment`, `model`, `variant`, `permit`, `fuel`, `wheels`, `cc`, `gvw`, `seating`,
`ex_showroom`, `invoice`.

---

## DEC-073 process engine (plan `DEC-073 in docs/decisions/decision-log.md`; all phases shipped)
| Piece | API |
|---|---|
| `Session\PricingStage` (enum) | `Started → Detecting → VehicleInfo → Prices → Addons → Rules → Impact → HoldCheck → Calculating → Summary`, terminal `Completed` / `Discarded`; `label()`, `order()`, `isTerminal()`, `steps()`, `fromStored($legacy)`; `ImportSession::stage()` returns it |
| `Session\PricingSessionService` | `gate(): ?ImportSession` (the one open process) · `start(UploadedFile, $sheets, $wef, $holdLists = [], ?$userId)` → Result (`ALREADY_ACTIVE`, `NO_SHEETS`; stores the upload under `storage/app/pricing/{id}/`) · `storeUpload($s, $file, $kind)` / `uploadAbsolutePath($s, $path)` · `record($s, fn)` (run writes that Discard can undo) · `advance($s, PricingStage, $stats)` (forward-only, except back to Vehicle Info) · `progress($s, [...])` · `putStats($s, $key, $value)` (replaces one stats section, e.g. this round's `vehicle_info`) · `markPublished($s)` · `discard($s)` → Result (`PUBLISHED` after publish; undoes exactly the session's changes) · `complete($s, $reopenLists)` → Result (`NOT_READY` before Summary) |
| `Session\PricingChangeRecorder` | `within($sessionId, fn)`, `active()`, `created/updated/softDeleted(Model)`, `captureBulk($query, $columns)` (called by `ExpiresActiveRows::expireActive()`), `rollback($sessionId)`, const `MODELS` (the recorded models; the observer is registered on them) — log model `Vehicle\Pricing\SessionChange` (`xlr8_vehicle_pricing_session_changes`, `before` cast to an array); inserted rows are removed, updated / expired rows restored through each model's base query (no events / stamps), DEC-093 |
| `Session\PricingChangeObserver` | model observer on the pricing, rule, add-on, snapshot, hold, profile and vehicle master models (records only inside `within()`) |
| `PricingHoldService` | `LISTS` (ALL, PV, CV, BEV, LMM, LMM_TZU, CSD, TAXI) · `hold($lists, ?$session, ?$reason, ?$userId)` · `reopen($lists, …)` · `heldLists()` · `isHeld($list, $channel = 'normal', ?$permit, $taxi = false)` (CSD channel and taxi Passenger snapshots included) |
| `Import\PricingWorkbookReader` | `sheetNames($path)` (no cells loaded) · `rows($path, $sheet, $fromRow = 1, ?$maxCol)` (generator `rowNo => cells`, one sheet, columns ≤ BJ, 250-row chunks, formulas → saved value) · `header($path, $sheet, $sheetCode)` → `['row', 'map', 'cells']` · statics `number()` ("3,00,752", "-"), `percent()` (0.4 → 40), `yesNo()` (Y/N/YES/NO), `text()`, `code()` |
| permit map | `PermitMap` / `Rules\PermitMapService::resolve($permit, $wheels)` (vehicle permit + wheels → RTO rule permit, insurance permit): 4W Passenger (taxi) → RTO "Taxi" / insurance "Passenger"; MISC → "Ambulance" / "Misc"; edited through the Insurance workbook's "Permit Map" sheet |
| snapshots | unique key `(model_code, channel, vin_type, permit, wef_date)` + `rto_permit`, `insu_permit` |
| `Import\PriceListDetectService` | step 2. `detect($path, $sheetTitles, ?$onProgress)` → per sheet `rows, known, created, csd_unknown, duplicates, blank_code, errors[], new_codes[], csd_unknown_codes[]` (lists capped at 500). Known = a variant with the full OEM code; new → `VehicleService::createStubFromPriceList()` (INCOMPLETE; LMM TZU colour `NA`); CSD never creates vehicles; a blank OEM Model is an error, not a stub. Statics `sheetCode($title)` ("Price List LMM TZU" → `PRICE_LIST_LMM_TZU`), `matchSheets($titles, $lists)` → `['found' => [list => title], 'missing' => [list]]`. `legacyCodeCount()` → variant rows whose `code` lacks the colour suffix (BUG-199; the Start screen warns — purge and re-import first, DEC-074). Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\DetectPriceListsJob($sessionId)` | timeout 1800, tries 1. Runs detect inside `record()`, writes `progress` (`step, state running/done/failed, message, error`), then `advance(VehicleInfo, ['detect' => ['sheets' => …, 'totals' => [created, known, csd_unknown, duplicates, errors]]])`. Real Pricing.xlsx (6 lists, 4,862 codes): 3,414 stubs in ~5 min, 98 MB |
| screens (`Admin\Pricing\Process\PricingProcessController`) | `pricing.workflow.index` (gate: Resume / Discard, stepper, Detect report; `PRC_WKFL_VIEW`) · `pricing.workflow.start-form` / `pricing.workflow.start` (POST `file` .xlsx ≤ 20 MB, `lists[]` of PV/CV/BEV/LMM/LMM_TZU/CSD — each must exist in the workbook, `wef_date` required, `hold_lists[]` optional; `PRC_WKFL_MANAGE`) · `pricing.workflow.status/{id}` (JSON `stage, stage_label, terminal, progress, totals`; polled while detecting) · `pricing.workflow.discard` (POST; before publish only). Labels in `lang/en/pricing.php` |
| `Import\VehicleInfoWorkbookService` | step 3. `COLUMNS` (reference Vehicle Info layout + `Missing Fields`) · `export($path)` → `['rows', 'incomplete']`: every variant (all statuses), sorted Segment → OEM Model → code; Fuel / Permit / Body Make / Body Type / Status written as key-value codes; incomplete rows highlighted · `import($path, ?$onProgress)` → `rows, completed, newly_completed, incomplete, rejected, unknown, issues[] {row, code, result incomplete/rejected, reason}` (issues capped at 5,000): each row through `VehicleService::applyVehicleInfo()`; blank cells keep the stored value; OEM Model / Variant are not imported; unknown codes are rejected (vehicles come only from Detect); unknown lookup values reject the row. Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\ImportVehicleInfoJob($sessionId, $uploadPath)` | timeout 1800, tries 1. Import inside `record()`; `putStats('vehicle_info', ['round', 'at'] + result)`; progress `step vehicle_info`. The session stays at Vehicle Info (export → fix → re-import loop) |
| screens (`Admin\Pricing\Process\VehicleInfoController`) | `pricing.workflow.vehicle-info-form` (status counts, download, upload, this round's summary + first 200 issues) · `vehicle-info-export/{id}` (download) · `vehicle-info-import` (POST `file`; queues the job; after a later step it sends the process back to Vehicle Info; refused after publish or while a step runs) · `vehicle-info-issues/{id}` (issues workbook) · `vehicle-info-continue` (POST → stage Prices). VIEW / MANAGE as above |
| `Import\PriceListImportService` | step 4. `import($path, $sheetTitles, $wef, ?$onProgress)` → `sheets[title] {rows, inserted, updated, unchanged, skipped_incomplete, skipped_unknown, no_price, duplicates, conflicts, rejected}`, `totals`, `issues[] {sheet, code, reason}` (≤ 2,000). Columns from the registry (DEC-076: ex-showroom PV/CV/BEV "Ex-Showroom Price ORG", LMM "Ex Showroom Price(Org)", TZU "Final Transaction Price", CSD "CSD Final Price"; schemes "with GST"; LMM assessable + freight, VIN Scheme; dealer margin + handling; GST% derived from amount / assessable when absent). `OV_LISTS` PV / CV / BEV (PV: the repeated label block is OV). WEF: same → update; newer + material change → expire + insert; unchanged → keep; older than the live WEF → rejected. Skips incomplete / unknown codes (CSD never creates); conflicting duplicate codes rejected. One `PricingHistory` row per code. Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\ImportPricesJob($sessionId, $uploadPath, $sheets, $wef)` | timeout 1800, tries 1. Import inside `record()`; `putStats('prices', [run, at, wef, upload] + result)`; progress `step prices` |
| screens (`Admin\Pricing\Process\PricesController`) | `pricing.workflow.prices-form` (the Start workbook or an updated one, lists, WEF; per-list result + issues) · `prices` (POST `source` session/upload, `file`, `lists[]`, `wef_date`; each list must be in the workbook; queued) · `prices-issues/{id}` · `prices-continue` (needs a run → stage Add-ons) |
| `Import\AddonDiscountWorkbookService` | step 5. `SHEETS` (group → reference sheet title), `HEADERS`, `EXCHANGE_SCHEMES`, `CORPORATE_CATEGORIES`, `SHIELD_SEGMENTS` · static `groupOf($title)` · `presence()` → live rows per group · `export($path, $groups)` → rows per group (every segment / model / scheme / category; blank where nothing stored; dealer charges split by permit where a segment sells under more than one) · `import($path, $groups, $wef, ?$onProgress)` → `sheets[group] {rows, written, blank, duplicates, rejected, expired}`, `issues[] {sheet, row, reason}`: per ticked sheet one transaction = expire the group at the WEF + insert through `DealerChargeService` / `AddonService` / `DiscountService` + history; blank = no rule, 0 = zero rule; model names → codes (`VehicleService::findModel`), "Any" = all, unknown model / conflicting duplicate scope rejected. Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\ImportAddonsJob($sessionId, $uploadPath, $groups, $wef)` | timeout 1800, tries 1. Import inside `record()`; `putStats('addons', [run, at, wef, groups] + result)`; progress `step addons` |
| screens (`Admin\Pricing\Process\AddonsController`) | `pricing.workflow.addons-form` (live rows per group, download with ticked sheets, upload + ticked groups + WEF, result + issues) · `addons-export/{id}?groups[]=` · `addons` (POST `file`, `groups[]`, `wef_date`; queued) · `addons-issues/{id}` · `addons-continue` (a run or stored rows → stage Rules) |
| `resources/views/admin/pricing/process/_progress.blade.php` | shared running-step card: `@include('admin.pricing.process._progress', ['session' => $s, 'step' => 'prices'])` — shows the job message / error, polls `pricing.workflow.status` every 2 s while `progress.state = running`, reloads when done |
| `Rules\RuleRange` | `RuleRange::parse($text)` (ANY / "0-3000" / "1 to 7" / ">1500" / "Above 2000000" / "< 30KW" / "4"; units ignored; throws on anything else) → `contains($value)`, `isAny()`, `isInverted()` (lower > upper — can never match). Shared by the rule imports and the engine (DEC-078) |
| `Rules\RuleFormula` | `evaluate($formula, $vars = [])` → float; `variables($formula)` → list (validates); `isNumber($v)`. Numbers, `%` (÷100), + − × (x, *, of, per) ÷, parentheses, variables `VARIABLES` (OD LPG SEAT IDV INVOICE TAX ESR TP; "Setat" = SEAT). No `eval()`; throws `InvalidArgumentException` with the reason. E.g. `evaluate("12.5% of Tax", ["TAX" => 10000])` = 1250 |
| `Import\RtoWorkbookService` | step 6. `SHEET`, `COLUMNS` (reference order) · `presence()` · `export($path)` → rows (exactly as written) · `import($path, $wef, ?$onProgress)` → `rows, written, blank, duplicates, rejected, expired, issues[]`: one transaction = expire the live RTO rules + insert through `RtoRuleService`; ranges and formulas validated (unparseable → rejected; inverted → imported with a warning); conflicting duplicate scopes rejected. Run inside `record()` |
| `Import\InsuranceWorkbookService` | step 6. `SHEETS` (companies "Insurance Co.", premium "Insu Premium", permit_map "Permit Map"), `KEY_COLUMNS`, `HEADS`, `ADDONS` · `presence()` → `[companies, premium, permit_map]` · `export($path)` → rows per sheet (plus blank company rows for every model × insurance permit without one) · `import($path, $wef, ?$onProgress)` → `sheets[part] {rows, written, blank, duplicates, rejected, expired}`, `issues[]`: permit map, then companies (model names → codes, Co. 1 = default), then premium (base rule + IDV slots + add-on rates, heads kept as written), each in one transaction. Reads the reference layout (typo labels aliased, rightmost "IDV n" = the basis, calculator columns and the "Rules" sheet ignored). Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\ImportRulesJob($sessionId, $kind, $uploadPath, $wef)` | `KINDS` insurance / rto; timeout 1800, tries 1; import inside `record()`; `putStats('rules', [kind => [run, at, wef] + result])`; progress `step rules` |
| screens (`Admin\Pricing\Process\RulesController`) | `pricing.workflow.rules-form` (per kind: stored / none — import required, download current, upload + WEF, result + issues) · `rules-export/{kind}/{id}` · `rules` (POST `kind`, `file`, `wef_date`; queued) · `rules-issues/{kind}/{id}` · `rules-continue` (both kinds stored → stage Impact) |
| `Session\PricingIssueStore` | Row issues of a step, and detect's code lists, live in `storage/app/pricing/{session}/{step}-issues.json`, not in the session `stats`. Thousands of issues rewritten inside the session row exhausted MySQL memory (DEC-080).<br>• `split($session, $step, $result)` writes the file and returns the result with `issues_count` + `issues` (the first `PREVIEW` = 20, for the screen).<br>• `all($session, $step)` returns every issue (for downloads).<br>• `put($session, $name, $data)` stores any list.<br>Steps: `vehicle_info`, `prices`, `addons`, `rules-insurance`, `rules-rto`, `detect-codes`. |
| `Session\PricingImpactService` | step 7. `LISTS` · `summary($session)` → `vehicles {new, activated}` (from the change log; activated includes vehicles detected and completed in this process), `prices {normal|csd: {new, up, down, other}, unchanged}` (a row inserted this session vs the row it expired, or a same-WEF update), `addons {group: {written, replaced} \| {kept}}`, `rules {insurance|rto: run \| {kept}}`, `calculate {lists {PV…TAXI: {vehicles, held}}, vehicles, held, snapshots}` (Active vehicles with a live price per `price_list`; TAXI = `taxi_price YES`), `skipped {incomplete, inactive, no_price}` · `incomplete()` → `[[code, oem variant, segment, missing]]` |
| screens (`Admin\Pricing\Process\ImpactController`) | `pricing.workflow.impact-summary-view/{id}` (step 7 summary; at stage HoldCheck also the hold form and Calculate & publish) · `impact-incomplete/{id}` (download) · `impact-continue` (POST → stage HoldCheck, stamps `stats.impact.reviewed_at/by`) · `hold-check` (POST `action` hold/reopen, `hold_lists[]`; through `PricingHoldService` inside `record()`, so Discard undoes it) |

## DEC-080 calculation engine (`App\Services\Vehicle\Pricing\Engine\*`) — step 9 and the snapshots
| Piece | API / rule |
|---|---|
| `PricingContract` (v2) | `VERSION = 2`; `defaults()` holds every key:<br>• identity and price;<br>• `dealer_charges` (with `cod_in_total`), `rsa`, `shield`, `accessories`;<br>• `discounts` (with `consumer_scheme`, the eligibility ratios and the `exchange` / `corporate` options);<br>• `insurance` (`default`, `companies[]`);<br>• `rto` (with `outside_state_trc` and `options[]` for BH);<br>• `tcs`, `gross`, `invoice_value`, `on_road`, `withheld`, `hold`, `errors`.<br>`normalize($payload)` fills missing keys and drops nothing. |
| `VehicleFacts::of($variant, $codes)` | Codes and names up the hierarchy; permit / fuel / body codes (`keyvalueCodes()`); wheels, seating, GVW, CC, motor kW (">65KW" → 65.5), transmission, shield pack, taxi. Helpers: `isElectric()`, `hasGasKit()`, `isGoods()`, `fuelFor($synonyms)`. |
| `ScopeMatcher` | Spec §6. `best($rows, $tokens, $ranges)` / `all()` / `score()`.<br>• Blank / ANY = all; comma = union; case-insensitive; ranges via `RuleRange`.<br>• The most specific row wins; ties go to the later row. |
| `RuleBook` | Every live rule row, loaded once per chunk: dealer charges, RSA, Shield, exchange, corporate, RTO, insurance (+ IDV slots + add-on rates), insurance defaults, permit map, TCS.<br>Settings: `odDiscountPct`, `insuranceGstPct`, `goodsTpGstPct`, `roundUpTo`, `includeCod`.<br>`permits($vehiclePermit, $wheels)`, `canonical($type, $value)`. |
| `ComponentResolver` | • `dealerCharges($v, $permit)` — COD is in the total only when `pricing.dealer_charges.include_cod` is on.<br>• `rsa($v)` — default = the first paid 1-year option.<br>• `shield($v)` — scheme 1.<br>• `discountOptions($v, 'EXCHANGE' / 'CORPORATE')`. |
| `RtoCalculator::calculate($v, $rtoPermit, $ex, $assessable, $margin)` | • Rule: the most specific match on permit, wheels, Regular / BH, body, fuel, GVW, seater, CC and assessable band.<br>• Tax = rounded-up ESR (or the BH base) × factor; "Fixed" = the amount; plus the surcharge formula and the fees.<br>• Outside-State TRC is shown but not added. BH goes under `options`. |
| `InsuranceCalculator::calculate($v, $insuPermit, $ex, $model)` | Every company (InsDefault order) × plan:<br>• IDV = ex × slot %; OD = Σ IDV × factor − 30%; CNG kit / IMT 23.<br>• TP heads, including seat formulas.<br>• Add-ons: `idv_rate` × IDV year 1, flat, or formula.<br>• GST 18% (Goods TP 12%).<br>Default = first company, first plan, NilDep + Consumables, `frozen`. A missing factor / heads / rates come from the sibling plan row. |
| `SnapshotBuilder::build($variant, $prices, $wef)` | One snapshot per permit × NV / OV × channel:<br>• permits: the vehicle's; taxi adds PASSENGER via the permit map (RTO Taxi / insurance Passenger);<br>• NV / OV use `curr_*` / `old_*`; channels are normal and csd.<br>• on_road = ex + charges + RSA + Shield + accessories (= accessory discount) + insurance + RTO + TCS − discounts.<br>• discounts = cash + accessory + Shield + RSA + consumer scheme.<br>• TCS = rate × (ex − discounts) when ex ≥ limit.<br>Throws `PricingFailure` when there is no RTO / insurance rule, no price or no permit. |
| `SnapshotPublisher::publish($variant, $snapshots, $wef, $sessionId)` | One transaction: upsert by (code, channel, VIN, permit, WEF), then expire every other live snapshot of the vehicle at the WEF. |
| `PricingCalculationService` | • `start($session)`: HoldCheck → Calculating; held lists recorded as skipped; `Bus::batch` of `CalculateVehiclesJob` (`CHUNK` = 100); `finally` → `finish()`.<br>• `calculate($session, $codes)`: build → publish → `CalcResult`; the first snapshot marks the session published; TAXI / CSD holds drop those snapshots.<br>• `finish($id)`: → Summary with the counts.<br>• `retryFailed($session)`, `counts($session)`. |
| `CalcResult` (`xlr8_vehicle_pricing_calc_results`) | Per session × vehicle: `published` (with the snapshot count), `failed` (with the reason) or `skipped` (held, not Active). |
| screens (`Admin\Pricing\Process\CalculateController`) | • `calculate-start` (POST, from the hold check).<br>• `summary/{id}`: progress with the batch %, counts per list, failures.<br>• `summary-results/{id}`: failed + skipped download.<br>• `retry-failed` (POST).<br>• `complete` (POST `reopen_lists[]`): → Completed, gate released.<br>`pricing.workflow.status/{id}` adds `batch {total, processed, failed, percent}`. |

## getPricing — step 11 (`App\Services\Vehicle\Pricing\Engine\PricingQueryService`)
Serves the **published snapshot**; nothing is recalculated from live rules. Selections are applied on top and the totals
recomputed with the snapshot's own formulas (`gross`, TCS on ex − discounts, `invoice_value`, `on_road`).

| Method | Returns |
|---|---|
| `getPricing(string $oemCode, array $options = []): Result` | ok `['pricing' => contract v2]` (`source = snapshot`) · fail `NOT_FOUND` · fail `ON_HOLD` with `data.pricing` (`hold = true`) |
| `apply(array $payload, array $options): array` | the selections applied to a normalized payload, totals recomputed; unknown selections go to `errors[]` and are not applied |

**Snapshot chosen:** `wef_date` ≤ the date (default today) and not expired by then; `vin_type` (default NV); `channel`
(default normal); `permit` (default the vehicle's own — a taxi-priced car's PRIVATE snapshot, not its PASSENGER one).
**Options:** `rsa_years` (0 = none), `shield_scheme` (0 = none), `insurance {company, plan, addons[]}` (re-priced
with the plan's frozen `od_gst_pct` / `tp_gst`; `frozen` stays true only for the default combo), `reg_type = BH`
(uses `rto.options[0]`), `outside_state` (adds `outside_state_trc`), `include_cod`, `exchange` (scheme),
`corporate` (category). **Hold:** `PricingHoldService::isHeld(price_list, channel, permit, taxi-extra)` — a TAXI hold
blocks only the extra PASSENGER price. `PricingContract::normalize()` casts numeric values to float where the default is
a float (MySQL JSON returns `1000000.0` as `1000000`).

```php
$r = app(PricingQueryService::class)->getPricing('AZ1116YGTTA4EA01BZ', ['rsa_years' => 2, 'exchange' => 'Scrappage']);
if (! $r->ok) { /* $r->code: NOT_FOUND | ON_HOLD */ }
$onRoad = $r->get('pricing')['on_road'];
```
- **API:** `GET /api/v1/vehicle/pricing/{oemCode}` (`auth:sanctum` + `validate_device`, name `api.vehicle.pricing.show`,
  `Api\V1\Vehicle\Pricing\PricingController::show`). The options go in the query string; the envelope has
  `data.pricing`; 404 `PRICING_NOT_FOUND`; 423 `PRICING_ON_HOLD` (with `data.pricing`).
- **Admin:** `admin/pricing/lookup` (`pricing.lookup`, `PRC_WKFL_VIEW`, `Admin\Pricing\PriceLookupController`) — the
  same options as a form, the build-up, discounts, RTO / insurance heads and the raw JSON.

## Automatic recalculation and the app sync stamp (DEC-083)
| Piece | API / rule |
|---|---|
| `Engine\PricingParamObserver` (on `PricingParamRegistry::all()`) | Saved / deleted / restored pricing parameters (prices, add-ons, discounts, dealer charges, RTO / insurance / TCS rules, permit map, insurance masters) and vehicle masters → `PricingRecalcService::mark()`, **unless a Pricing Process is recording** (step 9 recalculates everything). Vehicle masters and accessories bump the sync stamp; accessories never recalculate. |
| `Engine\PricingRecalcService` (singleton) | • `scopeOf($model)`: `{segment?, model?, variant?}` or `{all: true}` for rule sets, plus `wef` and `reason`.<br>• `mark($model)` adds it to the pending list (cache `pricing.recalc.pending`).<br>• `dispatchPending()` (request end, and after each queued job) queues one `RecalculateAffectedJob` (delay `DEBOUNCE_SECONDS` = 60, unique until processing).<br>• `affected($pending)` → `code => WEF` for Active vehicles with a live normal price; blank / ANY / ALL / `*` = no filter.<br>• `run()`: republishes those vehicles through `PricingCalculationService::publishVehicles()` at max(change WEF, today, the vehicle's latest live snapshot WEF), skipping held lists. It logs a `RecalcRun` (`xlr8_vehicle_pricing_recalc_runs`: status, reasons, counts, failures). It returns null while a process is open; `complete()` / `discard()` call `resumeAfterProcess()`. |
| `PricingCalculationService::publishVehicles($codes, $wefFor, ?$sessionId, $onResult)` | The vehicle loop shared by step 9 and the automatic runs (build → publish, one transaction per vehicle, held lists skipped). |
| `PricingSyncStamp` (singleton) | `touch()` (`SnapshotPublisher::publish()`, vehicle masters, accessories) → written once by `flush()` at request end / after each queued job → setting `pricing.last_updated_at`; `lastUpdated()`, `isPending()`. |

## Pricing masters — Admin → Pricing → Masters (DEC-083, `App\Support\PricingMaster\*`)
One kit serves every master: `MasterController` (routes `pricing.masters.{index,rows,create,store,edit,update,destroy,export,import,import-status}`, URI `admin/pricing/masters/{master}`), views `admin/pricing/masters/{index,form}`, queued `ImportPricingMasterJob` with a `MasterImport` row (`xlr8_pricing_master_imports`) for progress / result.

| `MasterDefinition` method | Meaning |
|---|---|
| `key()`, `label()`, `icon()`, `description()`, `permission()` | route key, titles, `{prefix}_VIEW` (list, export) / `{prefix}_MANAGE` (write, import) |
| `model()`, `service()`, `defaults()`, `query($history)` | the table, its entity service (the only write path), fixed attributes (e.g. `addon_type = RSA`), live rows (+ expired with `history`) |
| `columns()`, `row($model)`, `formFields()`, `input($field)` | grid / export columns; form fields with type / label / options / required derived from the service's `Field`s |
| `save($input, ?$existing)` | create; update (same WEF); new WEF → expire the stored row at it and insert the new version (every stored value carries forward) |
| `remove($model)` | WEF rows expire today (or at their WEF if later); others are soft-deleted |
| `export($path)` / `import($path, $wef, $progress)` | default: one row per record ("ID" first; an ID updates or versions that row, no ID creates); chunked, one transaction per chunk; rejected rows reported |
| `WorkbookGroupMaster` | Dealer Charges, RSA, Shield, Exchange, Corporate, Loyalty: export / import = that sheet of Addon-N-Discounts.xlsx (the process's own format); the import replaces the group at the WEF |

- **Masters** (`MasterRegistry::MASTERS`):
  - Dealer Charges (`PRC_DLRC`), Discounting Breakup (`PRC_DBRK`, the price rows' NV / OV scheme blocks), RSA
    (`PRC_RSA`), Shield (`PRC_SHLD`);
  - Corporate (`PRC_CORP`), Exchange (`PRC_EXCH`), Loyalty (`PRC_LYLT`).
  - RTO Rules (`PRC_RTOR`; import / export = the RTO workbook, replacing every live rule at the WEF).
  - Insurance Rules (`PRC_INSR`): one form for the base rule, its OD / TP heads (numbers or `RuleFormula` formulas),
    IDV slots 1–3 and a rate per add-on of the add-on master. Import / export = the "Insu Premium" sheet only
    (`InsuranceWorkbookService` `$parts`), so preferences are never touched (BUG-205).
  - Insurance Companies (`PRC_INCO`, new table `xlr8_vehicle_pricing_ins_companies`, `InsCompanyService`; seeded from
    the companies in use).
  - Insurance Preferences (`PRC_INPF`, `InsDefault` + new `segment` column): segment + permit (model ANY), or a model
    override. `InsuranceCalculator::companyOrder()` → the model's rows, else the segment's, else ANY.
  - Insurance Add-ons (`PRC_INAD`, new table `xlr8_vehicle_pricing_ins_addons`, `InsAddonService`): names + the
    default combo (`RuleBook::$defaultInsuranceAddons` / `$insuranceAddonNames`; fallback `InsuranceCalculator::DEFAULT_ADDONS`).
  - Accessories + Accessory Scopes (`PRC_ACCS`; `Accessories\AccessoryItemService` / `AccessoryScopeService`). Import /
    export = the typed sheets through `AccessoryService::importExcelWithSheetOrder()` (purge + reload). They never
    recalculate; they bump the sync stamp.
  - The old RTO / Insurance screens (`pricing.rto.*`, `pricing.insurance.*`) stay by URL for their test calculators;
    the menu links the masters.
- **Recalculation log:** `pricing.recalc-log` (`PRC_RCLC_VIEW`): the `RecalcRun` rows, the pending changes and the sync stamp.
- **Writes:**
  - Refused while a Pricing Process is open.
  - Every change reaches `PricingParamObserver` → automatic recalculation of the affected vehicles + the sync stamp.
- **Loyalty** (like Exchange):
  - `discount_type = LOYALTY` with a scheme name, OEM + dealer share.
  - `RuleBook::$loyalty`, `ComponentResolver::discountOptions($v, 'LOYALTY')`.
  - Contract `discounts.loyalty {selected, amount, options[{scheme, oem, dealer, total}]}`.
  - getPricing option `loyalty` (API + admin lookup).
  - Price List "Loyalty" conditional columns.
  - Quotation `deductibles.loyalty-scheme` (CN2, not auto-applied).
  - Addon-N-Discounts "Loyalty" sheet (registry rows copied from Exchange, migration `2026_09_29_192409`).

## Price List screens (DEC-081, `App\Services\Vehicle\Pricing\Engine\PriceListService`)
Read-only lists of the published NV prices at the default selections, laid out like the reference PDFs. They are open
to **every logged-in user** (no permission). The menu is **Price List**; the routes are `pricing.price-list.index`,
`pricing.price-list.show/{list}` and `pricing.price-list.rows/{list}` (JSON). The controller is
`Admin\Pricing\PriceListController`.

| Method | Returns |
|---|---|
| `rows(string $list, ?string $date = null): array` | `{list, label, date, hold, wef[], exchange[], corporate[], rows[]}`. Each row has `code, model, variant, colour, ex_showroom, incidental, fastag_trc, other_charges (+other_tip), rsa, shield, accessories, insurance (+insurance_tip), rto (+rto_tip), disc_consumer / cash / accessory / shield / rsa / total, exchange{scheme: amount}, corporate{category: amount}, tcs, invoice, on_road`. |
| `counts(?string $date = null): array<string, int>` | vehicles per list on the date (landing cards) |
| `LISTS` | `pv`, `taxi`, `cv`, `bev` (Electric), `lmm`, `tzu` (`LMM_TZU`), `csd` → label, hold code, channel, price list |

- **Which snapshots each list shows:** NV snapshots valid on the date.
  - PV / CV / BEV / LMM / TZU: the normal channel, that `price_list`, and `permit = vehicle_permit`.
  - Taxi: the extra PASSENGER snapshot (`permit ≠ vehicle_permit`).
  - CSD: the csd channel.
- **Snapshot columns:** snapshots carry `price_list` and `vehicle_permit` (migration `2026_09_29_014111`, filled by
  `SnapshotPublisher`), so a list is an indexed query.
- **Performance:**
  - Rows are read in chunks (`chunkById` 500) and projected.
  - They are cached per list × date × (latest `updated_at` + count), so a publish refreshes the list by itself.
  - The page loads the rows by AJAX into the AG Grid.
- **Grid:**
  - Pinned model and variant; on-road pinned on the right.
  - Hover break-ups for insurance, RTO and other charges.
  - Amount columns that are zero on every row are hidden.
  - Quick search and CSV download.
  - A held list shows an "On hold" banner.

## Shared import helpers
Sheet recognition: `Import\PriceListDetectService::sheetCode($title)`, `AddonDiscountWorkbookService::groupOf($title)`;
the Insurance / RTO workbooks match their sheet titles exactly (`SHEETS`, `RtoWorkbookService::SHEET`).
(`RulesWorkbookService`, `AddonDiscountImportService`, `PriceListVehicleDetector`, the legacy workflow controller / jobs /
session service and `PricingEngineService` were removed during DEC-073 phases 4–11.)

Header mapping for every sheet goes through `SheetHeaderService` (labels / aliases → stable `field_code`; registry labels and sheet cells are normalised the same way by the public `normalizeLabel()` — `-` `.` `_` → space, lower case; when several columns match one field the primary label beats its aliases, then the first column wins — BUG-200):
`labelMap($sheet)`, `mapHeaderRow($sheet, $cells)`, `findHeaderRow($sheet, $rows, $maxScan = 25)` (→ `[rowIndex,
fieldMap]`), `val($row, $map, $field, $default)`, `requiredFieldCodes($sheet)`, `headersForExport($sheet)`,
`forgetCache($sheet)`. Rows live in `SheetHeader` (`allLabels()`).

`PricingProcessLogger` (enabled by `config('pricing.process_log')` / `PRICING_PROCESS_LOG`): `forSession($id)`,
`info/warning/error/debug($msg, $ctx)`, `dumpSheetPreview(...)` → `pricing_process_session_N.log`.

`PricingResetService::run($afterDate, $flushQueue = true)` **destroys** pricing sessions, profiles, prices, history,
snapshots … after a date (keeps sheet headers, add-ons, discounts, rules) — **local only**; the admin route is a GET
preview + POST with the typed `RESET` (`pricing.reset` / `pricing.reset.run`, DEC-082).

---

## Rule, add-on and price entity services (the only writers, DEC-050/056-058)
All support `create / update / upsert / validate` (see [core.md](../architecture/core.md)); the rule and add-on services also have
`expireActive(string $wefDate, array $group = []): int` — expire every active row, or one group's
(`['addon_type' => 'RSA']` — RSA and Shield expire separately). **Never delete history; never seed zero-value ANY rows.**

| Service | Table | Notes |
|---|---|---|
| `Prices\PriceService` | `xlr8_vehicle_pricing` | one row per (OEM code, channel, WEF), key fixed once created; `expire($price, $wef)` closes a live price; `AMOUNTS`, `ELIGIBILITY` (`curr_acc_elg`, `curr_shield_elg`, `old_acc_elg`, `old_shield_elg` — ratios 0–1, 70 read as 70%, DEC-076); `price_list` = the list the row came from (PV, CV, BEV, LMM, LMM_TZU, CSD; set by the price import, DEC-079) |
| `Addons\AddonService` | `xlr8_vehicle_pricing_addons` | RSA / Shield; scope columns `segment`, `model_code` (ANY = all), `variant_code`, `permit`, `shield_pack`, `transmission`, `fuel`; `tenure_years`, `amount`, `oem_share`, `dealer_share`, `is_default` |
| `Addons\DealerChargeService` | `xlr8_vehicle_pricing_dealer_charges` | `segment` (ANY), `permit`, `model_code`; `incidental`, `fastag`, `trc`, `rto_tape`, `cod`, `kazam`, extra json — note BUG-178 (engine ignores WIDE charges / model scope); an all-zero row is allowed at a specific segment (explicit "no charges") but refused at ANY (DEC-077) |
| `Addons\DiscountService` | `xlr8_vehicle_pricing_discounts` | Exchange, Corporate …; total = sheet Total or OEM + dealer share |
| `Rules\RtoRuleService` | `xlr8_vehicle_pricing_rto_rules` | scope `permit`, `wheels` (`RuleFields::wheels()`: ANY → null), `reg_type`, `body_type`, `gvw_range`, `seater`, `fuel_type`, `cc_range`, `assessable_range` (DEC-078); `tax_basis` (text), `tax_slab` (as written, e.g. "(10% * 1.25 * 2) / 15"), `tax_factor` (its value), `surcharge` + `surcharge_formula` ("12.5% of Tax"), fees; `rto_tape` = Outside State TRC |
| `Rules\TcsConfigService` | `xlr8_vehicle_pricing_tcs_config` | at most one active row; `saveCurrent($input)` |
| `Rules\InsBaseRuleService` | `xlr8_vehicle_pricing_ins_base_rules` | plan `"1+3"` → `od_years` / `tp_years`; OD factor, TP amounts, `tp_pa_owner`; `heads` JSON = every OD / TP head as written (numbers or formulas such as "5% x OD", "1162 x (Seat -1)"; DEC-078) |
| `Rules\InsIdvSlotService` | `xlr8_vehicle_pricing_ins_idv_slots` | per base rule: `year_no`, `idv_basis` text, `idv_pct` |
| `Rules\InsDefaultService` | `xlr8_vehicle_pricing_ins_defaults` | companies per model + permit, priority 1 = default. `InsDefault::getCompanies($model, $permit)` → the model's companies by priority, else the ANY rows, `[]` when none (BUG-202 fixed) |
| `Rules\PermitMapService` (+ `PermitMap` model) | `xlr8_vehicle_pricing_permit_map` | `vehicle_permit`, `wheels` (ANY → null), `rto_permit`, `insu_permit`, `label`; `resolve($vehiclePermit, $wheels)` → the live row (a wheel-specific row beats a no-wheels row) |
| `Rules\InsAddonRateService` | `xlr8_vehicle_pricing_ins_addon_rates` | per `base_rule_id` (company / permit / band / plan row), `addon_slug` (NIL_DEP, CONSUMABLES, ENGINE, …), `rate_type` `idv_rate` (< 1, × IDV) / `flat` / `formula`, `rate_value` (4 dp) and `rate_text` (exact); written by the Insurance workbook import (GAP-01 closed) |

```php
$svc = app(AddonService::class);
DB::transaction(function () use ($svc, $rows, $wef) {
    $svc->expireActive($wef, ['addon_type' => 'RSA']);            // close the live RSA set
    foreach ($rows as $row) { $svc->create($row + ['addon_type' => 'RSA', 'wef_date' => $wef]); }
});
```

## Models (all `xlr8_vehicle_pricing_*`)
| Model | Holds | Helpers |
|---|---|---|
| `ImportSession` | workflow state, `selected_sheets`, `wef_date`, `hold_scopes`, `stats` | `STAGE_*`, `STATUS_*`; scope `active()`; `isTerminal()`, `isActiveProcess()`; `notes` is mirrored into `remarks` when that column exists (`setNotesAttribute` / `getNotesAttribute`) |
| `Pricing` / `PricingHistory` | live price rows / history | `Pricing`: `active()`, `forModel($code)`, `history()`; `static getActive($code, $channel)`. `PricingHistory` (BUG-201 fixed): `pricing_id, model_code, channel, wef_date, payload` (parsed sheet row), `action` = `ACTION_INSERT` / `ACTION_UPDATE` / `ACTION_UNCHANGED` — one row per imported code |
| `Profile` | per-variant readiness flags (`is_vehicle_master_complete`, `is_pricing_template_complete`, …) | `forModel()`, `publishable()`, `incomplete()`, `ofSegment()`; `canBeMarkedActive()` |
| `Snapshot` | published pricing JSON (`payload`) per code / channel / VIN type / WEF | `active()`, `forCode($oem)` |
| `Draft` | calculated but unpublished payloads | `forModel()` |
| `ChangeFlag` / `Affected` | what changed in this session / which variants to recalc | `unprocessed()`, `ofType()` / `pending()`, `forSession($id)` |
| `Hold` | price holds by scope | `held()`; `isHeld()`, `putOnHold()`, `reopen()` |
| `Csd` | CSD channel price rows | `active()`; `static getActive($modelCode)` |
| `Addon`, `DealerCharge`, `Discount` (+ `AddonHistory`, `DiscountHistory`) | add-ons, charges, discounts | `active()`, `ofType($type)`; the history models match their tables (`addon_id` / `discount_id`, `model_code`, `payload`, `action`; BUG-201) and are observed by the session change log |
| `RtoRule`, `TcsConfig`, `InsBaseRule` (+ `idvSlots()`), `InsIdvSlot` (`baseRule()`), `InsDefault`, `InsAddonRate` | rule sets | `findBestMatch($criteria)` (most specific wins), `TcsConfig::current()`, `InsDefault::getCompanies()`, `InsAddonRate::forCompanyPermit()` |
| `SheetHeader` | Excel header registry | `active()`, `forSheet()`, `ordered()`, `allLabels()` |

## Use cases
**Quotation / booking needs the on-road price** → `PricingQueryService::getPricing()` (the quotation goes through
`QuotationPricingService::forVehicle()`); never recompute pieces in a controller. The quotation stores what it showed in
`standard_data.pricing` — the published price may change after the next WEF.

**"Why is this variant not priced?"** → `app(VehicleService::class)->missingFields($variant)`, then
`Profile::forModel($code)->first()` flags, then `Hold::isHeld($scope)`.

**New rule set for October** → **Pricing → Pricing Process** (step 6, Insurance & RTO) or `RtoWorkbookService::import($path, $wef)` /
`InsuranceWorkbookService::import($path, $wef)` inside a session; old rows are expired, not deleted.

## Gotchas (from production incidents — see the rules file)
- Never load a whole workbook; one sheet at a time, capped columns, chunked rows.
- Same WEF → update the live row; different WEF → expire then insert. Group imports expire only their group.
- Scope match: ANY / blank / ALL / `*` = all; comma = union; the more specific row replaces the whole match.
- Calculate skips incomplete vehicles.
- Pricing admin needs `PRC_*` permissions. The reset (`pricing.reset`) is a GET preview + POST with the typed
  confirmation `RESET`, local environments only (DEC-082).

## Testing
Pricing tests need vehicle rows → `xlrm_testing`. Build a snapshot payload with `PricingContract::normalize()` and assert keys exist
(`array_keys(PricingContract::defaults())` ⊆ payload keys) and totals; for rule services assert `expireActive()`
counts and that history rows remain.

## Vehicle Info workbook — master dropdowns (owner request 30-09)
- **Export** (`VehicleInfoWorkbookService::export()` → `addDropdowns()`): a hidden `Lists` sheet with named ranges
  `LST_SEGMENT`, `LST_FUEL`, `LST_TRANSMISSION`, `LST_DRIVETRAIN`, `LST_BODY_MAKE`, `LST_BODY_TYPE`, `LST_PERMIT`,
  `LST_TAXI_PRICE`, `LST_STATUS` and one `SUB_<SEGMENT>` per segment; every lookup column has a stop-style list
  validation (codes), Sub Segment follows the row's Segment (`INDIRECT`), Seating 1–100, Wheels 1–30, GST% 0–100.
- **Import** calls `VehicleService::applyVehicleInfo($variant, $row, null, mastersMustExist: true)`: an unknown segment /
  sub-segment is rejected (never created from the sheet), transmission / drivetrain must be in their keyword masters
  (a code or label; stored in the variant service's format). Fuel / permit / body make / body type were already strict.
- `VehicleService::keywordOptions($keyword)` → one entry per distinct value (duplicates like `AUTOMATIC` / `AUTOMATIC_1`
  collapse — clean-up is to-do F6); `keywordCode($keyword, $value)` → the canonical code or null.
- Migration `2026_09_30_023856_add_awd_to_drivetrain_keyword` added `AWD` (used by existing vehicles) to `DRIVETRAIN`.
