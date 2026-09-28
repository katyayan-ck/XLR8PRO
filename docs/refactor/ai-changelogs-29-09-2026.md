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
