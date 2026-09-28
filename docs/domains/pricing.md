# Pricing — the price-list pipeline, rule sets and the on-road pricing engine

Turns OEM price lists plus dealer add-ons, discounts, insurance, RTO and TCS rules into one **fixed-key pricing JSON**
per variant (OEM code), channel and VIN type. Spec: `docs/reference/pricing/Xceler8_Vehicle_Pricing_Machine_Spec_v3.1.md`
(locked); human guide next to it; pitfalls in `.ai/rules/modules/vehicle-pricing.md` — **read the pitfalls before
touching importers**.

| Need | Use |
|---|---|
| on-road price for a variant (quotation, booking, API) | `app(PricingEngineService::class)->getPricingPayload($oemCode, ['channel' => 'normal', 'vin_type' => 'nv'])` |
| publish prices after an import | `PricingEngineService::calculateAndPublish(...)` (run by `RecalculateVehiclePricingJob`) |
| drive the import workflow | `PricingSessionService` + the importers (admin **Pricing → Workflow** screens) |
| write a rule / add-on / price row | the entity services under `Pricing\Rules\*`, `Pricing\Addons\*`, `Pricing\Prices\PriceService` (DEC-056/057/058) |
| a single calculator | `TcsService::compute()`, `RtoService::quote()`, `InsuranceService::quote()` |

---

## The pricing JSON (`PricingJsonContract::empty()` shows every key)
Keys **never change**; unused ones are present as `0` / `null` (the UI hides what is missing / zero).
```text
oem_code, channel (normal|csd), vin_type (nv|ov), wef_date, segment, model_code, display_name, permit, fuel, taxi_price,
ex_showroom, assessable_value, gst_percent, gst_amount, mm_invoice, dealer_margin,
dealer_charges {incidental, fasttag, trc, rto_tape, cod, other, total, lines[{name, amount}]},
rsa {selected_years, selected_amount, options[{years, amount, default}]},
shield {selected_scheme, selected_amount, options[{scheme, amount, default}]},
discounts {oem_scheme, dealer_cont, cash, accessory, shield, rsa, cash_portion, credit_note,
           corporate {category, oem, dealer, total, options[]}, exchange {scheme, oem, dealer, total, options[]}, …},
insurance {…companies, default_company, plan, idv, od, tp, addons, standard_total…},
rto {permit, tax, surcharge, registration_fee, hypothecation, green_tax, rto_tape, fitness, total, bifurcation…},
accessories, tcs {applicable, limit, rate, amount}, invoice_value, on_road, on_road_nv, on_road_ov,
withheld, incomplete, hold, errors[]
```
`incomplete = true` → the vehicle master is not complete (never published); `hold` / `withheld` → a price hold is on
for its scope; `errors` lists what could not be computed.

## PricingEngineService (`App\Services\Vehicle\Pricing\PricingEngineService`)
| Method | Returns |
|---|---|
| `getPricingPayload(string $oemCode, array $options = [])` | the pricing JSON. Options: `channel` (`normal` / `csd`), `vin_type` (`nv` new-vehicle / `ov` old; `new`/`current` → `nv`, `old` → `ov`), `wef_date` (price as of a date), `permit` (force RTO / insurance permit, e.g. taxi Private vs Passenger) |
| `build($oemCode, $channel = 'normal', $vinType = 'nv', ?$wefDate, ?$permitOverride)` | same computation with positional args |
| `calculateAndPublish($oemCode, $channel, $vinType, ?$wefDate, ?$sessionId, ?$userId, ?$permitOverride)` | `['payload' => …, 'published' => bool, 'errors' => [...]]` — writes an active `Snapshot` (expiring the previous one at the WEF) unless the vehicle is incomplete |

**Invoice value** (IDV / RTO base) is an agreed interim formula (ex-showroom + dealer charges + selected RSA / Shield
− discounts) pending business sign-off (GAP-04); it lives in one private method so it can change in one place.

```php
$json = app(PricingEngineService::class)->getPricingPayload($variant->code, ['vin_type' => 'nv']);
if ($json['incomplete'] || $json['hold']) { /* don't quote; show why */ }
$onRoad = $json['on_road'];
```
API v1: `Api\V1\Vehicle\Pricing\PricingController::getPricing()` wraps this for the mobile app.

## Calculators
| Service | Method | Returns |
|---|---|---|
| `TcsService` | `config()`, `compute(float $invoice)` | `['applicable' => bool, 'limit', 'rate', 'amount']` — TCS when invoice > limit (`TcsConfig::current()`) |
| `RtoService` | `quote(array $ctx)` / `calculate($modelOrCtx, $options)` | `['rule_id', 'permit', 'selected_permit', 'permit_options', 'tax', 'tax_basis', 'surcharge', 'surcharge_formula', 'registration_fee', 'hypothecation', 'green_tax', 'rto_tape', 'fitness', 'duplicate_tax_card', 'penalty', 'total', 'bifurcation', …]` — best-matching `RtoRule` (`findBestMatch`) |
| `InsuranceService` | `quote(array $ctx)` / `calculate($modelOrCtx, $options)` | `['companies', 'default_company', 'default_plan', 'selected', 'idv_sum', 'od', 'tp', 'nildep', 'consumables', 'addons', 'standard_combo', 'standard_total', 'selected_total', …]` from `InsDefault` (company order), `InsBaseRule::findBestMatch()`, IDV slots and add-on rates |
`$ctx` keys used by the engine: `segment`, `model`, `variant`, `permit`, `fuel`, `wheels`, `cc`, `gvw`, `seating`,
`ex_showroom`, `invoice`.

---

## DEC-073 redesign — process engine (being rolled out phase by phase; plan `docs/plans/2026-09-28-pricing-redesign-DEC-073.md`)
| Piece | API |
|---|---|
| `Session\PricingStage` (enum) | `Started → Detecting → VehicleInfo → Prices → Addons → Rules → Impact → HoldCheck → Calculating → Summary`, terminal `Completed` / `Discarded`; `label()`, `order()`, `isTerminal()`, `steps()`, `fromStored($legacy)`; `ImportSession::stage()` returns it |
| `Session\PricingSessionService` | `gate(): ?ImportSession` (the one open process) · `start(UploadedFile, $sheets, $wef, $holdLists = [], ?$userId)` → Result (`ALREADY_ACTIVE`, `NO_SHEETS`; stores the upload under `storage/app/pricing/{id}/`) · `storeUpload($s, $file, $kind)` / `uploadAbsolutePath($s, $path)` · `record($s, fn)` (run writes that Discard can undo) · `advance($s, PricingStage, $stats)` (forward-only, except back to Vehicle Info) · `progress($s, [...])` · `putStats($s, $key, $value)` (replaces one stats section, e.g. this round's `vehicle_info`) · `markPublished($s)` · `discard($s)` → Result (`PUBLISHED` after publish; undoes exactly the session's changes) · `complete($s, $reopenLists)` → Result (`NOT_READY` before Summary) |
| `Session\PricingChangeRecorder` | `within($sessionId, fn)`, `active()`, `created/updated/softDeleted(Model)`, `captureBulk($query, $columns)` (called by `ExpiresActiveRows::expireActive()`), `rollback($sessionId)` — log table `xlr8_vehicle_pricing_session_changes`; inserted rows are removed, updated / expired rows restored |
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
| `Session\PricingImpactService` | step 7. `LISTS` · `summary($session)` → `vehicles {new, activated}` (from the change log; activated includes vehicles detected and completed in this process), `prices {normal|csd: {new, up, down, other}, unchanged}` (a row inserted this session vs the row it expired, or a same-WEF update), `addons {group: {written, replaced} \| {kept}}`, `rules {insurance|rto: run \| {kept}}`, `calculate {lists {PV…TAXI: {vehicles, held}}, vehicles, held, snapshots}` (Active vehicles with a live price per `price_list`; TAXI = `taxi_price YES`), `skipped {incomplete, inactive, no_price}` · `incomplete()` → `[[code, oem variant, segment, missing]]` |
| screens (`Admin\Pricing\Process\ImpactController`) | `pricing.workflow.impact-summary-view/{id}` (step 7 summary; at stage HoldCheck also the hold form and Calculate & publish) · `impact-incomplete/{id}` (download) · `impact-continue` (POST → stage HoldCheck, stamps `stats.impact.reviewed_at/by`) · `hold-check` (POST `action` hold/reopen, `hold_lists[]`; through `PricingHoldService` inside `record()`, so Discard undoes it) |

## The import workflow (legacy flow — replaced step by step by the DEC-073 engine above)
`ImportSession` stages: `idle → detecting → awaiting_vehicle → importing_prices → awaiting_addons → importing_addons →
awaiting_rules → calculating → summary → completed` (or `cancelled`). Status `active` / `completed` / `cancelled`.

| Step | Service call | Returns (`stats` merged into the session) |
|---|---|---|
| start | **replaced** by `Session\PricingSessionService::start()` + `DetectPriceListsJob` (above) | |
| detect vehicles from price lists | **replaced** by `Import\PriceListDetectService` (above); `PriceListVehicleDetector` remains only for the legacy price importer until Phase 4 | |
| Vehicle Info (specs) | **replaced** by `Import\VehicleInfoWorkbookService` + `ImportVehicleInfoJob` (above) | |
| prices | **replaced** by `Import\PriceListImportService` + `ImportPricesJob` (above) | |
| add-ons & discounts | **replaced** by `Import\AddonDiscountWorkbookService` + `ImportAddonsJob` (above) | |
| insurance & RTO rules (keep or import) | **replaced** by `Import\InsuranceWorkbookService` / `Import\RtoWorkbookService` + `ImportRulesJob` (above) | |
| hold (optional) | `PricingSessionService::setHoldScopes($session, $scopes)`; `Hold::putOnHold($scope, $reason, $userId)`, `Hold::reopen(...)`, `Hold::isHeld($scope = 'ALL')` | |
| calculate & publish | `RecalculateVehiclePricingJob` → `calculateAndPublish()` per affected variant (`Affected` rows) | `calculated`, `skipped_incomplete` |
| move on / cancel | `advance($session, $stage, $statsMerge, $userId)`, `updateStats()`, `discard($session, $userId)` | the session |
| current session | `activeSession()` | `?ImportSession` |

Sheet recognition: `Import\PriceListDetectService::sheetCode($title)`, `AddonDiscountWorkbookService::groupOf($title)`;
the Insurance / RTO workbooks match their sheet titles exactly (`SHEETS`, `RtoWorkbookService::SHEET`).
(`RulesWorkbookService`, `AddonDiscountImportService` and `PriceListVehicleDetector` were removed with DEC-073 phases 4–6.)

Header mapping for every sheet goes through `SheetHeaderService` (labels / aliases → stable `field_code`; registry labels and sheet cells are normalised the same way by the public `normalizeLabel()` — `-` `.` `_` → space, lower case; when several columns match one field the primary label beats its aliases, then the first column wins — BUG-200):
`labelMap($sheet)`, `mapHeaderRow($sheet, $cells)`, `findHeaderRow($sheet, $rows, $maxScan = 25)` (→ `[rowIndex,
fieldMap]`), `val($row, $map, $field, $default)`, `requiredFieldCodes($sheet)`, `headersForExport($sheet)`,
`forgetCache($sheet)`. Rows live in `SheetHeader` (`allLabels()`).

`PricingProcessLogger` (enabled by `config('pricing.process_log')` / `PRICING_PROCESS_LOG`): `forSession($id)`,
`info/warning/error/debug($msg, $ctx)`, `dumpSheetPreview(...)` → `pricing_process_session_N.log`.

`PricingResetService::run($afterDate, $flushQueue = true)` **destroys** pricing sessions, profiles, prices, history,
snapshots … after a date (keeps sheet headers, add-ons, discounts, rules) — **local only**.

---

## Rule, add-on and price entity services (the only writers, DEC-050/056-058)
All support `create / update / upsert / validate` (see [core.md](core.md)); the rule and add-on services also have
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
**Quotation / booking needs the on-road price** → `getPricingPayload()`; never recompute pieces in a controller. Store
the JSON you showed the customer (quote snapshot) — the live price may change after the next WEF.

**"Why is this variant not priced?"** → `app(VehicleService::class)->missingFields($variant)`, then
`Profile::forModel($code)->first()` flags, then `Hold::isHeld($scope)`.

**New rule set for October** → **Pricing → Workflow** (step 6, Insurance & RTO) or `RtoWorkbookService::import($path, $wef)` /
`InsuranceWorkbookService::import($path, $wef)` inside a session; old rows are expired, not deleted.

## Gotchas (from production incidents — see the rules file)
- Never load a whole workbook; one sheet at a time, capped columns, chunked rows.
- Same WEF → update the live row; different WEF → expire then insert. Group imports expire only their group.
- Scope match: ANY / blank / ALL / `*` = all; comma = union; the more specific row replaces the whole match.
- Calculate skips incomplete vehicles.
- Pricing admin needs `PRC_*` permissions.

## Testing
Pricing tests need vehicle rows → `xlrm_testing`. Build a payload for a known complete variant and assert keys exist
(`array_keys(PricingJsonContract::empty())` ⊆ payload keys) and totals; for rule services assert `expireActive()`
counts and that history rows remain.
