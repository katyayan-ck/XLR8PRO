# AI changelog: 29-09-2026 (Track A: pricing redesign, DEC-073 Phase 10)

## getPricing — step 11 (DEC-073, DEC-080)
- **New:** `app/Services/Vehicle/Pricing/Engine/PricingQueryService.php`:
  - `getPricing($oemCode, $options)`:
    - serves the published snapshot valid on the date;
    - defaults to the vehicle's own permit, NV and the normal channel;
    - fails with `NOT_FOUND` or `ON_HOLD` (the pricing is included with `hold = true`).
  - `apply($payload, $options)` applies the caller's selections and recomputes the totals the way Calculate & Publish
    does. Selections: RSA years, Shield scheme, insurance company / plan / add-ons, BH, outside state, COD, exchange,
    corporate. An unknown selection goes to `errors[]`.
- **`InsuranceCalculator`:** each plan now freezes `od_gst_pct` / `tp_gst_pct`, so a re-priced insurance combo uses the
  published rates.
- **`PricingContract::normalize()`:**
  - Before: numbers came back as MySQL JSON returned them (`1000000.0` → int `1000000`).
  - After: a value whose default is a float is cast to float, so amounts are always floats.
- **API:**
  - Before: `Api/V1/Vehicle/Pricing/PricingController` was unrouted and ran on the legacy `PricingEngineService`.
  - After: it is rewritten on `BaseController` + `PricingQueryService`, and routed as
    `GET /api/v1/vehicle/pricing/{oemCode}` (`api.vehicle.pricing.show`, `auth:sanctum` + `validate_device`).
  - Responses: 200 `data.pricing`; 404 `PRICING_NOT_FOUND`; 423 `PRICING_ON_HOLD`.
  - The stale "Pricing API removed" comment in `routes/api.php` is gone.
- **`ErrorCodeEnum`:** adds `PRICING_NOT_FOUND` (404) and `PRICING_ON_HOLD` (423). Pint also normalised the file's
  existing formatting.
- **Admin:** new `Admin/Pricing/PriceLookupController` + `resources/views/admin/pricing/lookup.blade.php`
  (`admin/pricing/lookup`, `pricing.lookup`, `PRC_WKFL_VIEW`). It has the same options as the API, the build-up, the
  discounts, the RTO / insurance heads and the raw JSON.
- **`PricingEngineService`:** no callers remain; it is removed in Phase 11.
- **Docs:** `docs/domains/pricing.md` gains a getPricing section; the "Need" table points at `getPricing`.
- **Tests:** `tests/Feature/Pricing/GetPricingTest.php` (7):
  - default permit + recomputed totals;
  - selections re-price;
  - unknown selections are reported;
  - date validity / channel → NOT_FOUND;
  - TAXI vs PV hold;
  - API envelope 200 / 404 / 423 / 401 with a device-bound token;
  - the admin lookup permission.

## Price List screens + Pricing menu (DEC-081)
- **New:**
  - `app/Services/Vehicle/Pricing/Engine/PriceListService.php`: `rows($list, $date)` and `counts($date)`; chunked
    projection; cache keyed on the latest snapshot change.
  - `Admin/Pricing/PriceListController`: `index` / `show` / `rows`, open to every logged-in user.
  - Views: `resources/views/admin/pricing/price-list/{index,show}.blade.php`. The AG Grid 36.2.0 has column groups
    Vehicle / Add-ons / Standard discounts / Conditional discounts / On-road, hover break-ups, CSV, quick search and
    the hold banner.
- **Migration `2026_09_29_014111_add_list_columns_to_pricing_snapshots`:**
  - Before: `xlr8_vehicle_pricing_snapshots` had no list columns.
  - After: it has `price_list` and `vehicle_permit`, plus the index (`price_list`, `channel`, `vin_type`,
    `is_active`), back-filled from the payload.
  - Run on xlrm and xlrm_testing.
- **`SnapshotPublisher`** writes the two new columns; the `Snapshot` model's fillable fields and properties are updated.
- **Menu** (`menu_items.blade.php`):
  - A new top-level **Price List** menu (all lists) for every user.
  - **Admin → Pricing** (Pricing Process, Price Lookup, Price Holds, TCS, RTO Rules, Insurance Rules), each item gated
    by its `PRC_*` permission. Resolves BUG-069.
  - The stale "Price List hidden" comment is removed.
- **Tests:** `tests/Feature/Pricing/PriceListTest.php` (3):
  - list membership (PV / Taxi / CSD / CV), date validity, break-ups and conditional columns;
  - cache refresh after a publish, and the hold flag;
  - any logged-in user can open the lists; guests are redirected; an unknown list gives 404.

## Quotation on getPricing, booking hold guard, reset hardening, legacy engine removed (DEC-082, DEC-073 phase 11)
- **New `app/Services/Sales/Quotation/QuotationPricingService.php`:**
  - `forVehicle()`: getPricing → the screen's shape, with every published permit.
  - `screen()`, `validateSubmission()` (gate + TCS), `vehicleOptions()`.
- **`VehicleService::variantGroupOptions()`:** one row per OEM variant.
- **`PricingQueryService::holdMessage()`:** new.
- **`QuotationCrudController`:**
  - New `pricing()` (`sales.quotation.pricing`, JSON 200 / 404 / 423) and `vehicleOptions()`
    (`sales.quotation.vehicle-options/{level}`), gated by `SLS_QUOT_CREATE` or `SLS_QUOT_EDIT`.
  - `store()` requires an OEM code; `store()` / `update()` re-validate through `repriceSubmission()` and store
    `standard_data.pricing`.
  - Pint normalised the file's existing style.
- **`quotation/create.blade.php`:**
  - Before: `ENQUIRIES` / `PRICING` mocks, auto-loaded enquiry 019 (BUG-203).
  - After: Segment → Model → Variant → Colour pickers in create mode (locked where the enquiry has codes). The colour
    loads getPricing into `applyPricing()` (the former mock-fetch handler). Save stays disabled until prices load and
    the list is open.
  - Edit mode reloads `PRICING.saved`. The TCS limit / rate come from the pricing. The exchange scheme is no longer
    auto-applied on live prices.
  - The mock HTML and the Reset-mock handler are removed.
- **`BookingCoreService::heldPriceMessage()`:** new; `BookingCrudController` `create()` warns and `store()` refuses when
  the linked quotation's price list is held (guide: `docs/domains/sales-booking.md`). The stale `store()` docblock is fixed. Pint normalised the file (PHPStan errors 446 → 441).
- **`PricingResetController`:**
  - Before: `GET pricing/reset?confirm=1` ran the destructive reset.
  - After: GET = dry preview + form (`admin/pricing/reset.blade.php`); `POST pricing.reset.run` needs
    `PRC_RESET_MANAGE`, a local / testing environment and the typed `RESET`.
- **Removed:** `PricingEngineService`, `PricingJsonContract`, `TcsService`, and
  `tests/Unit/Services/Vehicle/Pricing/PricingEngineServiceTest.php` (no callers). `InsuranceService` / `RtoService`
  stay for the admin test-calculate screens.
- **Docs:**
  - `docs/domains/pricing.md` (legacy sections replaced), `crm-enquiry-quotation.md` (new "Quotation pricing" section),
    `api-v1-adapters.md`, `docs/domains/README.md`.
  - `.ai/rules/services.md`, `modules/sales.md`, `modules/vehicle-pricing.md`.
  - BUG-203 logged and fixed.
- **Tests:**
  - `QuotationPricingTest` (4): the adapter shape, gate / TCS re-validation, endpoints + save (no vehicle / held /
    stored pricing), the create page without mocks.
  - `PricingResetTest` (1).
  - Pricing + vehicle + sales unit suites: 150 passed.
  - JS of the rendered create and edit pages passes `node --check`.
  - Smoke: users 1 and 40.

## Pricing masters — phase A1: automatic recalculation + sync stamp (DEC-083)
- **New:**
  - `Engine\PricingRecalcService`, `Engine\PricingParamObserver`, `Engine\PricingParamRegistry`,
    `Jobs\Vehicle\Pricing\RecalculateAffectedJob`, `PricingSyncStamp`.
  - Model `RecalcRun` + migration `2026_09_29_025300_create_pricing_recalc_runs_dec083` (run on xlrm and
    xlrm_testing).
- **`PricingCalculationService`:**
  - Before: the vehicle loop lived inside `calculate()` and needed a session.
  - After: `publishVehicles()` is shared (the session is optional); held lists are also checked per vehicle.
- **`SnapshotPublisher::publish()`:** the session is now optional, and each publish touches the sync stamp.
- **`PricingSessionService` `complete()` / `discard()`:** re-queue marks that waited for the process.
- **`AppServiceProvider`:** observer registration; `Queue::after` flushes the stamp and dispatches pending
  recalculation; the new singletons.
- **Setting:** `pricing.last_updated_at` (`config/platform.php`, docs/utilities/16-reference.md).
- **Tests:**
  - New `PricingRecalcTest` (4).
  - The fixture moved to the `BuildsPricedVehicle` trait (shared with `PricingCalculationTest`).
  - Pricing + vehicle suites: 103 passed.
- **Found while building:** with the sync queue, a job that re-dispatched itself while a process was open recursed
  forever. The run now simply waits, and the process's complete / discard re-queues it.

## Pricing masters — phases A2 + B: master kit, add-on / discount masters, Loyalty (DEC-083)
- **Kit:**
  - `App\Support\PricingMaster\{MasterDefinition, WorkbookGroupMaster, MasterRegistry}` and
    `Admin\Pricing\MasterController`.
  - Views `admin/pricing/masters/{index,form}`; `Jobs\Vehicle\Pricing\ImportPricingMasterJob`; model `MasterImport`.
  - Migrations: `2026_09_29_192219_create_pricing_master_imports_dec083` and `…pricing_master_permissions_dec083`
    (processes + `PRC_{DLRC,DBRK,RSA,SHLD,CORP,EXCH,LYLT,ACCS,INCO,INPF,INAD}_{VIEW,MANAGE}`, `PRC_RCLC_VIEW`,
    `PRC_INSR_MANAGE`). Run on xlrm and xlrm_testing.
- **Masters:** Dealer Charges, Discounting Breakup, RSA, Shield, Corporate, Exchange, Loyalty
  (`app/Support/PricingMaster/Masters/*`).
- **Loyalty:**
  - `AddonDiscountWorkbookService`: `LOYALTY` sheet / headers / schemes, export and import like Exchange. Migration
    `2026_09_29_192409_pricing_loyalty_sheet_headers_dec083`.
  - `RuleBook`, `ComponentResolver`, `SnapshotBuilder`, `PricingContract` (`discounts.loyalty`).
  - `PricingQueryService` (the `loyalty` option, in totals); the API + admin lookup validation / select.
  - `PriceListService` + the Price List view (Loyalty columns); `QuotationPricingService` (`loyalty-scheme`, CN2);
    the process add-ons view label.
- **Menu:** Admin → Pricing → Masters, each item gated by its `_VIEW` permission.
- **Rules doc:** `.ai/rules/admin-backpack.md` module table gains the new PRC process codes.
- **Fix found while building:**
  - Before: removing a future-dated rule row failed validation (expiry before its WEF).
  - After: it now expires at max(today, WEF).
- **Tests:**
  - `PricingMasterTest` (5): permissions + every screen renders; CRUD + WEF versioning + recalculation; locked while
    a process is open; one-row-per-record round trip (Discounting Breakup); workbook round trip (Dealer Charges).
  - `LoyaltyDiscountTest` (2).

## Pricing masters — phases C–E: rules + insurance masters, accessories, recalculation log, configurable logo (DEC-083)
- **Masters:**
  - `RtoRulesMaster`, `InsuranceRulesMaster` (base rule + heads + IDV slots + add-on rates in one form).
  - `InsCompaniesMaster`, `InsPreferencesMaster`, `InsAddonsMaster`, `AccessoriesMaster`, `AccessoryScopesMaster`.
- **Migration `2026_09_29_193500_pricing_insurance_masters_dec083`:**
  - New tables `xlr8_vehicle_pricing_ins_companies` (seeded from the companies in use) and
    `xlr8_vehicle_pricing_ins_addons` (17 add-ons; NIL_DEP + CONSUMABLES default).
  - `segment` on `xlr8_vehicle_pricing_ins_defaults`.
  - Run on xlrm and xlrm_testing.
- **New:**
  - models `InsCompany`, `InsAddon`;
  - entity services `Rules\InsCompanyService`, `Rules\InsAddonService`, `Accessories\AccessoryItemService`,
    `Accessories\AccessoryScopeService`.
  - `InsDefaultService` gains `segment` (model defaults to ANY).
- **Engine:**
  - `InsuranceCalculator::companyOrder()` uses the model rows, else the segment rows, else ANY.
  - Default add-ons and names come from the add-on master (`RuleBook::$defaultInsuranceAddons` /
    `$insuranceAddonNames`).
  - `PricingParamRegistry` watches `InsCompany` / `InsAddon`.
- **`InsuranceWorkbookService`:**
  - `export()` / `import()` take `$parts`.
  - The companies sheet exports and expires model-level rows only (BUG-205).
  - `HEAD_COLUMNS` is now public.
- **Accessories:**
  - The typed-sheet import (`importExcelWithSheetOrder`) is authoritative.
  - Deleted: `ImportVehicleAccessories` command, `AccessoryImportService`, `VehicleAccessoriesImport` (BUG-179 fixed).
  - `AccessoryScope::$fillable` gains `permit` (BUG-204 fixed).
- **Recalculation log:** `RecalcLogController` + `admin/pricing/recalc-log.blade.php` (`pricing.recalc-log`,
  `PRC_RCLC_VIEW`); Menu → Pricing → Masters.
- **Menu:** the old RTO / Insurance rule links were replaced by the masters.
- **Logo:**
  - Setting `branding.logo` (type `image`); `SettingsService::setImage()`; the `SystemSetting` `setting_image` media
    collection (public disk, single file).
  - Route `utils.settings.image` (`SettingsAdminController::image`), with an image row on the Settings screen
    (preview, upload, "use the built-in image").
  - Helper `site_logo_url($fallback)`.
  - Used in both menu layouts (the logo now always links to `backpack_url('dashboard')`; the horizontal layout had no
    link and the vertical one went to the site root), the login page, and the quotation / OTF / OTF-PDF prints
    (fallback `images/bikaner_logo.png`).
  - `.xl-site-logo` CSS in `public/css/xl-ui.css`.
- **`SystemSetting`:** `@property` docs added.
- **Tests:**
  - `InsuranceAccessoryMastersTest` (5), `SiteLogoTest` (3), plus a recalculation-log test in `PricingRecalcTest`.
  - Pricing / utils / platform suites: 126 passed (1 known skip).
