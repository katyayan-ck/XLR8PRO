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
| permit map | table `xlr8_vehicle_pricing_permit_map` (vehicle permit + wheels → RTO rule permit, insurance permit): 4W Passenger (taxi) → RTO "Taxi" / insurance "Passenger"; MISC → "Ambulance" / "Misc" |
| snapshots | unique key `(model_code, channel, vin_type, permit, wef_date)` + `rto_permit`, `insu_permit` |
| `Import\PriceListDetectService` | step 2. `detect($path, $sheetTitles, ?$onProgress)` → per sheet `rows, known, created, csd_unknown, duplicates, blank_code, errors[], new_codes[], csd_unknown_codes[]` (lists capped at 500). Known = a variant with the full OEM code; new → `VehicleService::createStubFromPriceList()` (INCOMPLETE; LMM TZU colour `NA`); CSD never creates vehicles; a blank OEM Model is an error, not a stub. Statics `sheetCode($title)` ("Price List LMM TZU" → `PRICE_LIST_LMM_TZU`), `matchSheets($titles, $lists)` → `['found' => [list => title], 'missing' => [list]]`. `legacyCodeCount()` → variant rows whose `code` lacks the colour suffix (BUG-199; the Start screen warns — purge and re-import first, DEC-074). Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\DetectPriceListsJob($sessionId)` | timeout 1800, tries 1. Runs detect inside `record()`, writes `progress` (`step, state running/done/failed, message, error`), then `advance(VehicleInfo, ['detect' => ['sheets' => …, 'totals' => [created, known, csd_unknown, duplicates, errors]]])`. Real Pricing.xlsx (6 lists, 4,862 codes): 3,414 stubs in ~5 min, 98 MB |
| screens (`Admin\Pricing\Process\PricingProcessController`) | `pricing.workflow.index` (gate: Resume / Discard, stepper, Detect report; `PRC_WKFL_VIEW`) · `pricing.workflow.start-form` / `pricing.workflow.start` (POST `file` .xlsx ≤ 20 MB, `lists[]` of PV/CV/BEV/LMM/LMM_TZU/CSD — each must exist in the workbook, `wef_date` required, `hold_lists[]` optional; `PRC_WKFL_MANAGE`) · `pricing.workflow.status/{id}` (JSON `stage, stage_label, terminal, progress, totals`; polled while detecting) · `pricing.workflow.discard` (POST; before publish only). Labels in `lang/en/pricing.php` |
| `Import\VehicleInfoWorkbookService` | step 3. `COLUMNS` (reference Vehicle Info layout + `Missing Fields`) · `export($path)` → `['rows', 'incomplete']`: every variant (all statuses), sorted Segment → OEM Model → code; Fuel / Permit / Body Make / Body Type / Status written as key-value codes; incomplete rows highlighted · `import($path, ?$onProgress)` → `rows, completed, newly_completed, incomplete, rejected, unknown, issues[] {row, code, result incomplete/rejected, reason}` (issues capped at 5,000): each row through `VehicleService::applyVehicleInfo()`; blank cells keep the stored value; OEM Model / Variant are not imported; unknown codes are rejected (vehicles come only from Detect); unknown lookup values reject the row. Run inside `record()` |
| `Jobs\Vehicle\Pricing\Process\ImportVehicleInfoJob($sessionId, $uploadPath)` | timeout 1800, tries 1. Import inside `record()`; `putStats('vehicle_info', ['round', 'at'] + result)`; progress `step vehicle_info`. The session stays at Vehicle Info (export → fix → re-import loop) |
| screens (`Admin\Pricing\Process\VehicleInfoController`) | `pricing.workflow.vehicle-info-form` (status counts, download, upload, this round's summary + first 200 issues) · `vehicle-info-export/{id}` (download) · `vehicle-info-import` (POST `file`; queues the job; after a later step it sends the process back to Vehicle Info; refused after publish or while a step runs) · `vehicle-info-issues/{id}` (issues workbook) · `vehicle-info-continue` (POST → stage Prices). VIEW / MANAGE as above |

## The import workflow (legacy flow — replaced step by step by the DEC-073 engine above)
`ImportSession` stages: `idle → detecting → awaiting_vehicle → importing_prices → awaiting_addons → importing_addons →
awaiting_rules → calculating → summary → completed` (or `cancelled`). Status `active` / `completed` / `cancelled`.

| Step | Service call | Returns (`stats` merged into the session) |
|---|---|---|
| start | **replaced** by `Session\PricingSessionService::start()` + `DetectPriceListsJob` (above) | |
| detect vehicles from price lists | **replaced** by `Import\PriceListDetectService` (above); `PriceListVehicleDetector` remains only for the legacy price importer until Phase 4 | |
| Vehicle Info (specs) | **replaced** by `Import\VehicleInfoWorkbookService` + `ImportVehicleInfoJob` (above) | |
| prices | `PriceListPricingImporter::importFile($path, $session, $sheetCodes, $wefDate, $channel, $userId, $onProgress)` | `prices_written`, `price_changes`, `skipped_incomplete`, `skipped_no_price`; writes `ChangeFlag` rows |
| add-ons & discounts | `AddonDiscountImportService::importFile($path, $session, $selectedSheets, $wefDate, $userId)`; template `AddonDiscountExportService::exportForSession($session)` | per sheet (`DEALER_CHARGES`, `SHIELD`, `RSA`, `EXCHANGE`, `CORPORATE`): `written`, `skipped`, `errors` |
| insurance & RTO rules (keep or import) | `RulesWorkbookService::presence()` (what exists), `exportCurrent($session)`, `importFile($path, $session, $kinds, $wefDate, $userId)` | `rto_count`, `insurance_count`, `written`, `skipped`, `errors` |
| hold (optional) | `PricingSessionService::setHoldScopes($session, $scopes)`; `Hold::putOnHold($scope, $reason, $userId)`, `Hold::reopen(...)`, `Hold::isHeld($scope = 'ALL')` | |
| calculate & publish | `RecalculateVehiclePricingJob` → `calculateAndPublish()` per affected variant (`Affected` rows) | `calculated`, `skipped_incomplete` |
| move on / cancel | `advance($session, $stage, $statsMerge, $userId)`, `updateStats()`, `discard($session, $userId)` | the session |
| current session | `activeSession()` | `?ImportSession` |

Sheet recognition: `PriceListVehicleDetector::sheetCodeFromTitle($title)` (static) and
`AddonDiscountImportService::sheetCodeFromTitle($title)` map a sheet title to its code (`SHEET_MAP`);
`RulesWorkbookService::kindFromTitle($title)` → `rto` / `insurance` / null; its statics `percentOrNum($v)` ("18%" → 18.0) and
`num($v)` parse sheet numbers (null when blank).

Header mapping for every sheet goes through `SheetHeaderService` (labels / aliases → stable `field_code`):
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
| `Prices\PriceService` | `xlr8_vehicle_pricing` | one row per (OEM code, channel, WEF), key fixed once created; `expire($price, $wef)` closes a live price |
| `Addons\AddonService` | `xlr8_vehicle_pricing_addons` | RSA / Shield; scope columns `segment`, `model_code` (ANY = all), `variant_code`, `permit`, `shield_pack`, `transmission`, `fuel`; `tenure_years`, `amount`, `oem_share`, `dealer_share`, `is_default` |
| `Addons\DealerChargeService` | `xlr8_vehicle_pricing_dealer_charges` | `segment` (ANY), `permit`, `model_code`; `incidental`, `fastag`, `trc`, `rto_tape`, `cod`, `kazam`, extra json — note BUG-178 (engine ignores WIDE charges / model scope) |
| `Addons\DiscountService` | `xlr8_vehicle_pricing_discounts` | Exchange, Corporate …; total = sheet Total or OEM + dealer share |
| `Rules\RtoRuleService` | `xlr8_vehicle_pricing_rto_rules` | scope `permit`, `wheels` (`RuleFields::wheels()`: ANY → null), `reg_type`, `body_type`, `gvw_range`, `fuel_type`, `cc_range`; tax factor / basis / slab, fees |
| `Rules\TcsConfigService` | `xlr8_vehicle_pricing_tcs_config` | at most one active row; `saveCurrent($input)` |
| `Rules\InsBaseRuleService` | `xlr8_vehicle_pricing_ins_base_rules` | plan `"1+3"` → `od_years` / `tp_years`; OD factor, TP amounts |
| `Rules\InsIdvSlotService` | `xlr8_vehicle_pricing_ins_idv_slots` | per base rule: `year_no`, `idv_basis` text, `idv_pct` |
| `Rules\InsDefaultService` | `xlr8_vehicle_pricing_ins_defaults` | companies per model + permit, priority 1 = default (`InsDefault::getCompanies($model, $permit)`) |
| `Rules\InsAddonRateService` | `xlr8_vehicle_pricing_ins_addon_rates` | written by screens only; no importer yet (GAP-01) |

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
| `Pricing` / `PricingHistory` | live price rows / history | `active()`, `forModel($code)`, `history()`; `static getActive($code, $channel)` |
| `Profile` | per-variant readiness flags (`is_vehicle_master_complete`, `is_pricing_template_complete`, …) | `forModel()`, `publishable()`, `incomplete()`, `ofSegment()`; `canBeMarkedActive()` |
| `Snapshot` | published pricing JSON (`payload`) per code / channel / VIN type / WEF | `active()`, `forCode($oem)` |
| `Draft` | calculated but unpublished payloads | `forModel()` |
| `ChangeFlag` / `Affected` | what changed in this session / which variants to recalc | `unprocessed()`, `ofType()` / `pending()`, `forSession($id)` |
| `Hold` | price holds by scope | `held()`; `isHeld()`, `putOnHold()`, `reopen()` |
| `Csd` | CSD channel price rows | `active()`; `static getActive($modelCode)` |
| `Addon`, `DealerCharge`, `Discount` (+ `AddonHistory`, `DiscountHistory`) | add-ons, charges, discounts | `active()`, `ofType($type)` |
| `RtoRule`, `TcsConfig`, `InsBaseRule` (+ `idvSlots()`), `InsIdvSlot` (`baseRule()`), `InsDefault`, `InsAddonRate` | rule sets | `findBestMatch($criteria)` (most specific wins), `TcsConfig::current()`, `InsDefault::getCompanies()`, `InsAddonRate::forCompanyPermit()` |
| `SheetHeader` | Excel header registry | `active()`, `forSheet()`, `ordered()`, `allLabels()` |

## Use cases
**Quotation / booking needs the on-road price** → `getPricingPayload()`; never recompute pieces in a controller. Store
the JSON you showed the customer (quote snapshot) — the live price may change after the next WEF.

**"Why is this variant not priced?"** → `app(VehicleService::class)->missingFields($variant)`, then
`Profile::forModel($code)->first()` flags, then `Hold::isHeld($scope)`.

**New rule set for October** → **Pricing → Workflow** (rules stage) or `RulesWorkbookService::importFile()` with the new
WEF; old rows are expired, not deleted.

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
